<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FileUpload extends Model
{
    protected $fillable = [
        'original_filename',
        'file_path',
        'file_hash',
        'status',
        'total_rows',
        'processed_rows',
        'error_message',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
    ];

    /**
     * Get the CSV records for this file upload.
     */
    public function csvRecords(): HasMany
    {
        return $this->hasMany(CsvRecord::class);
    }
}
