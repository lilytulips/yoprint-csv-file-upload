# YoPrint CSV File Upload

A Laravel application for uploading and processing CSV files with background job processing.

## Features

- **File Upload**: Upload CSV files via drag & drop or file selection
- **Background Processing**: Files are processed asynchronously using Laravel Horizon and Redis
- **Idempotent Uploads**: Same file can be uploaded multiple times without creating duplicates
- **UPSERT Support**: Updates existing records based on UNIQUE_KEY
- **UTF-8 Cleaning**: Automatically cleans non-UTF-8 characters from CSV files
- **Real-time Status Updates**: Polling-based status updates for file processing
- **Upload History**: View all file uploads with status and progress
- **API with Transformers**: RESTful API using Fractal transformers

## Requirements

- PHP 8.2+
- Composer
- Redis (for queue processing)
- SQLite (or MySQL/PostgreSQL)

## Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd yoprint-csv-file-upload
```

2. Install dependencies:
```bash
composer install
```

3. Configure environment:
```bash
cp .env.example .env
php artisan key:generate
```

4. Update `.env` file:
```env
DB_CONNECTION=sqlite
QUEUE_CONNECTION=redis
```

5. Create SQLite database:
```bash
touch database/database.sqlite
```

6. Run migrations:
```bash
php artisan migrate
```

7. Install and configure Horizon:
```bash
php artisan horizon:install
```

8. Start Redis server (if not already running):
```bash
# On macOS with Homebrew
brew services start redis

# On Linux
sudo systemctl start redis

# Or use Docker
docker run -d -p 6379:6379 redis
```

9. Start the development server:
```bash
php artisan serve
```

10. In a separate terminal, start Horizon:
```bash
php artisan horizon
```

## Usage

1. Open your browser and navigate to `http://localhost:8000`
2. Click "Upload File" or drag and drop a CSV file
3. The file will be processed in the background
4. View the upload history and status in the table below

## CSV Format

The CSV file should have the following columns:
- `UNIQUE_KEY` (required) - Unique identifier for each record
- `PRODUCT_TITLE` - Product title
- `PRODUCT_DESCRIPTION` - Product description
- `STYLE#` - Style number
- `SANMAR_MAINFRAME_COLOR` - Mainframe color
- `SIZE` - Size
- `COLOR_NAME` - Color name
- `PIECE_PRICE` - Piece price

## API Endpoints

### Upload File
```
POST /api/upload
Content-Type: multipart/form-data

Body:
- file: CSV file
```

### Get All Uploads
```
GET /api/uploads
```

### Get Upload by ID
```
GET /api/uploads/{id}
```

## Features Implementation

### Idempotent Uploads
Files are hashed using SHA-256. If a file with the same hash is uploaded, the existing upload record is returned instead of creating a duplicate.

### UPSERT Logic
Records are upserted based on the `UNIQUE_KEY` field. If a record with the same `UNIQUE_KEY` exists, it will be updated; otherwise, a new record will be created.

### UTF-8 Cleaning
Non-UTF-8 characters are automatically cleaned from the CSV file before processing using iconv with the `//IGNORE` flag.

### Background Processing
File processing is handled by the `ProcessCsvFile` job, which is dispatched to the Redis queue and processed by Horizon workers.

### Real-time Status Updates
The frontend polls the API every 3 seconds to check for status updates on processing files.

## Testing

To test the application:

1. Create a test CSV file with the required columns
2. Upload it via the web interface
3. Check the upload status in the table
4. Verify the records in the database:
```bash
php artisan tinker
>>> App\Models\CsvRecord::count()
```

## Troubleshooting

### Horizon not processing jobs
- Make sure Redis is running
- Check Horizon status: `php artisan horizon:status`
- Check queue connection in `.env`: `QUEUE_CONNECTION=redis`

### File upload fails
- Check storage permissions: `chmod -R 775 storage`
- Check file size limits in `php.ini`
- Check Laravel logs: `storage/logs/laravel.log`

### Database errors
- Make sure SQLite database exists: `touch database/database.sqlite`
- Check database permissions
- Run migrations: `php artisan migrate:fresh`

## License

MIT
