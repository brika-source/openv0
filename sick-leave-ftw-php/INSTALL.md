# Installation and deployment

## Requirements

- **PHP 8.1 or newer** (developed and tested on 8.4)
- One PDO driver: `pdo_sqlite` (default, bundled with PHP), `pdo_mysql`, or `pdo_pgsql`
- Standard extensions: `json`, `mbstring`, `session`, `fileinfo`
- A web server, or PHP's built-in server for evaluation

No Composer, no `vendor/` directory, no build step, no external packages.

Check what you have:

```bash
php -v
php -m | grep -E 'pdo_sqlite|pdo_mysql|pdo_pgsql|mbstring|fileinfo|json|session'
```

---

## 1. Fastest path — built-in server, SQLite

From the project root:

```bash
php bin/console.php install
php -S localhost:8000 -t public
```

Open <http://localhost:8000>. Demo password for every account: `Passw0rd!`.

`install` prints the full account list. It is safe to re-run — it will not
overwrite an existing populated database.

---

## 2. Apache

Point a virtual host at the **`public/`** directory. Nothing above it should be
web-reachable.

```apache
<VirtualHost *:80>
    ServerName sickleave.example.com
    DocumentRoot /var/www/sickleave/public

    <Directory /var/www/sickleave/public>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/sickleave-error.log
    CustomLog ${APACHE_LOG_DIR}/sickleave-access.log combined
</VirtualHost>
```

The application routes through `index.php` with query-string parameters, so
**no rewrite rules are required**. The bundled `public/.htaccess` only adds
hardening. `.htaccess` files in `app/`, `config/`, `storage/`, `resources/`,
`database/`, `bin/` and `tests/` deny access outright, in case a document root
is ever misconfigured to the project root.

Then:

```bash
cd /var/www/sickleave
php bin/console.php install
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage
```

### Serving from a subdirectory

If the app lives at `https://example.com/sickleave/`, set the base path so URLs
are generated correctly:

```apache
SetEnv APP_BASE_PATH /sickleave
```

---

## 3. nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name sickleave.example.com;
    root /var/www/sickleave/public;
    index index.php;

    # Everything is served by the front controller.
    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        include        fastcgi_params;
        fastcgi_pass   unix:/run/php/php8.3-fpm.sock;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;

        # Optional: production settings
        fastcgi_param  APP_DEBUG      false;
        fastcgi_param  APP_DEMO_MODE  false;
        fastcgi_param  SESSION_SECURE true;
    }

    # Never serve dotfiles.
    location ~ /\. { deny all; }
}
```

Because the document root is `public/`, the database, uploads and application
code are already outside the served tree.

---

## 4. MySQL or MariaDB

Create the database first:

```sql
CREATE DATABASE slms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'slms'@'localhost' IDENTIFIED BY 'a-strong-password';
GRANT ALL PRIVILEGES ON slms.* TO 'slms'@'localhost';
FLUSH PRIVILEGES;
```

Then point the app at it and install:

```bash
export DB_DRIVER=mysql
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_DATABASE=slms
export DB_USERNAME=slms
export DB_PASSWORD='a-strong-password'

php bin/console.php install
php bin/console.php status      # confirm the tables were created
```

Set the same variables for the web server process (`SetEnv` in Apache,
`fastcgi_param` in nginx, or `env[...]` in the PHP-FPM pool).

---

## 5. PostgreSQL

```sql
CREATE DATABASE slms;
CREATE USER slms WITH PASSWORD 'a-strong-password';
GRANT ALL PRIVILEGES ON DATABASE slms TO slms;
```

```bash
export DB_DRIVER=pgsql
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_DATABASE=slms
export DB_USERNAME=slms
export DB_PASSWORD='a-strong-password'

php bin/console.php install
```

---

## 6. Configuration reference

Everything lives in `config/config.php`, and every value can be overridden with
an environment variable of the same name.

| Variable | Default | Meaning |
|---|---|---|
| `APP_DEBUG` | `false` | Show exception detail in the browser. Never enable in production. |
| `APP_DEMO_MODE` | `true` | Show the account picker and the demo password on the login screen. Set `false` for a real deployment — the login form then asks for an e-mail address. |
| `APP_TIMEZONE` | `UTC` | Timezone for all stored and displayed timestamps. |
| `APP_BASE_PATH` | `` | Set when serving from a subdirectory, e.g. `/sickleave`. |
| `DB_DRIVER` | `sqlite` | `sqlite`, `mysql` or `pgsql`. |
| `DB_SQLITE_PATH` | `storage/db/slms.sqlite` | SQLite file location. |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | — | Server database credentials. |
| `SESSION_NAME` | `SLMSSESSID` | Session cookie name. |
| `SESSION_LIFETIME` | `2700` | Idle timeout in seconds (45 minutes). |
| `SESSION_SECURE` | `false` | Send the session cookie over HTTPS only. **Enable in production.** |
| `UPLOAD_PATH` | `storage/uploads` | Where attachments are written. |
| `UPLOAD_MAX_BYTES` | `10485760` | Maximum size per file (10 MB). |
| `UPLOAD_MAX_FILES` | `10` | Maximum files per upload. |

Business rules (retention, SLA thresholds, entitlement) seed from
`config/config.php` on install and are edited afterwards by an Admin in the
**Retention / SLA Settings** tab.

---

## 7. Production checklist

1. **Serve `public/` only.** Never point a document root at the project root.
2. **Use HTTPS** and set `SESSION_SECURE=true`.
3. **`APP_DEBUG=false`** (the default). Exception detail then goes only to
   `storage/logs/php-error.log`.
4. **`APP_DEMO_MODE=false`.** This removes the account picker and the demo
   password hint, and switches the login form to e-mail plus password.
5. **Change every seeded password**, or delete the demo accounts:
   ```bash
   php bin/console.php passwd system.admin@example.com 'a-strong-password'
   ```
6. **Lock down `storage/`**: writable by the web server user, readable by no one
   else, and never inside the document root.
   ```bash
   chown -R www-data:www-data storage
   chmod -R 750 storage
   ```
7. **Back up** the database file (or run `mysqldump` / `pg_dump`) *and*
   `storage/uploads` — the attachments are not in the database.
8. **Set `APP_TIMEZONE`** to the timezone the organisation works in, before
   entering real data. All day counts and SLA arithmetic use it.
9. **Review the retention period** in the Settings tab against local rules for
   occupational health records.
10. **Consider a real mail transport** if you want the notifications delivered
    outside the application; they are currently in-app only.

---

## 8. Upgrading an existing installation

```bash
php bin/console.php migrate     # adds any missing tables, never drops data
```

`migrate` is idempotent and safe to run on every deploy.

---

## 9. Troubleshooting

**"Setup required" page.** The schema does not exist yet. Run
`php bin/console.php install`.

**"Database connection failed".** Check `DB_*` variables, that the database
exists, and — for SQLite — that `storage/db/` is writable by the web server user.

**Uploads fail silently.** Check that `storage/uploads` is writable, and that
`upload_max_filesize` and `post_max_size` in `php.ini` are at least as large as
`UPLOAD_MAX_BYTES`.

**Everything 404s except the home page.** The document root is probably the
project root rather than `public/`, or PHP is not handling `.php` files.

**A page is blank or shows the generic error page.** The detail is in
`storage/logs/php-error.log`. Set `APP_DEBUG=true` temporarily to see it in the
browser.

**Sessions do not persist.** Check that PHP's session save path is writable and
that the clock is correct — a session older than `SESSION_LIFETIME` is dropped.
