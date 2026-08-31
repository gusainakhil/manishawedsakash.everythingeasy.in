# Manisha & Akash — Personalized Wedding Invitation

A complete PHP 8.1+ and MySQL 5.7+/8 application with a premium guest invitation, secure admin login, guest CRUD, unique invitation links, view tracking, RSVP management, events, countdown and mobile sharing.

## Install on cPanel

1. Upload the contents of this folder to `public_html` (or a subfolder).
2. Create a MySQL database and database user in cPanel, then assign **All Privileges**.
3. Open `https://yourdomain.com/install.php` in your browser.
4. Enter the full website URL, database credentials and new admin login.
5. After installation, delete `install.php` for security.
6. Sign in at `/admin/login.php`, add a guest, and copy the generated invitation link.

If clean `/invite/...` URLs return 404, confirm Apache `mod_rewrite` and `AllowOverride All` are enabled. On shared cPanel hosting this is normally already configured.

## Local install

Place the folder in XAMPP `htdocs`, start Apache and MySQL, then visit `http://localhost/wedding-invitation/install.php`.

## Requirements

- PHP 8.1 or newer with PDO MySQL and `iconv`
- MySQL 5.7+ or MariaDB 10.4+
- Apache with `mod_rewrite`
- HTTPS recommended in production

# manishawedsakash.everythingeasy.in
