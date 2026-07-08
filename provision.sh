#!/usr/bin/env bash
#
# provision.sh — one-time setup for the Maison Des Bains VM.
#
# Prepares a fresh Ubuntu VM to receive deploys from GitHub Actions,
# mirroring the yuz VM layout: a `runner` user rsyncs the built site
# into /var/www/html, which Apache serves.
#
# Run ONCE on the VM as a user with sudo (e.g. the cloud default user):
#     scp provision.sh <you>@<VM_IP>:~   &&   ssh <you>@<VM_IP>
#     sudo bash provision.sh
#
# The deploy public key below is baked in (safe to publish). Its matching
# private key lives in the GitHub repo secret SSH_KEY.
# ---------------------------------------------------------------------------
set -euo pipefail

DEPLOY_PUBKEY='ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAVL50AAi+QyDsJ+0AsK20NfpP9djMC1FGYR9WC3Sndp maison-des-bains-deploy'
RUNNER_USER='runner'
WEBROOT='/var/www/html'

echo "==> Updating apt and installing Apache + rsync"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y apache2 rsync

echo "==> Enabling Apache modules (deflate, headers, expires)"
a2enmod deflate headers expires >/dev/null 2>&1 || true

echo "==> Creating deploy user '${RUNNER_USER}'"
if ! id -u "${RUNNER_USER}" >/dev/null 2>&1; then
  adduser --disabled-password --gecos "" "${RUNNER_USER}"
fi
usermod -aG www-data "${RUNNER_USER}"

echo "==> Installing deploy public key for '${RUNNER_USER}'"
install -d -m 700 -o "${RUNNER_USER}" -g "${RUNNER_USER}" "/home/${RUNNER_USER}/.ssh"
touch "/home/${RUNNER_USER}/.ssh/authorized_keys"
grep -qxF "${DEPLOY_PUBKEY}" "/home/${RUNNER_USER}/.ssh/authorized_keys" \
  || echo "${DEPLOY_PUBKEY}" >> "/home/${RUNNER_USER}/.ssh/authorized_keys"
chmod 600 "/home/${RUNNER_USER}/.ssh/authorized_keys"
chown "${RUNNER_USER}:${RUNNER_USER}" "/home/${RUNNER_USER}/.ssh/authorized_keys"

echo "==> Granting '${RUNNER_USER}' passwordless sudo for rsync + permission fixes"
cat > /etc/sudoers.d/maison-runner <<EOF
${RUNNER_USER} ALL=(root) NOPASSWD: /usr/bin/rsync, /usr/bin/find, /bin/chown, /bin/chmod, /usr/bin/chown, /usr/bin/chmod
EOF
chmod 440 /etc/sudoers.d/maison-runner
visudo -cf /etc/sudoers.d/maison-runner

echo "==> Preparing web root ${WEBROOT}"
mkdir -p "${WEBROOT}"
chown -R www-data:www-data "${WEBROOT}"

echo "==> Writing Apache vhost (static, gzip, long cache on assets)"
cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:80>
    DocumentRoot ${WEBROOT}
    DirectoryIndex index.html

    <Directory ${WEBROOT}>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>

    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/css application/javascript image/svg+xml
    </IfModule>

    <IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType text/css "access plus 7 days"
        ExpiresByType application/javascript "access plus 7 days"
        ExpiresByType image/svg+xml "access plus 30 days"
        ExpiresByType text/html "access plus 0 seconds"
    </IfModule>

    ErrorLog \${APACHE_LOG_DIR}/maison-error.log
    CustomLog \${APACHE_LOG_DIR}/maison-access.log combined
</VirtualHost>
EOF

echo "==> Placeholder page until first deploy"
if [ ! -f "${WEBROOT}/index.html" ]; then
  echo '<!doctype html><title>Maison Des Bains</title><body style="font-family:serif;background:#F6F3EE;color:#111110;display:grid;place-items:center;height:100vh;margin:0"><p>Maison Des Bains — awaiting first deploy.</p></body>' > "${WEBROOT}/index.html"
  chown www-data:www-data "${WEBROOT}/index.html"
fi

echo "==> Enabling site and restarting Apache"
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite 000-default >/dev/null 2>&1
systemctl enable apache2 >/dev/null 2>&1 || true
systemctl restart apache2

echo "==> Opening firewall (if ufw is active)"
if command -v ufw >/dev/null 2>&1; then
  ufw allow OpenSSH >/dev/null 2>&1 || true
  ufw allow 'Apache Full' >/dev/null 2>&1 || true
fi

echo ""
echo "✓ Provisioning complete."
echo "  Web root : ${WEBROOT}"
echo "  Deploy   : GitHub Actions rsyncs public/ here as user '${RUNNER_USER}'."
echo "  Verify   : curl -I http://localhost/  (should return 200)"
