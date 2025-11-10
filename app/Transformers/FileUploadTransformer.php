<?php

namespace App\Transformers;

use App\Models\FileUpload;
use League\Fractal\TransformerAbstract;

class FileUploadTransformer extends TransformerAbstract
{
    /**
     * Transform a file upload model.
     *
     * @param FileUpload $fileUpload
     * @return array
     */
    public function transform(FileUpload $fileUpload): array
    {
        return [
            'id' => $fileUpload->id,
            'original_filename' => $fileUpload->original_filename,
            'status' => $fileUpload->status,
            'total_rows' => $fileUpload->total_rows,
            'processed_rows' => $fileUpload->processed_rows,
            'error_message' => $fileUpload->error_message,
            'created_at' => $fileUpload->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $fileUpload->created_at->diffForHumans(),
            'updated_at' => $fileUpload->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
