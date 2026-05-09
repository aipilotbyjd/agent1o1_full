# Backup Strategy — Zero Data Loss

## The 3-2-1 Rule
- **3** copies of your data
- **2** different storage types
- **1** offsite location

This plan achieves 3-2-1 using: database (live) + local backup + offsite cloud backup.

---

## What Gets Backed Up

| Data | Backup Method | How Critical |
|---|---|---|
| PostgreSQL database | `pg_dump` daily | Critical — all workflow data |
| Uploaded files | Docker volume snapshot | Important — user file attachments |
| Environment config | Manual copy | Important — kept securely off-server |

Redis does **not** need backups — it holds transient queue data that regenerates automatically.

---

## Backup Script (`backup.sh`)

Place this file in your project root. Full contents in `02-docker-configuration.md`.

What it does:
1. Dumps PostgreSQL to a compressed `.sql.gz` file
2. Saves it locally under `~/backups/`
3. Optionally uploads to any cloud storage via rclone
4. Deletes local backups older than 7 days
5. Deletes offsite backups older than 7 days

### Schedule — Daily at 2am
```bash
crontab -e
```
Add:
```
0 2 * * * /home/deploy/YOUR_REPO/backup.sh >> /home/deploy/backups/backup.log 2>&1
```

### Verify It Works
```bash
# Run manually
./backup.sh

# Check the output
ls -lh ~/backups/

# Check the log
cat ~/backups/backup.log
```

---

## Offsite Backup Setup

Offsite backups protect against total server failure. Set up one of these:

### Option A: Backblaze B2 (Recommended — 10GB Free)

1. Sign up at [backblaze.com](https://backblaze.com) — no credit card for B2
2. Create a bucket (e.g., `myapp-backups`)
3. Go to **App Keys** → Create key with read/write access to your bucket
4. On your server:

```bash
# Install rclone
curl -fsSL https://rclone.org/install.sh | sudo bash

# Configure
rclone config
```

In the config wizard:
- Name: `backblaze`
- Type: `b2`
- Account: Your Key ID
- Key: Your Application Key

5. In `.env`:
```env
BACKUP_OFFSITE_ENABLED=true
BACKUP_OFFSITE_DEST=backblaze:myapp-backups/db
BACKUP_KEEP_DAYS=30
```

### Option B: Cloudflare R2 (10GB Free/Month, No Egress Fees)

1. Cloudflare dashboard → **R2** → Create bucket
2. **Manage R2 API Tokens** → Create token with Object Read & Write
3. On your server: `rclone config`
- Type: `s3`, Provider: `Cloudflare`
- Access key ID and Secret from Cloudflare

In `.env`:
```env
BACKUP_OFFSITE_ENABLED=true
BACKUP_OFFSITE_DEST=r2:myapp-backups/db
```

### Option C: Any S3-Compatible Storage
rclone supports 40+ providers. Run `rclone config` and choose from the list.

---

## Retention Policy

| Location | Kept For | Why |
|---|---|---|
| Local (server) | 7 days | Quick restore, limited disk |
| Offsite (cloud) | 30 days | Longer recovery window |

Adjust `BACKUP_KEEP_DAYS` in `.env` to change local retention.
Change the `rclone delete` line in `backup.sh` for offsite retention.

---

## Restore Procedures

### Restore on the Same Server (Something Corrupted)

```bash
# List available backups
ls -lh ~/backups/

# Stop the app to prevent writes during restore
docker compose -f docker-compose.prod.yml stop app

# Restore (replace FILENAME with actual file)
gunzip < ~/backups/db_2026-03-29_02-00-00.sql.gz | \
    docker exec -i $(docker ps -qf "name=pgsql") \
    psql -U myapp myapp

# Restart the app
docker compose -f docker-compose.prod.yml start app
```

### Restore After Total Server Loss (New Server)

```bash
# 1. On your local machine — download from offsite
rclone copy backblaze:myapp-backups/db/db_LATEST.sql.gz ./

# 2. Set up new server (follow 03-hetzner-deployment.md or 04-azure-deployment.md)
# 3. Deploy the app (./deploy.sh) — this creates the database structure

# 4. Copy backup to new server
scp db_LATEST.sql.gz deploy@NEW_SERVER_IP:~/

# 5. On new server — drop and recreate the database cleanly
docker exec -i $(docker ps -qf "name=pgsql") \
    psql -U myapp -c "DROP DATABASE IF EXISTS myapp; CREATE DATABASE myapp;"

# 6. Restore
gunzip < ~/db_LATEST.sql.gz | \
    docker exec -i $(docker ps -qf "name=pgsql") \
    psql -U myapp myapp

# 7. Restart app
docker compose -f docker-compose.prod.yml restart app
```

### Restore Uploaded Files

If you have a file storage volume backup:
```bash
# Copy files from old server
rsync -avz deploy@OLD_SERVER:/var/lib/docker/volumes/myapp_app-storage/ \
    deploy@NEW_SERVER:/var/lib/docker/volumes/myapp_app-storage/
```

---

## Testing Your Backups (Do This Monthly)

A backup you've never tested is not a backup.

```bash
# 1. Create a test database
docker exec -i $(docker ps -qf "name=pgsql") \
    psql -U myapp -c "CREATE DATABASE myapp_test;"

# 2. Restore latest backup into test DB
gunzip < ~/backups/$(ls -t ~/backups/ | head -1) | \
    docker exec -i $(docker ps -qf "name=pgsql") \
    psql -U myapp myapp_test

# 3. Verify data looks correct
docker exec -i $(docker ps -qf "name=pgsql") \
    psql -U myapp myapp_test -c "\dt"

# 4. Clean up
docker exec -i $(docker ps -qf "name=pgsql") \
    psql -U myapp -c "DROP DATABASE myapp_test;"
```

---

## Monitoring Backup Health

Add to your crontab to alert if backups fail:
```
0 2 * * * /home/deploy/YOUR_REPO/backup.sh >> /home/deploy/backups/backup.log 2>&1 || \
    echo "BACKUP FAILED on $(hostname) at $(date)" | mail -s "Backup Failed" your@email.com
```
