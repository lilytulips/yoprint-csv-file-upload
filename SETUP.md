# Quick Setup Guide

## Prerequisites
1. PHP 8.2+
2. Composer
3. Redis (for queue processing)
4. SQLite (already configured)

## Installation Steps

1. **Install dependencies:**
   ```bash
   composer install
   ```

2. **Configure environment:**
   - The `.env` file is already configured with SQLite and Redis
   - Make sure Redis is running:
     ```bash
     # macOS
     brew services start redis
     
     # Linux
     sudo systemctl start redis
     
     # Or use Docker
     docker run -d -p 6379:6379 redis
     ```

3. **Run migrations:**
   ```bash
   php artisan migrate
   ```

4. **Start the application:**
   
   In terminal 1 (Laravel server):
   ```bash
   php artisan serve
   ```
   
   In terminal 2 (Horizon worker):
   ```bash
   php artisan horizon
   ```

5. **Access the application:**
   - Web UI: http://localhost:8000
   - Horizon Dashboard: http://localhost:8000/horizon

## Testing

1. Create a test CSV file with these columns:
   - UNIQUE_KEY (required)
   - PRODUCT_TITLE
   - PRODUCT_DESCRIPTION
   - STYLE#
   - SANMAR_MAINFRAME_COLOR
   - SIZE
   - COLOR_NAME
   - PIECE_PRICE

2. Upload the CSV file through the web interface

3. Check the upload status in the table

4. Verify records in the database:
   ```bash
   php artisan tinker
   >>> App\Models\CsvRecord::count()
   ```

## Features

✅ File upload with drag & drop
✅ Background processing with Horizon
✅ Idempotent uploads (same file = no duplicates)
✅ UPSERT logic (updates existing records by UNIQUE_KEY)
✅ UTF-8 character cleaning
✅ Real-time status updates (polling every 3 seconds)
✅ Upload history with progress tracking
✅ API endpoints with Fractal transformers

## Troubleshooting

- **Horizon not processing jobs**: Check if Redis is running
- **File upload fails**: Check storage permissions (`chmod -R 775 storage`)
- **Database errors**: Ensure SQLite database exists (`touch database/database.sqlite`)
