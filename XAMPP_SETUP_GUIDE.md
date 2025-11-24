# XAMPP Setup Guide for Laravel

## Current Issue
Getting 404 errors when accessing routes through Apache.

## Quick Fix: Use Laravel's Built-in Server

**This is the easiest solution for development:**

1. Open terminal in your project directory
2. Run: `php artisan serve`
3. Access: `http://localhost:8000/login`

This bypasses Apache routing issues completely.

## Fix Apache Routing (For Production)

### Step 1: Enable mod_rewrite

1. Open: `C:\xampp\apache\conf\httpd.conf`
2. Find this line (around line 180):
   ```apache
   #LoadModule rewrite_module modules/mod_rewrite.so
   ```
3. Remove the `#` to uncomment it:
   ```apache
   LoadModule rewrite_module modules/mod_rewrite.so
   ```
4. Save and restart Apache

### Step 2: Configure Directory Permissions

In the same `httpd.conf` file, find the section for your directory:

```apache
<Directory "C:/xampp/htdocs">
    Options Indexes FollowSymLinks
    AllowOverride None
    Require all granted
</Directory>
```

Change `AllowOverride None` to `AllowOverride All`:

```apache
<Directory "C:/xampp/htdocs">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

### Step 3: Restart Apache

1. Stop Apache in XAMPP Control Panel
2. Start Apache again
3. Try: `http://localhost/libratrack/login`

## Alternative: Point Document Root to Public Folder

If routing still doesn't work, you can configure Apache to use the `public` folder as document root:

1. Open: `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
2. Add:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/LibraTrack/public"
    ServerName libratrack.local
    <Directory "C:/xampp/htdocs/LibraTrack/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

3. Add to `C:\Windows\System32\drivers\etc\hosts`:
   ```
   127.0.0.1 libratrack.local
   ```
4. Access: `http://libratrack.local/login`

## Recommended: Use Laravel Serve for Development

For development, just use:
```bash
php artisan serve
```

Then access: `http://localhost:8000/login`

This is simpler and avoids Apache configuration issues.









