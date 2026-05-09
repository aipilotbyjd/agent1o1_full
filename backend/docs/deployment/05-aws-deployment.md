# AWS Deployment Guide

**Free Tier:** 12 months free on select services
**Best for:** Teams already in the AWS ecosystem, or needing AWS-specific integrations
**Prerequisites:** AWS account (credit card required for identity — not charged within free limits)

---

## AWS Free Tier What You Get (12 Months)

| Service | Free Allowance | Used For |
|---|---|---|
| EC2 t2.micro or t3.micro | 750 hours/month | App server |
| EBS Storage | 30GB SSD | Server disk |
| Data Transfer Out | 100GB/month | Traffic |
| Elastic IP | 1 free (while attached) | Static IP |

**What AWS does NOT offer free:**
- Managed RDS PostgreSQL (costs ~$15/mo minimum)
- Managed ElastiCache Redis (costs ~$12/mo minimum)

For this reason, the recommended approach is to run PostgreSQL and Redis **inside Docker on the EC2 instance** — same as the Hetzner setup — rather than using managed AWS services. This keeps costs zero and the setup portable.

---

## Step 1 — Create an EC2 Instance

1. Sign in to [aws.amazon.com/console](https://aws.amazon.com/console)
2. Search **EC2** → **Launch Instance**

Fill in:

| Setting | Value |
|---|---|
| Name | `myapp-server` |
| AMI | **Ubuntu Server 24.04 LTS** |
| Instance Type | **t3.micro** (free tier eligible) |
| Key Pair | Create new → download the `.pem` file, keep it safe |
| Storage | 30GB gp2 SSD (free tier max) |

3. Under **Network Settings** → **Edit**:
   - Create a new Security Group named `myapp-sg`
   - Add inbound rules:
     - SSH — Port 22 — Source: My IP (more secure than Anywhere)
     - HTTP — Port 80 — Source: Anywhere (0.0.0.0/0)
     - HTTPS — Port 443 — Source: Anywhere (0.0.0.0/0)

4. Click **Launch Instance**
5. Go to **Instances** → wait for status to show **Running**
6. Note the **Public IPv4 address**

---

## Step 2 — Assign a Static IP (Elastic IP)

By default, AWS EC2 instances get a new IP on every restart.

1. EC2 Console → **Elastic IPs** (left sidebar) → **Allocate Elastic IP**
2. Click **Allocate**
3. Select the new IP → **Actions** → **Associate Elastic IP**
4. Associate it with your EC2 instance
5. Your server now has a permanent IP — note it

---

## Step 3 — First Login

Your `.pem` key file needs correct permissions before use:

```bash
chmod 400 ~/Downloads/your-key.pem

ssh -i ~/Downloads/your-key.pem ubuntu@YOUR_ELASTIC_IP
```

AWS Ubuntu instances use `ubuntu` as the default user (not `root`).

---

## Step 4 — Create Deploy User & Harden SSH

```bash
# Create deploy user
sudo adduser deploy
sudo usermod -aG sudo deploy

# Copy your SSH key to the new user
sudo rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy

# Harden SSH
sudo nano /etc/ssh/sshd_config
```

Set:
```
PermitRootLogin no
PasswordAuthentication no
MaxAuthTries 3
```

```bash
sudo systemctl restart sshd
```

From now on, connect as:
```bash
ssh -i ~/Downloads/your-key.pem deploy@YOUR_ELASTIC_IP
```

---

## Step 5 — Configure Firewall

AWS has Security Groups (configured in the console) which already block unwanted ports.
Also enable the OS-level firewall:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

---

## Step 6 — Install Fail2ban

```bash
sudo apt install fail2ban -y
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

---

## Step 7 — Add Swap Space (Essential on t3.micro 1GB RAM)

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

Without this, Docker image builds will fail on 1GB RAM instances.

---

## Step 8 — Install Docker

```bash
sudo apt update && sudo apt upgrade -y
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker deploy
sudo apt install docker-compose-plugin -y

# Log out and back in for group membership to take effect
exit
ssh -i ~/Downloads/your-key.pem deploy@YOUR_ELASTIC_IP
```

---

## Step 9 — Deploy Your Application

```bash
git clone https://github.com/YOUR_USERNAME/YOUR_REPO.git
cd YOUR_REPO

cp .env.production.example .env
nano .env
```

Fill in `.env` values (see `02-docker-configuration.md` for the full template).

Generate your `APP_KEY`:
```bash
docker run --rm php:8.2-cli php -r \
    "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Run the deployment:
```bash
chmod +x deploy.sh
./deploy.sh
```

---

## Step 10 — Point Domain to Elastic IP

In your domain registrar, add an **A record**:
- Type: `A`
- Name: `@` and `www`
- Value: `YOUR_ELASTIC_IP`
- TTL: 300

Wait 5–30 minutes for DNS propagation.

---

## Step 11 — Free SSL Certificate

```bash
sudo apt install certbot -y

# Stop app to free port 80
docker compose -f docker-compose.prod.yml down

# Get certificate
sudo certbot certonly --standalone \
    -d yourdomain.com \
    -d www.yourdomain.com \
    --non-interactive \
    --agree-tos \
    --email your@email.com

# Restart app
docker compose -f docker-compose.prod.yml up -d
```

Update `.env`:
```env
APP_URL=https://yourdomain.com
```

Redeploy:
```bash
./deploy.sh
```

**Auto-renew SSL:**
```bash
sudo crontab -e
```
Add:
```
0 3 1 * * certbot renew --pre-hook "docker compose -f /home/deploy/YOUR_REPO/docker-compose.prod.yml down" --post-hook "docker compose -f /home/deploy/YOUR_REPO/docker-compose.prod.yml up -d"
```

---

## Step 12 — Set Up Automatic Backups

```bash
chmod +x backup.sh
mkdir -p ~/backups

# Test it
./backup.sh

# Schedule daily at 2am
crontab -e
```
Add:
```
0 2 * * * /home/deploy/YOUR_REPO/backup.sh >> /home/deploy/backups/backup.log 2>&1
```

For offsite backups, AWS S3 is a natural fit since you're already in AWS:

1. Create an S3 bucket (e.g., `myapp-backups`) in the AWS Console
2. Create an IAM user with `AmazonS3FullAccess` on that bucket only
3. Get the Access Key ID and Secret Access Key
4. Configure rclone:

```bash
rclone config
# Type: s3
# Provider: AWS
# Access Key ID: YOUR_KEY_ID
# Secret Access Key: YOUR_SECRET
# Region: same region as your EC2
```

In `.env`:
```env
BACKUP_OFFSITE_ENABLED=true
BACKUP_OFFSITE_DEST=s3:myapp-backups/db
BACKUP_KEEP_DAYS=30
```

**S3 cost:** First 5GB free for 12 months, then ~$0.023/GB/month. Compressed database backups are very small — likely under 1GB for months.

---

## Step 13 — Enable Auto Security Updates

```bash
sudo apt install unattended-upgrades -y
sudo dpkg-reconfigure --priority=low unattended-upgrades
```

---

## AWS-Specific Considerations

### Free Tier Monitoring
Set a billing alert to avoid surprise charges:
1. AWS Console → **Billing** → **Budgets** → **Create budget**
2. Type: Cost budget, Amount: $5
3. Alert at 80% → Email notification

### Elastic IP Billing
An Elastic IP is **free only while attached** to a running instance.
If you stop your instance, the Elastic IP starts being charged (~$0.005/hour).
Either keep the instance running or release the IP when not in use.

### Data Transfer Costs (After Free Tier)
AWS charges for outbound data transfer after 100GB/month free.
If your app sends a lot of data (file downloads, large API responses), monitor this.
Beyond free tier: ~$0.09/GB outbound.

### EC2 Instance Connect (Alternative to SSH Key)
AWS also offers browser-based SSH via the console:
EC2 → Select your instance → **Connect** → **EC2 Instance Connect** → **Connect**
Useful if you lose your `.pem` key file.

---

## After 12 Months (Avoiding Charges)

When your free tier expires:

**Option A — Keep on AWS (paid):**
- t3.micro: ~$8/mo
- Elastic IP: included while attached
- Total: ~$8–10/mo

**Option B — Move to Hetzner (cheaper):**
- Same Docker setup, no code changes
- Export database, provision Hetzner CX22 (€3.79/mo), restore database
- Update domain A record to new IP
- Total migration time: ~2 hours

---

## Provider Comparison

| | AWS t3.micro (Free) | Azure B1s (Free) | Hetzner CX22 (Paid) |
|---|---|---|---|
| vCPU | 2 (burstable) | 1 | 2 |
| RAM | 1GB | 1GB | 4GB |
| Storage | 30GB SSD | 64GB HDD | 40GB SSD |
| Free period | 12 months | 12 months | No free tier |
| After free | ~$8/mo | ~$6/mo | €3.79/mo |
| Best for | AWS ecosystem | Microsoft ecosystem | Best value production |
