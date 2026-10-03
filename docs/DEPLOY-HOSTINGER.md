# Deploying to Hostinger (shared hosting)

Target in these notes: `https://frelancy.saddamadil.in`.

## 1. Build the package

```bash
scripts/build-deploy.sh frelancy.saddamadil.in
```

Output: `dist/workora-frelancy.saddamadil.in.zip` (about 18 MB) containing

- `workora/` : the app, `vendor/`, a migrated SQLite database and a production `.env`
  with a fresh `APP_KEY`
- `webroot/` : the public files and an `index.php` that finds `workora/` on its own

The script needs PHP, Composer, Node and `zip`. It does not touch your local `.env`.

## 2. Make the subdomain point at this hosting account

The subdomain's DNS and the hosting account must match. In hPanel:

1. Add `saddamadil.in` to this hosting plan (or create the subdomain if the domain is
   already there). If the domain's nameservers are elsewhere, add an A record for
   `frelancy` pointing at this server's IP instead.
2. Note the subdomain's **document root** that hPanel shows (for example
   `public_html/frelancy`).
3. Advanced > PHP Configuration: choose PHP 8.3 or higher, and set
   `upload_max_filesize` = 100M and `post_max_size` = 110M.
4. SSL: turn on the free certificate for the subdomain.

## 3. Upload

Fastest is the File Manager: upload the zip, then Extract.

1. Extract in the folder that **contains** `public_html` (the account's home folder in
   the File Manager). This creates `workora/` and `webroot/` there.
2. Move everything inside `webroot/` (including the hidden `.htaccess`) into the
   subdomain's document root. Then delete the empty `webroot/` folder and the zip.
3. Make sure these are writable (permission 755 normally works; try 775 if you see a
   500 error): `workora/storage`, `workora/bootstrap/cache`, `workora/database`.

`workora/` must stay outside the document root: it holds `.env`, the database and every
uploaded file.

## 4. Check it

Open the site, register, upload a file, create a share link and open it in a private
window. If you see a 500 error, read `workora/storage/logs/laravel.log`.

## 5. Google Drive

In your Google OAuth client add `https://frelancy.saddamadil.in/drive/callback` as an
authorized redirect URI. Then edit `workora/.env` in the File Manager and fill in
`GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`. While the consent screen is in Testing
mode only listed test users can connect.

## Updating later

Rebuild and upload again, but **do not overwrite** these, or you lose your data:

- `workora/database/database.sqlite` (accounts, file records, links)
- `workora/storage/app/` (the uploaded files)
- `workora/.env` (your key and Google credentials)

If new migrations are added and you have no SSH, run them locally against a copy of the
live database file, then upload that file back.

## Backups

Download `workora/database/database.sqlite` and `workora/storage/app/private/` regularly.
