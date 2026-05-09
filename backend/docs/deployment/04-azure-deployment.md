# Azure Deployment Guide (Free Tier)

**Cost:** Free for 12 months, then ~€15/mo
**Free tier includes:** B1s VM (1 vCPU, 1GB RAM), 64GB disk, static IP
**Prerequisites:** Azure account (credit card for identity verification only, not charged within limits)

---

## Azure Free Tier Limits to Know

| Resource | Free Allowance |
|---|---|
| Virtual Machine B1s | 750 hours/month (runs 24/7) |
| Standard HDD disk | 64GB |
| Public IP address | Included |
| Data outbound | 15GB/month |

**Important:** The B1s has only 1GB RAM. Add swap space (see Step 5) — it's essential.
For a production SaaS with significant workflow load, use a B2s (2GB RAM) — this is NOT free but costs ~€8/mo.

---

## Step 1 — Create the Virtual Machine

1. Go to [portal.azure.com](https://portal.azure.com)
2. Search **Virtual Machines** → **Create** → **Azure virtual machine**

Fill in:

| Setting | Value |
|---|---|
| Resource Group | Create new: `myapp-rg` |
| VM Name | `myapp-server` |
| Region | Closest to your users |
| Image | Ubuntu Server 24.04 LTS |
| Size | **B1s** (free) or B2s (recommended for prod) |
| Authentication | SSH public key |
| Username | `deploy` |
| SSH key | Paste your `~/.ssh/id_rsa.pub` content |

3. Under **Disks** — Standard HDD (free tier)
4. Under **Networking** — Allow ports: SSH (22), HTTP (80), HTTPS (443)
5. Click **Review + Create** → **Create**
6. Note your **Public IP address** from the VM overview page

---

## Step 2 — First Login

```bash
ssh deploy@YOUR_AZURE_IP
```

---

## Step 3 — Update System & Harden SSH

```bash
sudo apt update && sudo apt upgrade -y

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

---

## Step 4 — Firewall

Azure has its own Network Security Group firewall (configured in the portal).
Also enable the OS-level firewall:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

---

## Step 5 — Swap Space (Critical on B1s 1GB RAM)

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

Without this, Docker builds will fail on 1GB RAM.

---

## Step 6 — Install Fail2ban

```bash
sudo apt install fail2ban -y
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

---

## Step 7 — Install Docker

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker deploy
sudo apt install docker-compose-plugin -y

# Log out and back in
exit
ssh deploy@YOUR_AZURE_IP
```

---

## Step 8 — Deploy Your Application

Same as Hetzner (Step 8 in `03-hetzner-deployment.md`):

```bash
git clone https://github.com/YOUR_USERNAME/YOUR_REPO.git
cd YOUR_REPO
cp .env.production.example .env
nano .env
chmod +x deploy.sh
./deploy.sh
```

---

## Step 9 — Domain + SSL

Same process as Hetzner. Point your domain A record to your Azure public IP,
then run certbot for free SSL.

---

## Azure-Specific Considerations

### Static IP Address
By default, Azure VMs get a dynamic IP that changes on reboot.
To fix this: in Azure Portal → Your VM → Networking → Public IP → Change to **Static**.
This is important so your domain keeps pointing to the right IP.

### Auto-Shutdown (Avoid This)
Azure sometimes suggests enabling auto-shutdown on VMs. **Disable it** for production.
Check: VM → Operations → Auto-shutdown → Off.

### Cost Alerts
Set a budget alert so you're notified before hitting paid usage:
Portal → Subscriptions → Budgets → Create ($10 budget with 80% alert).

### After 12 Months
When the free tier expires:
- You'll be charged for the VM (~€6–15/mo depending on size)
- Or you can move to Hetzner (same Docker setup, no code changes needed)
- Export your database and files, set up Hetzner, update DNS — done in 2 hours

---

## Comparison: Azure B1s vs Hetzner CX22

| | Azure B1s (Free) | Hetzner CX22 (€3.79/mo) |
|---|---|---|
| vCPU | 1 | 2 |
| RAM | 1GB | 4GB |
| Storage | 64GB HDD | 40GB SSD |
| Network | 15GB/mo outbound | 20TB/mo included |
| Best for | Testing / Year 1 | Production |

For serious workflow processing, Hetzner CX22 is significantly better value.
