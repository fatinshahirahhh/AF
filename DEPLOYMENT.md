# AF CULTURED launch check — 19 October 2026

## Confirmed on local XAMPP

- PHP 8.2.12 with PDO SQLite, mbstring, fileinfo and image support is installed. PHP and JavaScript syntax checks passed.
- Seller login and logout passed. Passwords are hashed in SQLite; the password previously shared in chat remains unchanged.
- Authenticated catalogue saving and public readback passed without changing catalogue content. Anonymous edits and requests without a CSRF token were rejected.
- A JPG upload was accepted, served successfully, and then removed after testing. A fake image was rejected.
- WhatsApp is configured as +60102356513, Instagram as https://www.instagram.com/af_cultured.my/, and TikTok as https://www.tiktok.com/@af_cultured. These are the configured destinations; no messages or orders were sent.
- Both designs cost RM79 and have S, M, L and XL. Black stock is 12; cream stock is 5, based on the seller's instructions. Physical inventory is not independently verified.
- Apache denied access to the database, storage configuration, backup folder, scripts and upload configuration with HTTP 403.
- Earlier isolated browser tests passed homepage editing, campaign uploads, separate front/back/both product photos, and responsive layouts. The public shop currently has only All pieces.

## Backup

Run `C:/xampp/php/php.exe scripts/backup.php` from the AF folder. It creates a protected ZIP in `backups/`, including the website, uploaded photos and a consistent SQLite snapshot, and reports its SHA-256 checksum. It excludes active sessions, QA files and hosting identity metadata. The ZIP contains seller password hashes and must stay private. Keep an additional copy outside the server through your own backup storage.

## Still required before a public launch

1. Choose hosting and a domain. Neither has been connected or purchased by this check.
2. Replace the seller password shared in chat. It was not silently changed, so the seller can still log in. A private new password is required before public launch; this version does not yet provide a password-change screen.
3. Enable HTTPS on the selected hosting and verify HTTPS redirects and secure session cookies there.
4. Restore the complete application and database to the chosen host, then repeat login, uploads, catalogue/homepage saving and customer link checks on its actual URL.
5. Set a recurring backup schedule and test restoring the ZIP on the chosen hosting. This check created an on-demand backup only.

## Hosting choice

The current implementation uses PHP, a writable SQLite database, file-based sessions, local uploads and Apache .htaccess access controls. It is suitable for a compatible PHP/Apache host with persistent writable storage. Upload the full application structure, not only dist/. Preserve .htaccess files, keep storage inaccessible, and grant PHP write access only to storage/ and dist/uploads/. Use the host's currently supported PHP release and confirm required extensions. For an Nginx host, equivalent access rules must be configured; .htaccess is not sufficient.

Vercel is not a direct deployment target for this version. Its function filesystem is read-only apart from temporary scratch space, and PHP uses a community runtime. A Vercel version needs persistent remote database, session and image storage, plus a suitable backend runtime and deployment configuration. Merely uploading dist/ would display a static fallback and disable seller login/saving. It may also expose PHP source if configured incorrectly. No Vercel deployment has been attempted.

Official reference: https://vercel.com/docs/functions/runtimes
