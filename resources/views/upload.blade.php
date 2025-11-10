<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CSV File Upload - {{ config('app.name', 'Laravel') }}</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .upload-area {
            border: 2px dashed #d1d5db;
            transition: all 0.3s ease;
        }
        .upload-area.dragover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-processing { background-color: #dbeafe; color: #1e40af; }
        .status-completed { background-color: #d1fae5; color: #065f46; }
        .status-failed { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="antialiased bg-gray-50">
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">CSV File Upload</h1>
                <p class="mt-2 text-sm text-gray-600">Upload and process CSV files</p>
            </div>

            <!-- File Upload Area -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
                <div class="upload-area rounded-lg p-12 text-center" id="uploadArea">
                    <input type="file" id="fileInput" accept=".csv,.txt" class="hidden">
                    <div class="space-y-4">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div>
                            <p class="text-lg font-medium text-gray-900">Select file / Drag and drop</p>
                            <p class="mt-1 text-sm text-gray-500">CSV files only</p>
                        </div>
                        <button type="button" id="uploadButton" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Upload File
                        </button>
                    </div>
                </div>
            </div>

            <!-- Upload History Table -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Upload History</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" id="sortTime">
                                    Time
                                    <span class="ml-1">▲</span>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" id="sortFileName">
                                    File Name
                                    <span class="ml-1">▲▼</span>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Progress
                                </th>
                            </tr>
                        </thead>
                        <tbody id="uploadsTableBody" class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Loading...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/api';
        let pollInterval = null;

        // File upload functionality
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const uploadButton = document.getElementById('uploadButton');

        uploadButton.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', handleFileSelect);

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect({ target: fileInput });
            }
        });

        async function handleFileSelect(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (!file.name.endsWith('.csv') && !file.name.endsWith('.txt')) {
                alert('Please select a CSV file');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);

            try {
                uploadButton.disabled = true;
                uploadButton.textContent = 'Uploading...';

                const response = await fetch(`${API_BASE}/upload`, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (response.ok) {
                    fileInput.value = '';
                    uploadButton.textContent = 'Upload File';
                    uploadButton.disabled = false;
                    loadUploads();
                    startPolling();
                } else {
                    alert('Upload failed: ' + (data.message || 'Unknown error'));
                    uploadButton.textContent = 'Upload File';
                    uploadButton.disabled = false;
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('Upload failed: ' + error.message);
                uploadButton.textContent = 'Upload File';
                uploadButton.disabled = false;
            }
        }

        // Load uploads
        async function loadUploads() {
            try {
                const response = await fetch(`${API_BASE}/uploads`);
                const result = await response.json();
                const uploads = result.data || [];

                const tbody = document.getElementById('uploadsTableBody');
                
                if (uploads.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                No uploads yet
                            </td>
                        </tr>
                    `;
                    return;
                }

                tbody.innerHTML = uploads.map(upload => {
                    const date = new Date(upload.created_at);
                    const timeStr = date.toLocaleString('en-US', {
                        month: '2-digit',
                        day: '2-digit',
                        year: '2-digit',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });
                    
                    const progress = upload.total_rows 
                        ? Math.round((upload.processed_rows / upload.total_rows) * 100)
                        : 0;

                    return `
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${timeStr}
                                <span class="text-gray-500">(${upload.created_at_human})</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${upload.original_filename}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="status-badge status-${upload.status}">
                                    ${upload.status}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                ${upload.status === 'processing' ? `${upload.processed_rows || 0} / ${upload.total_rows || 0} (${progress}%)` : ''}
                                ${upload.status === 'completed' ? 'Completed' : ''}
                                ${upload.status === 'failed' ? (upload.error_message ? upload.error_message.substring(0, 50) + '...' : 'Failed') : ''}
                                ${upload.status === 'pending' ? 'Pending' : ''}
                            </td>
                        </tr>
                    `;
                }).join('');

                // Check if we need to continue polling
                const hasProcessing = uploads.some(u => u.status === 'processing' || u.status === 'pending');
                if (!hasProcessing && pollInterval) {
                    stopPolling();
                }

            } catch (error) {
                console.error('Error loading uploads:', error);
            }
        }

        // Polling for status updates
        function startPolling() {
            if (pollInterval) return;
            
            pollInterval = setInterval(() => {
                loadUploads();
            }, 3000); // Poll every 3 seconds
        }

        function stopPolling() {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        }

        // Initial load
        loadUploads();
        startPolling();
    </script>
</body>
</html>
