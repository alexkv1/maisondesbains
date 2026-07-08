# Maison Des Bains — Deployment

A PHP/MySQL webshop served by **Apache** from `/var/www/html`, deployed via
**GitHub Actions on push to `main`** — the same pattern as the `yuz` repos.

```
push to main ─▶ GitHub Actions ─▶ rsync public/ ─▶ runner@VM:/home/runner/maison
                                               └▶ sudo rsync ─▶ /var/www/html
                                               └▶ write conf.php (from secrets)
                                               └▶ composer install (if Stripe configured)
```

## Layout

| Path                          | Purpose                                             |
|-------------------------------|-----------------------------------------------------|
| `public/`                     | Web root: PHP app + assets (deploys to /var/www/html)|
| `public/db.php`               | mysqli `DB` wrapper (reads conf.php)                 |
| `public/api/…`                | JSON endpoints: account, cart, checkout, orders, payments |
| `public/pages/…`              | product, cart, checkout, account, order, login      |
| `public/schema.sql`           | MySQL schema + seed catalogue                        |
| `.github/workflows/main.yaml` | The deploy pipeline                                  |
| `provision.sh`                | One-time VM setup (Apache, PHP, MySQL, runner user)  |

`conf.php` and `vendor/` are **not** committed — `conf.php` is written on the VM
by the deploy from repo secrets; `vendor/` is built there by composer.

## One-time VM setup

Run `provision.sh` once (installs Apache+PHP+MySQL, creates the `runner` user
and deploy key, creates the DB, imports the schema). Set a DB password first:

```bash
export DB_PASS='a-strong-password'
sudo -E bash provision.sh
```

## GitHub secrets

At **Settings → Secrets and variables → Actions**, set:

| Secret | Value |
|--------|-------|
| `SSH_KEY` | private deploy key (`~/.ssh/maison-runner`) |
| `MAIN_IP` | the VM's public IP |
| `DB_HOST` | `127.0.0.1` |
| `DB_USER` | `maison` |
| `DB_PASS` | the DB password you chose |
| `DB_NAME` | `maison_des_bains` |
| `STRIPE_KEY` | Stripe secret key — **leave empty to use mock checkout** |
| `STRIPE_WEBHOOK_SECRET` | Stripe webhook signing secret (optional) |

## Deploy

```bash
git push origin main      # or re-run the workflow from the Actions tab
```

## Schema changes (IMPORTANT)

`schema.sql` uses `CREATE TABLE IF NOT EXISTS`, which will **not** add new
columns to an existing table. So whenever `schema.sql` changes (new column,
prices, stock, products, images), rebuild the catalogue/cart/order tables on
the VM after the deploy. This is safe at this stage — `users` and `sessions`
(accounts) are preserved; only test catalogue/cart/order data is rebuilt:

```bash
mysql maison_des_bains -e "DROP TABLE IF EXISTS cart_items, order_items, product_variants, products, carts, orders;"
mysql maison_des_bains < /var/www/html/schema.sql
```

PHP/CSS/JS/image-only changes need no SQL — they go live when the Action finishes.

## Payments

- **No `STRIPE_KEY`** → the built-in **mock checkout** places the order, marks it
  paid, and redirects to the confirmation page. The whole flow is demoable.
- **With `STRIPE_KEY`** → checkout creates a Stripe Checkout Session and redirects
  to Stripe. Add a webhook in the Stripe dashboard pointing at
  `https://<host>/api/payments/webhook` (event `checkout.session.completed`) and
  set `STRIPE_WEBHOOK_SECRET`. Composer pulls `stripe/stripe-php` automatically.

## Local development

```bash
mysql -u root -e "CREATE DATABASE maison_des_bains"
mysql -u root maison_des_bains < public/schema.sql
cp public/conf.sample.php public/conf.php      # edit credentials
php -S 127.0.0.1:8080 -t public public/dev-router.php
```

## TLS (after DNS points at the VM)

```bash
sudo apt-get install -y certbot python3-certbot-apache && sudo certbot --apache
```
