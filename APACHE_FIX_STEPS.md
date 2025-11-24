# Apache Configuration Fix - Step by Step

## Problem
404 errors when accessing `localhost/libratrack/login`

## Solution Steps

### Step 1: Enable mod_rewrite

1. Open: `C:\xampp\apache\conf\httpd.conf`
2. Press `Ctrl+F` and search for: `rewrite_module`
3. Find this line (should be around line 180):
   ```apache
   #LoadModule rewrite_module modules/mod_rewrite.so
   ```
4. Remove the `#` at the beginning:
   ```apache
   LoadModule rewrite_module modules/mod_rewrite.so
   ```
5. Save the file

### Step 2: Allow .htaccess Overrides

1. In the same `httpd.conf` file, search for: `AllowOverride`
2. Find the section for your htdocs directory:
   ```apache
   <Directory "C:/xampp/htdocs">
       Options Indexes FollowSymLinks
       AllowOverride None
       Require all granted
   </Directory>
   ```
3. Change `AllowOverride None` to `AllowOverride All`:
   ```apache
   <Directory "C:/xampp/htdocs">
       Options Indexes FollowSymLinks
       AllowOverride All
       Require all granted
   </Directory>
   ```
4. Save the file

### Step 3: Restart Apache

1. Open XAMPP Control Panel
2. Click **Stop** on Apache
3. Wait a few seconds
4. Click **Start** on Apache
5. Check that Apache shows "Running" in green

### Step 4: Test

Try accessing:
- `http://localhost/libratrack/login`
- `http://localhost/libratrack/login.html`

## Alternative: Use Laravel Serve (Easier for Development)

If Apache configuration is too complicated, just use:

```bash
php artisan serve
```

Then access: `http://localhost:8000/login`

This works immediately without any Apache configuration!









