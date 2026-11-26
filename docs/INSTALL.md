# Installing Erty

This guide sets up Erty on a Linux server. For a quick local try-out, the README's quick start is enough.

## 1. What you need

- PHP 8.3 or newer with PHP-FPM and the `pdo_pgsql`, `mbstring`, `gd`, `zip`, `xml`, `fileinfo` and `openssl`
  extensions
- PostgreSQL 14 or newer
- Composer 2
- A web server (nginx is shown below) and an HTTPS certificate
- An SMTP account Erty can send email from. Sign-in sends a 6-digit code by email, so email must work before
  anyone can sign in.

## 2. Get the code and dependencies

```bash
cd /var/www
git clone <repository-url> erty
cd erty
composer install --no-dev --optimize-autoloader
```

The web server user (often `www-data`) must be able to write to `storage/` and `bootstrap/cache/`:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
```

## 3. Create the database

```sql
CREATE USER erty WITH PASSWORD 'choose-a-strong-password';
CREATE DATABASE erty OWNER erty;
```

## 4. Configure `.env`

```bash
cp .env.example .env
```

Every setting is explained in `.env.example`. For production, set at least:

| Setting | Value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | The address people use, for example `https://erty.example.com` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | The database from step 3 |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Your SMTP account |
| `ADMIN_EMAIL` | The first administrator's email, a mailbox you can read |
| `BUSINESS_NAME` | Your business name |
| `ERTY_CURRENCY`, `CURRENCY_SYMBOL` | Your currency, for example `KES` and `KSh` |
| `SESSION_SECURE_COOKIE` | `true` once Erty is served over HTTPS |
| `NOTIFICATION_EMAIL` | Where security, account-deletion and backup alerts go (optional) |

`.env` holds passwords: keep it out of version control and readable only by the web server user.

## 5. Install

```bash
php artisan erty:install --force
```

This generates the application key if it is missing, creates the tables, loads the reference data (roles,
property types, task templates, expense and document types, support topics), links `public/storage` and creates
the first administrator. **The administrator's password is printed once.** Copy it, sign in, enter the emailed
code, then choose your own password.

Then cache the configuration, routes and views:

```bash
php artisan optimize
```

Run `php artisan optimize` again after every change to `.env` or the code.

## 6. Web server (nginx)

Point the site at the `public` directory:

```nginx
server {
    listen 443 ssl http2;
    server_name erty.example.com;
    root /var/www/erty/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/erty.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/erty.example.com/privkey.pem;

    client_max_body_size 25m;   # uploads go up to 20 MB

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}

server {
    listen 80;
    server_name erty.example.com;
    return 301 https://$host$request_uri;
}
```

Raise `upload_max_filesize` and `post_max_size` in PHP to at least `20M` as well.

## 7. Scheduler

Erty bills rent every night, reminds agents about overdue tasks every hour, works out period commission
each month, removes old activity log entries and makes a backup every night. Add one cron entry for the web server user:

```cron
* * * * * cd /var/www/erty && php artisan schedule:run >> /dev/null 2>&1
```

`php artisan schedule:list` shows what runs and when.

## 8. Queue (optional)

With `QUEUE_CONNECTION=sync` (the default), emails are sent while the page waits. To send them in the
background, set `QUEUE_CONNECTION=database` and keep a worker running, for example with systemd or Supervisor:

```bash
php artisan queue:work --tries=3
```

## 9. Backups

`backup:run` (nightly at 01:30) zips a database dump, the uploaded files and `.env` to
`storage/app/<APP_NAME>/`, and `backup:clean` (01:00) removes old ones. The code itself comes from the
repository. Backups contain your passwords (in `.env`) and tenants' documents: set `BACKUP_ARCHIVE_PASSWORD` to
encrypt them, and copy them off the server regularly, or configure an S3 disk in `config/backup.php`. The
server needs `pg_dump`, which comes with the PostgreSQL client.

## 10. Health checks

`GET /health` answers `{"healthy": true}` with status 200 when the database answers and storage is writable,
and 503 otherwise: point a load balancer or uptime monitor at it. `php artisan health:check` shows the detailed
checks (disk space, database size, cache and more).

## Upgrading

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force      # adds any new reference data; your own changes are kept
php artisan erty:seed-roles      # offers new starter roles to existing businesses
php artisan optimize
php artisan up
```

Installations from before the baseline migrations upgrade the same way. The baseline skips tables that already
exist, and its last migration removes the tables and data of features Erty no longer has. Back up the database
before the first upgrade.

## Optional modules

Listings and Sales can be switched off for the whole installation (`php artisan erty:module sales off`), and a
business owner can switch them off for their own business under Account settings → Modules.
