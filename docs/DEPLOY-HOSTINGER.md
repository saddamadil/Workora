# Deploying to Hostinger (shared hosting)

These notes use the subdomain `freelancy.saddamadil.in`. Replace it with yours.

## 1. Build the upload package

```bash
# SQLite (simplest, one file)
scripts/build-deploy.sh freelancy.saddamadil.in

# or MySQL/MariaDB (what hPanel's "Databases" gives you)
DB_CONNECTION=mysql DB_DATABASE=u123_workora DB_USERNAME=u123_workora scripts/build-deploy.sh freelancy.saddamadil.in
```

Output: `dist/workora-<domain>.zip` (about 18 MB) with

- `workora/`: the app, `vendor/`, a production `.env` with a fresh `APP_KEY`
  (and a migrated `database.sqlite` when using SQLite)
- `webroot/`: the public files and an `index.php` that finds `workora/` on its own

Needs PHP, Composer, Node and `zip` on your computer. It never touches your local `.env`.

## 2. MySQL only: create the tables

phpMyAdmin has no command line, so import a ready-made file instead of running migrations:

```bash
DB_DATABASE=scratch DB_USERNAME=root DB_PASSWORD=secret scripts/export-schema.sh   # needs a local MariaDB
```

In hPanel open **Databases > phpMyAdmin**, pick your database, **Import**, choose `dist/workora-schema.sql`.
It has one statement per line and `DROP TABLE IF EXISTS`, so it is safe to run again. You should end up
with 42 tables. (Tested on MariaDB 10.11, which is what Hostinger runs.)

## 3. Point the subdomain at the right folder

Each website on Hostinger has its **own** File Manager and folder. In hPanel > **Websites**, click
**Dashboard** next to `freelancy.saddamadil.in`, then **Files > File Manager**. That shows the folder
that contains this site's `public_html`.

1. Upload the zip into that top folder (next to `public_html`, not inside it) and extract it. You get
   `workora/` and `webroot/`.
2. Move everything inside `webroot/` into `public_html/`, including the hidden `.htaccess`. Delete
   `webroot/` and the zip.
3. Never leave `workora/` inside `public_html`: it holds `.env`, the database and every uploaded file.
4. Make `workora/storage`, `workora/bootstrap/cache` (and `workora/database` for SQLite) writable.
5. hPanel > Advanced > PHP Configuration: PHP 8.3+ (8.4 if offered), `upload_max_filesize` 100M,
   `post_max_size` 110M. Turn on SSL for the subdomain.

## 4. Finish `.env` and test

For MySQL, open `workora/.env` in the File Manager and replace `PUT_YOUR_DATABASE_PASSWORD_HERE`.
Check `APP_URL=https://freelancy.saddamadil.in` matches the real address letter for letter.

Open `https://freelancy.saddamadil.in/robots.txt` (should show text), then the home page, then register.
A 500 error: read `workora/storage/logs/laravel.log`. A 403: `index.php` is not directly in the
subdomain's `public_html`.

## Email

Invitations work without email: the link is shown on screen to copy and send. To also email it, set
`MAIL_MAILER=smtp` and the host, port, username and password from hPanel > Emails in `workora/.env`.

## Updating later

Rebuild and upload again, but **do not overwrite** these or you lose data:

- `workora/.env`
- `workora/storage/app/` (uploaded files)
- `workora/database/database.sqlite` (SQLite only)

New migrations on MySQL: run `scripts/export-schema.sh` only for a fresh database. For an existing one,
apply just the new migration's SQL by hand in phpMyAdmin (ask for it) or use SSH and `php artisan migrate`.

## Backups

Download your database (phpMyAdmin > Export, or the SQLite file) and `workora/storage/app/private/`
regularly. Uploaded files live only there.
