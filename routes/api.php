<?php

use App\Http\Controllers\Api\FileUploadController;
use Illuminate\Support\Facades\Route;

Route::post('/upload', [FileUploadController::class, 'upload']);
Route::get('/uploads', [FileUploadController::class, 'index']);
Route::get('/uploads/{id}', [FileUploadController::class, 'show']);
