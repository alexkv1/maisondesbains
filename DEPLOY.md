# Maison Des Bains — Deployment

A static site (HTML/CSS/JS) served by **Apache** from `/var/www/html` on a VM,
deployed via **GitHub Actions on push to `main`** — the same pattern as the
`yuz` repos, minus PHP/DB/composer since there's no backend.

```
push to main ──▶ GitHub Actions ──▶ rsync public/ ──▶ runner@VM:/home/runner/maison
                                                   └▶ sudo rsync ──▶ /var/www/html  (Apache)
```

## Layout

| Path                        | Purpose                                              |
|-----------------------------|------------------------------------------------------|
| `public/`                   | The deployable site (this is what ends up in webroot)|
| `.github/workflows/main.yaml` | The deploy pipeline                                |
| `provision.sh`              | One-time VM setup (Apache, `runner` user, sudoers)   |

## One-time setup

### 1. Provision the VM (once)

Copy the script up and run it as a sudo-capable user on the fresh VM:

```bash
scp provision.sh <you>@<VM_IP>:~
ssh <you>@<VM_IP> 'sudo bash provision.sh'
```

This installs Apache, creates the `runner` deploy user, installs the deploy
public key, grants `runner` passwordless sudo for `rsync`, and serves
`/var/www/html`. Verify: `curl -I http://<VM_IP>/` → `200`.

### 2. Create the GitHub repo + secrets

```bash
gh auth login                                   # if not already
gh repo create vinder-ab/maison-des-bains --private --source=. --remote=origin

gh secret set SSH_KEY  < ~/.ssh/maison-runner   # the PRIVATE deploy key
gh secret set MAIN_IP  --body "<VM_IP>"
```

The matching **public** key is baked into `provision.sh` (safe to publish);
the **private** key `~/.ssh/maison-runner` is held only as the `SSH_KEY` secret.

### 3. Deploy

```bash
git push origin main
```

Every push to `main` re-runs the workflow. Watch it with `gh run watch`.

## Deploy keypair

- Private: `~/.ssh/maison-runner`  → GitHub secret `SSH_KEY`
- Public:  `~/.ssh/maison-runner.pub` → baked into `provision.sh`

## TLS (optional, after DNS points at the VM)

```bash
ssh <you>@<VM_IP> 'sudo apt-get install -y certbot python3-certbot-apache && sudo certbot --apache'
```
