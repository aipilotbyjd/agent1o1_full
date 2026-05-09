# Hetzner Deployment Guide

**Cost:** €3.79/mo (CX22) — best value for a production SaaS
**Prerequisites:** A domain name, a GitHub account with your code pushed

---

## Step 1 — Create the Server

1. Sign up at [hetzner.com/cloud](https://hetzner.com/cloud)
2. Create a new project
3. Click **Add Server** with these settings:

| Setting | Value |
|---|---|
| Location | Choose nearest to your users |
| Image | Ubuntu 24.04 |
| Type | **CX22** (2 vCPU, 4GB RAM) — €3.79/mo |
| SSH Keys | Add your public key (`~/.ssh/id_rsa.pub`) |
| Firewall | Create one — allow SSH (22), HTTP (80), HTTPS (443) |

4. Click **Create & Buy Now** — note your server IP.

---

## Step 2 — First Login & Create Deploy User

```bash
ssh root@YOUR_SERVER_IP

# Update system packages
apt update && apt upgrade -y

# Create a non-root user for deployments
adduser deploy
usermod -aG sudo deploy

# Copy SSH keys to deploy user
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy

# Test the new user (in a new terminal)
ssh deploy@YOUR_SERVER_IP
```

From this point, always login as `deploy`, not `root`.

---

## Step 3 — Harden SSH

```bash
sudo nano /etc/ssh/sshd_config
```

Change or add these lines:
```
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
MaxAuthTries 3
```

```bash
sudo systemctl restart sshd
```

---

## Step 4 — Configure Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status
```

Expected output:
```
To          Action    From
--          ------    ----
OpenSSH     ALLOW     Anywhere
80/tcp      ALLOW     Anywhere
443/tcp     ALLOW     Anywhere
```

---

## Step 5 — Install Fail2ban (Block Brute Force)

```bash
sudo apt install fail2ban -y
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

---

## Step 6 — Add Swap Space (Prevents Out-of-Memory Crashes)

The CX22 has 4GB RAM. Add 2GB swap as a safety net for builds.

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

---

## Step 7 — Install Docker

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker deploy
sudo apt install docker-compose-plugin -y

# Log out and back in for group change to take effect
exit
ssh deploy@YOUR_SERVER_IP

# Verify
docker --version
docker compose version
```

---

## Step 8 — Deploy Your Application

```bash
# Clone your repository
git clone https://github.com/YOUR_USERNAME/YOUR_REPO.git
cd YOUR_REPO

# Set up environment file
cp .env.production.example .env
nano .env
```

Fill in your `.env` file (see `02-docker-configuration.md` for the template).

Generate your `APP_KEY`:
```bash
docker run --rm php:8.2-cli php -r \
  "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```
Copy the output and paste it as `APP_KEY=` in your `.env`.

Run the deployment:
```bash
chmod +x deploy.sh
./deploy.sh
```

This will:
- Pull your code
- Build the Docker image (5–10 minutes first time)
- Start PostgreSQL and Redis
- Run database migrations automatically
- Cache config/routes/views
- Start Nginx, PHP-FPM, and Horizon

---

## Step 9 — Point Your Domain to the Server

In your domain registrar, add an **A record**:
- Type: `A`
- Name: `@` (root domain) and `www`
- Value: `YOUR_SERVER_IP`
- TTL: 300 (5 minutes)

Wait 5–30 minutes for DNS to propagate. Test with:
```bash
ping yourdomain.com
```

---

## Step 10 — Free SSL Certificate (HTTPS)

```bash
sudo apt install certbot -y

# Stop app temporarily to free port 80
docker compose -f docker-compose.prod.yml down

# Get the certificate
sudo certbot certonly --standalone \
    -d yourdomain.com \
    -d www.yourdomain.com \
    --non-interactive \
    --agree-tos \
    --email your@email.com

# Restart the app
docker compose -f docker-compose.prod.yml up -d
```

Then update your `.env`:
```env
APP_URL=https://yourdomain.com
```

And redeploy:
```bash
./deploy.sh
```

**Auto-renew SSL** (certificates expire every 90 days — automate renewal):
```bash
sudo crontab -e
```
Add:
```
0 3 1 * * certbot renew --pre-hook "docker compose -f /home/deploy/YOUR_REPO/docker-compose.prod.yml down" --post-hook "docker compose -f /home/deploy/YOUR_REPO/docker-compose.prod.yml up -d"
```

---

## Step 11 — Set Up Automatic Backups

```bash
chmod +x backup.sh
mkdir -p ~/backups

# Test the backup
./backup.sh

# Schedule daily at 2am
crontab -e
```
Add:
```
0 2 * * * /home/deploy/YOUR_REPO/backup.sh >> /home/deploy/backups/backup.log 2>&1
```

---

## Step 12 — Enable Auto Security Updates

```bash
sudo apt install unattended-upgrades -y
sudo dpkg-reconfigure --priority=low unattended-upgrades
# Choose Yes
```

---

## Verification Checklist

After completing all steps, verify:

```bash
# All containers running
docker compose -f docker-compose.prod.yml ps

# App responds
curl -I https://yourdomain.com

# Horizon is processing jobs
docker compose -f docker-compose.prod.yml exec app php artisan horizon:status

# Firewall active
sudo ufw status

# Fail2ban active
sudo fail2ban-client status

# SSL certificate valid
echo | openssl s_client -connect yourdomain.com:443 2>/dev/null | openssl x509 -noout -dates
```

---

## Day-to-Day Commands

```bash
# Deploy a code update
./deploy.sh

# View live logs
docker compose -f docker-compose.prod.yml logs -f

# View only app logs
docker compose -f docker-compose.prod.yml logs -f app

# Run an artisan command
docker compose -f docker-compose.prod.yml exec app php artisan <command>

# Restart just the app (not DB/Redis)
docker compose -f docker-compose.prod.yml restart app

# Manual database backup
./backup.sh

# Check disk space
df -h

# Check server memory
free -h
```

---

## Moving to Another Provider

```bash
# 1. Backup database
./backup.sh

# 2. Copy backup to your machine
scp deploy@YOUR_IP:~/backups/db_LATEST.sql.gz ./

# 3. Set up new server (repeat this guide)

# 4. Copy backup to new server
scp db_LATEST.sql.gz deploy@NEW_IP:~/

# 5. After deploying on new server, restore
gunzip < db_LATEST.sql.gz | \
    docker exec -i $(docker ps -qf "name=pgsql") \
    psql -U myapp myapp

# 6. Update domain A record to new IP
```
