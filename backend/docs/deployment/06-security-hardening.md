# Security Hardening Guide

A production SaaS platform must be secured at every layer. This document covers the complete
security checklist — server level, network level, application level, and secrets management.

---

## Layer 1 — Server Security

### SSH Hardening
```bash
sudo nano /etc/ssh/sshd_config
```

Set these values:
```
PermitRootLogin no              # Never login as root
PasswordAuthentication no       # SSH key only, no passwords
PubkeyAuthentication yes
MaxAuthTries 3                  # Lock out after 3 wrong key attempts
ClientAliveInterval 300         # Disconnect idle sessions after 5 min
ClientAliveCountMax 2
X11Forwarding no
AllowTcpForwarding no
```

```bash
sudo systemctl restart sshd
```

### Firewall — UFW
```bash
# Only allow what's needed
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

Database (5432) and Redis (6379) ports are **never** exposed to the internet.
They are only accessible within the internal Docker network.

### Fail2ban — Block Brute Force
```bash
sudo apt install fail2ban -y
```

Create `/etc/fail2ban/jail.local`:
```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
destemail = your@email.com

[sshd]
enabled = true
port = ssh
maxretry = 3
bantime = 86400
```

```bash
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### Automatic Security Updates
```bash
sudo apt install unattended-upgrades -y
sudo dpkg-reconfigure --priority=low unattended-upgrades
```
Choose **Yes** — security patches apply automatically without rebooting.

---

## Layer 2 — Docker Security

### Container Network Isolation
Your `docker-compose.prod.yml` creates an internal Docker network.
PostgreSQL and Redis are only reachable by the app container — not from the internet.

Verify no database port is exposed externally:
```bash
# This should show nothing for ports 5432 or 6379
sudo ss -tlnp | grep -E '5432|6379'
```

### Run Containers as Non-Root
In your Dockerfile, the app runs as `www-data` (not root).
Docker itself runs as root on the host, but containers are isolated.

### Keep Images Updated
```bash
# Pull latest base images monthly
docker compose -f docker-compose.prod.yml pull
./deploy.sh
```

### Limit Container Resources (Prevent One Container Starving Others)
Add to each service in `docker-compose.prod.yml`:
```yaml
deploy:
    resources:
        limits:
            cpus: '1.5'
            memory: 1G
```

---

## Layer 3 — Application Security

### Environment Variables
- All secrets live in `.env` on the server — never in code or Git
- `.env` is in `.gitignore` — never committed
- Use strong random values for `DB_PASSWORD`, `REDIS_PASSWORD`, `APP_KEY`

Generate a strong password:
```bash
openssl rand -base64 32
```

### Laravel Security Settings
In `.env`:
```env
APP_ENV=production
APP_DEBUG=false          # CRITICAL — never true in production (exposes stack traces)
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
```

### HTTPS / SSL
- Use Let's Encrypt (free) for SSL certificates
- Force HTTPS redirect — add to `nginx.conf`:

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    # ... rest of config
}
```

### Security Headers (in nginx.conf)
```nginx
add_header X-Frame-Options "SAMEORIGIN";
add_header X-Content-Type-Options "nosniff";
add_header X-XSS-Protection "1; mode=block";
add_header Referrer-Policy "strict-origin-when-cross-origin";
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

### Rate Limiting (Prevent API Abuse)
In `nginx.conf`:
```nginx
http {
    limit_req_zone $binary_remote_addr zone=api:10m rate=60r/m;

    server {
        location /api/ {
            limit_req zone=api burst=20 nodelay;
        }
    }
}
```

### Webhook Security
Your workflow triggers use signed webhooks. Ensure:
- All incoming webhooks verify the signature before processing
- Webhook secrets are stored as encrypted credentials in the database
- Use `hash_equals()` for signature comparison (prevents timing attacks)

---

## Layer 4 — Database Security

### Credentials
- Use a long random password (32+ characters)
- Never use `root` or simple passwords
- Create a dedicated DB user with only the permissions it needs:

```sql
-- Run this after initial setup
REVOKE ALL ON ALL TABLES IN SCHEMA public FROM myapp;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO myapp;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO myapp;
```

### Connection Encryption
PostgreSQL connections within Docker are over the internal network (trusted).
If you ever expose PostgreSQL externally, enable SSL in `postgresql.conf`.

### Regular Backups
See `06-backup-strategy.md` — your data is the most valuable asset.

---

## Security Checklist

Run through this after every deployment:

| Check | Command | Expected |
|---|---|---|
| Root login disabled | `sudo grep PermitRootLogin /etc/ssh/sshd_config` | `no` |
| Password auth disabled | `sudo grep PasswordAuthentication /etc/ssh/sshd_config` | `no` |
| Firewall active | `sudo ufw status` | Active |
| Fail2ban running | `sudo fail2ban-client status` | Running |
| APP_DEBUG off | `grep APP_DEBUG .env` | `false` |
| HTTPS working | `curl -I https://yourdomain.com` | 200 OK |
| DB port not exposed | `sudo ss -tlnp \| grep 5432` | No output |
| Redis port not exposed | `sudo ss -tlnp \| grep 6379` | No output |
| SSL certificate valid | `certbot certificates` | Days until expiry > 30 |
