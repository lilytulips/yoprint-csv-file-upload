<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCsvFile;
use App\Models\FileUpload;
use App\Transformers\FileUploadTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;

class FileUploadController extends Controller
{
    protected Manager $fractal;

    public function __construct(Manager $fractal)
    {
        $this->fractal = $fractal;
    }

    /**
     * Upload a CSV file.
     */
    public function upload(Request $request): JsonResponse
    {
        // Check if file was uploaded (handles cases where PHP upload limits are exceeded)
        if (!$request->hasFile('file')) {
            $uploadMaxSize = ini_get('upload_max_filesize');
            $postMaxSize = ini_get('post_max_size');
            
            return response()->json([
                'message' => 'No file uploaded. Please check PHP upload limits.',
                'errors' => [
                    'file' => [
                        'PHP upload_max_filesize: ' . $uploadMaxSize,
                        'PHP post_max_size: ' . $postMaxSize,
                        'Please increase these limits in php.ini to upload larger files.'
                    ]
                ]
            ], 422);
        }
        
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB max (in KB)
        ]);

        $file = $request->file('file');
        
        // Validate file extension manually
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'txt'])) {
            return response()->json([
                'message' => 'Invalid file type. Only CSV and TXT files are allowed.',
                'errors' => ['file' => ['The file must be a CSV or TXT file.']]
            ], 422);
        }
        
        // Calculate file hash for idempotency
        $fileHash = hash_file('sha256', $file->getRealPath());

        // Check if file with same hash already exists
        $existingUpload = FileUpload::where('file_hash', $fileHash)->first();
        
        if ($existingUpload) {
            // Return existing upload (idempotent)
            $resource = new Item($existingUpload, new FileUploadTransformer());
            $data = $this->fractal->createData($resource)->toArray();
            
            return response()->json([
                'message' => 'File already uploaded',
                'data' => $data['data'],
            ], 200);
        }

        // Store the file
        $filePath = $file->store('csv-uploads', 'local');
        
        // Create file upload record
        $fileUpload = FileUpload::create([
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'file_hash' => $fileHash,
            'status' => 'pending',
        ]);

        // Dispatch job to process CSV file
        ProcessCsvFile::dispatch($fileUpload);

        // Transform response
        $resource = new Item($fileUpload, new FileUploadTransformer());
        $data = $this->fractal->createData($resource)->toArray();

        return response()->json([
            'message' => 'File uploaded successfully',
            'data' => $data['data'],
        ], 201);
    }

    /**
     * Get all file uploads.
     */
    public function index(): JsonResponse
    {
        $fileUploads = FileUpload::orderBy('created_at', 'desc')->get();

        $resource = new Collection($fileUploads, new FileUploadTransformer());
        $data = $this->fractal->createData($resource)->toArray();

        return response()->json([
            'data' => $data['data'],
        ], 200);
    }

    /**
     * Get a specific file upload.
     */
    public function show($id): JsonResponse
    {
        $fileUpload = FileUpload::findOrFail($id);

        $resource = new Item($fileUpload, new FileUploadTransformer());
        $data = $this->fractal->createData($resource)->toArray();

        return response()->json([
            'data' => $data['data'],
        ], 200);
    }
}
