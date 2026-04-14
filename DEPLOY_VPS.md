# VPS Deployment Guide

This app is ready to deploy on a Linux VPS running PHP 8.2+ and MySQL 8+. The safest first production setup is:
- Ubuntu 24.04 LTS
- Nginx or Apache
- PHP-FPM 8.3
- MySQL 8 or managed MySQL
- HTTPS via Let's Encrypt

## 1. Server prerequisites

Install packages:
- git
- unzip
- nginx or apache2
- php8.3, php8.3-fpm, php8.3-mysql, php8.3-mbstring, php8.3-xml, php8.3-curl, php8.3-zip, php8.3-intl, php8.3-gd
- composer
- mysql-client

CodeIgniter/Dompdf also benefits from:
- php8.3-opcache
- fonts-liberation

## 2. App directory layout

Recommended app path:
- /var/www/jt-rider-app

Only expose this path to the web server:
- /var/www/jt-rider-app/public

Do not expose:
- app/
- writable/
- vendor/
- tests/
- env
- .env

## 3. Deploy the code

Example:
```bash
cd /var/www
sudo git clone https://github.com/DonDon14/jt-rider-remittance-payroll.git jt-rider-app
cd /var/www/jt-rider-app
composer install --no-dev --optimize-autoloader
```

## 4. Production environment

Create `.env` from `.env.production.example`:
```bash
cp .env.production.example .env
```

Fill these values:
- `CI_ENVIRONMENT=production`
- `app.baseURL=https://your-domain.example/`
- database host/name/user/password
- `encryption.key`
- `auth.bootstrapAdminPassword` before first migrate only
- `auth.apiTokenTtlHours` to control mobile login lifetime
- `auth.adminRecoveryKey` for admin forgot-password recovery

Recommended extra lines:
```ini
app.indexPage = ''
session.cookieSecure = true
session.cookieSameSite = Lax
```

## 5. Writable permissions

Make only `writable/` writable by the web server user:
```bash
sudo chown -R www-data:www-data writable
sudo chmod -R 775 writable
```

## 6. Database migration

Run this once production env is configured:
```bash
php spark migrate --all
```

Then log in with the bootstrap admin password, rotate it immediately, and remove or change `auth.bootstrapAdminPassword`.

## 7. Web server config

Use one of the provided config samples:
- Nginx: `deploy-tools/nginx/jt-rider-app.conf`
- Apache: `deploy-tools/apache/jt-rider-app.conf`

After enabling the site, reload the web server.

## 8. HTTPS

Use Let's Encrypt after the site resolves publicly:
```bash
sudo certbot --nginx -d your-domain.example -d www.your-domain.example
```
Or for Apache:
```bash
sudo certbot --apache -d your-domain.example -d www.your-domain.example
```

## 9. Post-deploy checks

Verify these flows on the live server:
1. Admin login
2. Rider login
3. Rider delivery submission with remittance account
4. Admin approval
5. Pending remittance collection
6. Split cash/GCash remittance save
7. Payroll generation
8. Payroll release
9. Rider receipt confirmation
10. PDF downloads

## 10. Mobile apps after deployment

After the VPS is live, rebuild both APKs with the production API URL.
Do not rely on the current fallback development URL for release builds.

Rider app example:
```bash
flutter build apk --release --dart-define=API_BASE_URL=https://your-domain.example/api
```

Admin app example:
```bash
flutter build apk --release --dart-define=API_BASE_URL=https://your-domain.example/api
```

## 11. Operational recommendations

- Use a non-root MySQL user
- Back up the database before each deploy
- Keep `writable/logs` monitored
- Run `php vendor/bin/phpunit` before pushing production updates
- Tag production releases in git
- Do not keep `.env` in version control

