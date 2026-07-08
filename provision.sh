#!/usr/bin/env bash
#
# provision.sh — one-time setup for the Maison Des Bains VM.
#
# Prepares a fresh Ubuntu VM to serve the PHP/MySQL webshop and receive
# deploys from GitHub Actions (mirrors the yuz VM layout): a `runner` user
# rsyncs the site into /var/www/html, Apache + PHP serve it, MySQL backs it.
#
# Run ONCE on the VM as a sudo-capable user:  sudo bash provision.sh
# ---------------------------------------------------------------------------
set -euo pipefail

DEPLOY_PUBKEY='ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAVL50AAi+QyDsJ+0AsK20NfpP9djMC1FGYR9WC3Sndp maison-des-bains-deploy'
RUNNER_USER='runner'
WEBROOT='/var/www/html'
DB_NAME='maison_des_bains'
DB_USER='maison'
# Set a strong password before running, or export DB_PASS in the environment.
DB_PASS="${DB_PASS:-change-this-password}"

echo "==> Installing Apache, PHP, MySQL, rsync, composer"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y apache2 rsync mysql-server \
    php libapache2-mod-php php-mysql php-mbstring php-curl php-xml unzip curl composer

echo "==> Enabling Apache modules"
a2enmod deflate headers expires rewrite php* >/dev/null 2>&1 || true

echo "==> Creating deploy user '${RUNNER_USER}'"
if ! id -u "${RUNNER_USER}" >/dev/null 2>&1; then
  adduser --disabled-password --gecos "" "${RUNNER_USER}"
fi
usermod -aG www-data "${RUNNER_USER}"

echo "==> Installing deploy public key"
install -d -m 700 -o "${RUNNER_USER}" -g "${RUNNER_USER}" "/home/${RUNNER_USER}/.ssh"
touch "/home/${RUNNER_USER}/.ssh/authorized_keys"
grep -qxF "${DEPLOY_PUBKEY}" "/home/${RUNNER_USER}/.ssh/authorized_keys" \
  || echo "${DEPLOY_PUBKEY}" >> "/home/${RUNNER_USER}/.ssh/authorized_keys"
chmod 600 "/home/${RUNNER_USER}/.ssh/authorized_keys"
chown "${RUNNER_USER}:${RUNNER_USER}" "/home/${RUNNER_USER}/.ssh/authorized_keys"

echo "==> Passwordless sudo for the deploy user (writes conf.php, runs composer)"
echo "${RUNNER_USER} ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/maison-runner
chmod 440 /etc/sudoers.d/maison-runner
visudo -cf /etc/sudoers.d/maison-runner

echo "==> Configuring Apache vhost (AllowOverride so .htaccess routing works)"
cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:80>
    DocumentRoot ${WEBROOT}
    DirectoryIndex index.php index.html
    <Directory ${WEBROOT}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/maison-error.log
    CustomLog \${APACHE_LOG_DIR}/maison-access.log combined
</VirtualHost>
EOF
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite 000-default >/dev/null 2>&1

echo "==> Preparing web root"
mkdir -p "${WEBROOT}"
chown -R www-data:www-data "${WEBROOT}"

echo "==> Creating database + user"
systemctl enable --now mysql >/dev/null 2>&1 || systemctl enable --now mysqld >/dev/null 2>&1 || true
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "==> Importing schema (if schema.sql is present next to this script)"
if [ -f "$(dirname "$0")/public/schema.sql" ]; then
  mysql "${DB_NAME}" < "$(dirname "$0")/public/schema.sql"
elif [ -f "$(dirname "$0")/schema.sql" ]; then
  mysql "${DB_NAME}" < "$(dirname "$0")/schema.sql"
else
  echo "    (schema.sql not found locally — import it after the first deploy:"
  echo "     mysql ${DB_NAME} < ${WEBROOT}/schema.sql)"
fi

echo "==> Enabling + restarting services"
systemctl enable apache2 >/dev/null 2>&1 || true
systemctl restart apache2

if command -v ufw >/dev/null 2>&1; then
  ufw allow OpenSSH >/dev/null 2>&1 || true
  ufw allow 'Apache Full' >/dev/null 2>&1 || true
fi

echo ""
echo "✓ Provisioning complete."
echo "  DB name  : ${DB_NAME}   user: ${DB_USER}"
echo "  GitHub secrets to set: DB_HOST=127.0.0.1 DB_USER=${DB_USER} DB_PASS=<the password> DB_NAME=${DB_NAME}"
echo "  Verify   : curl -I http://localhost/"
