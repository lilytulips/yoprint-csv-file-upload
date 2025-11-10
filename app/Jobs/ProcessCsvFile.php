<?php

namespace App\Jobs;

use App\Models\FileUpload;
use App\Models\CsvRecord;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;

class ProcessCsvFile implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public FileUpload $fileUpload
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Increase memory limit for large file processing
            ini_set('memory_limit', '512M');
            
            // Update status to processing
            $this->fileUpload->update(['status' => 'processing']);

            // Read the CSV file
            $filePath = storage_path('app/' . $this->fileUpload->file_path);
            
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            // Clean UTF-8 and create temporary file using streaming to avoid memory exhaustion
            $tempFile = $this->cleanUtf8File($filePath);

            // Create CSV reader
            $csv = Reader::createFromPath($tempFile, 'r');
            $csv->setHeaderOffset(0);

            // Get header
            $headers = $csv->getHeader();
            
            // Count total rows first (streaming approach)
            $totalRows = $this->countCsvRows($tempFile);
            $this->fileUpload->update(['total_rows' => $totalRows]);

            $processedRows = 0;
            $errors = [];
            $rowIndex = 0;

            // Process records using iterator (streaming, not loading all into memory)
            foreach ($csv->getRecords($headers) as $record) {
                $rowIndex++;
                
                try {
                    // Map CSV columns to database fields
                    $data = $this->mapCsvRowToData($record, $headers);

                    // Skip if UNIQUE_KEY is missing
                    if (empty($data['unique_key'])) {
                        $errors[] = "Row " . ($rowIndex + 1) . ": UNIQUE_KEY is missing";
                        continue;
                    }

                    // UPSERT: Update if exists, insert if not
                    CsvRecord::updateOrCreate(
                        ['unique_key' => $data['unique_key']],
                        [
                            'file_upload_id' => $this->fileUpload->id,
                            'product_title' => $data['product_title'],
                            'product_description' => $data['product_description'],
                            'style_number' => $data['style_number'],
                            'sanmar_mainframe_color' => $data['sanmar_mainframe_color'],
                            'size' => $data['size'],
                            'color_name' => $data['color_name'],
                            'piece_price' => $data['piece_price'],
                        ]
                    );

                    $processedRows++;
                    
                    // Update progress every 100 rows to reduce database writes
                    if ($processedRows % 100 == 0 || $processedRows == $totalRows) {
                        $this->fileUpload->refresh();
                        $this->fileUpload->update(['processed_rows' => $processedRows]);
                    }

                } catch (\Exception $e) {
                    $errors[] = "Row " . ($rowIndex + 1) . ": " . $e->getMessage();
                    Log::error("Error processing CSV row", [
                        'file_upload_id' => $this->fileUpload->id,
                        'row' => $rowIndex + 1,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Final update of processed rows
            $this->fileUpload->refresh();
            $this->fileUpload->update(['processed_rows' => $processedRows]);

            // Clean up temporary file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            // Update status
            if (!empty($errors)) {
                $this->fileUpload->refresh();
                $this->fileUpload->update([
                    'status' => 'completed',
                    'error_message' => 'Processed with errors: ' . implode('; ', array_slice($errors, 0, 10))
                ]);
            } else {
                $this->fileUpload->refresh();
                $this->fileUpload->update(['status' => 'completed']);
            }

        } catch (\Exception $e) {
            // Update status to failed
            $this->fileUpload->refresh();
            $this->fileUpload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            Log::error("Error processing CSV file", [
                'file_upload_id' => $this->fileUpload->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Clean UTF-8 file using streaming to avoid memory exhaustion.
     */
    private function cleanUtf8File(string $filePath): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
        $inputHandle = fopen($filePath, 'r');
        $outputHandle = fopen($tempFile, 'w');
        
        if (!$inputHandle || !$outputHandle) {
            throw new \Exception("Failed to create temporary file for UTF-8 cleaning");
        }
        
        $isFirstLine = true;
        $bufferSize = 8192; // 8KB chunks
        
        while (!feof($inputHandle)) {
            $chunk = fread($inputHandle, $bufferSize);
            
            if ($chunk === false) {
                break;
            }
            
            // Remove BOM only from first line
            if ($isFirstLine) {
                $chunk = preg_replace('/\x{FEFF}/u', '', $chunk);
                $isFirstLine = false;
            }
            
            // Clean UTF-8 chunk by chunk
            $cleanedChunk = @iconv('UTF-8', 'UTF-8//IGNORE', $chunk);
            if ($cleanedChunk === false) {
                // If iconv fails, use mb_convert_encoding as fallback
                $cleanedChunk = mb_convert_encoding($chunk, 'UTF-8', 'UTF-8');
            }
            
            fwrite($outputHandle, $cleanedChunk);
        }
        
        fclose($inputHandle);
        fclose($outputHandle);
        
        return $tempFile;
    }
    
    /**
     * Count CSV rows efficiently without loading all into memory.
     */
    private function countCsvRows(string $filePath): int
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return 0;
        }
        
        $count = 0;
        // Skip header row
        fgetcsv($handle);
        
        while (($data = fgetcsv($handle)) !== false) {
            $count++;
        }
        
        fclose($handle);
        return $count;
    }

    /**
     * Clean non-UTF-8 characters from a string.
     */
    private function cleanUtf8(string $content): string
    {
        // Remove BOM if present
        $content = preg_replace('/\x{FEFF}/u', '', $content);

        // Convert to UTF-8 and remove invalid characters using iconv
        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $content);
        if ($cleaned === false) {
            // If iconv fails, use mb_convert_encoding as fallback
            $cleaned = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }

        return $cleaned ?: $content;
    }

    /**
     * Map CSV row to database data.
     */
    private function mapCsvRowToData(array $record, array $headers): array
    {
        // Normalize headers to lowercase for matching
        $headerMap = [];
        foreach ($headers as $header) {
            $normalizedKey = strtolower(trim($header));
            $headerMap[$normalizedKey] = $header;
        }

        // Helper function to get value by various possible header names
        $getValue = function($possibleKeys) use ($record, $headerMap) {
            foreach ($possibleKeys as $key) {
                $normalizedKey = strtolower(trim($key));
                if (isset($headerMap[$normalizedKey]) && isset($record[$headerMap[$normalizedKey]])) {
                    $value = $record[$headerMap[$normalizedKey]];
                    return $value !== null && $value !== '' ? trim($value) : null;
                }
            }
            return null;
        };

        // Map to database fields - try various possible column names
        $uniqueKey = $getValue(['UNIQUE_KEY', 'unique_key', 'Unique Key', 'unique key', 'UNIQUEKEY', 'uniquekey']) ?? '';
        $productTitle = $getValue(['PRODUCT_TITLE', 'product_title', 'Product Title', 'product title', 'PRODUCTTITLE', 'producttitle']);
        $productDescription = $getValue(['PRODUCT_DESCRIPTION', 'product_description', 'Product Description', 'product description', 'PRODUCTDESCRIPTION', 'productdescription']);
        $styleNumber = $getValue(['STYLE#', 'style#', 'STYLE', 'style', 'style_number', 'STYLE_NUMBER', 'Style#']);
        $sanmarMainframeColor = $getValue(['SANMAR_MAINFRAME_COLOR', 'sanmar_mainframe_color', 'Sanmar Mainframe Color', 'sanmar mainframe color', 'SANMARMAINFRAMECOLOR']);
        $size = $getValue(['SIZE', 'size', 'Size']);
        $colorName = $getValue(['COLOR_NAME', 'color_name', 'Color Name', 'color name', 'COLORNAME', 'colorname']);
        $piecePrice = $getValue(['PIECE_PRICE', 'piece_price', 'Piece Price', 'piece price', 'PIECEPRICE', 'pieceprice']);

        // Clean and convert piece_price
        $piecePriceValue = null;
        if ($piecePrice !== null && $piecePrice !== '') {
            // Remove currency symbols and clean
            $piecePrice = preg_replace('/[^0-9.]/', '', $piecePrice);
            $piecePriceValue = $piecePrice !== '' ? (float) $piecePrice : null;
        }

        return [
            'unique_key' => $this->cleanUtf8((string)$uniqueKey),
            'product_title' => $productTitle ? $this->cleanUtf8($productTitle) : null,
            'product_description' => $productDescription ? $this->cleanUtf8($productDescription) : null,
            'style_number' => $styleNumber ? $this->cleanUtf8($styleNumber) : null,
            'sanmar_mainframe_color' => $sanmarMainframeColor ? $this->cleanUtf8($sanmarMainframeColor) : null,
            'size' => $size ? $this->cleanUtf8($size) : null,
            'color_name' => $colorName ? $this->cleanUtf8($colorName) : null,
            'piece_price' => $piecePriceValue,
        ];
    }
}
