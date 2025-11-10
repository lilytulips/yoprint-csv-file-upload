# PHP Configuration for Large File Uploads

## Current Issue

Your PHP configuration has the following limits:
- `upload_max_filesize`: 2M (too small for 36MB files)
- `post_max_size`: 8M (should be larger than upload_max_filesize)
- `memory_limit`: 128M (being increased to 512M at runtime in the job)

## Solutions

### Option 1: Increase PHP Limits via php.ini (Recommended)

Find your PHP configuration file:
```bash
php --ini
```

Edit the php.ini file and update these values:
```ini
upload_max_filesize = 100M
post_max_size = 100M
memory_limit = 512M
max_execution_time = 300
```

Then restart your PHP server.

### Option 2: Increase Limits for php artisan serve

Since `php artisan serve` uses the CLI PHP, you can:

1. Create a custom php.ini file:
```bash
php -i | grep "Loaded Configuration File"
```

2. Copy that file and modify it, then run:
```bash
php -c /path/to/custom/php.ini artisan serve
```

### Option 3: Use PHP-FPM or Apache with .htaccess

If using Apache, create or update `public/.htaccess`:
```apache
php_value upload_max_filesize 100M
php_value post_max_size 100M
php_value memory_limit 512M
php_value max_execution_time 300
```

### Option 4: Use Docker/Laravel Sail

If using Laravel Sail, the `php.ini` file in the project root should be used.

## Verification

Check your current PHP settings:
```bash
php -i | grep -E "(upload_max_filesize|post_max_size|memory_limit)"
```

Or create a test file `public/phpinfo.php`:
```php
<?php phpinfo(); ?>
```

Then visit: http://localhost:8000/phpinfo.php

## Notes

- The application code has been updated to handle large files using streaming
- Memory limit is increased to 512M at runtime in the ProcessCsvFile job
- CSV processing now uses streaming instead of loading entire file into memory
- Files are processed in chunks to avoid memory exhaustion

