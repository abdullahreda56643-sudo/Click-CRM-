<?php
// File Upload System
// Prevent direct access
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../config/config.php';
}

// Function to handle file upload
function handle_file_upload($file, $upload_dir = null, $allowed_types = null, $max_size = null) {
    try {
        // Validate file
        $result = validate_file($file, $allowed_types, $max_size);
        if (!$result['valid']) {
            return ['success' => false, 'message' => $result['message']];
        }
        
        // Set default upload directory
        if ($upload_dir === null) {
            $upload_dir = UPLOAD_PATH;
        }
        
        // Create upload directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename_without_ext = pathinfo($file['name'], PATHINFO_FILENAME);
        $sanitized_filename = sanitize_filename($filename_without_ext);
        $unique_filename = $sanitized_filename . '_' . time() . '.' . $file_extension;
        
        // Full file path
        $file_path = $upload_dir . $unique_filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            return [
                'success' => true,
                'filename' => $unique_filename,
                'original_name' => $file['name'],
                'file_path' => $file_path,
                'file_size' => $file['size'],
                'file_type' => $file['type']
            ];
        } else {
            return ['success' => false, 'message' => 'فشل في رفع الملف'];
        }
        
    } catch (Exception $e) {
        error_log("File upload error: " . $e->getMessage());
        return ['success' => false, 'message' => 'حدث خطأ أثناء رفع الملف'];
    }
}

// Function to validate uploaded file
function validate_file($file, $allowed_types = null, $max_size = null) {
    // Check if file was uploaded
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['valid' => false, 'message' => 'لم يتم رفع أي ملف'];
    }
    
    // Check upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
            return ['valid' => false, 'message' => 'حجم الملف يتجاوز الحد الأقصى المحدد في php.ini'];
        case UPLOAD_ERR_FORM_SIZE:
            return ['valid' => false, 'message' => 'حجم الملف يتجاوز الحد الأقصى المحدد في النموذج'];
        case UPLOAD_ERR_PARTIAL:
            return ['valid' => false, 'message' => 'تم رفع الملف جزئياً'];
        case UPLOAD_ERR_NO_FILE:
            return ['valid' => false, 'message' => 'لم يتم رفع أي ملف'];
        case UPLOAD_ERR_NO_TMP_DIR:
            return ['valid' => false, 'message' => 'مجلد المؤقت مفقود'];
        case UPLOAD_ERR_CANT_WRITE:
            return ['valid' => false, 'message' => 'فشل في كتابة الملف على القرص'];
        case UPLOAD_ERR_EXTENSION:
            return ['valid' => false, 'message' => 'تم إيقاف رفع الملف بواسطة امتداد'];
        default:
            return ['valid' => false, 'message' => 'خطأ غير معروف في رفع الملف'];
    }
    
    // Set default values
    if ($allowed_types === null) {
        $allowed_types = ALLOWED_FILE_TYPES;
    }
    
    if ($max_size === null) {
        $max_size = MAX_FILE_SIZE;
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['valid' => false, 'message' => 'حجم الملف يتجاوز الحد الأقصى المسموح (' . format_file_size($max_size) . ')'];
    }
    
    // Check file type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        return ['valid' => false, 'message' => 'نوع الملف غير مسموح به. الأنواع المسموحة: ' . implode(', ', $allowed_types)];
    }
    
    // Additional security checks
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['valid' => false, 'message' => 'ملف غير صالح'];
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mime_types = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt' => 'text/plain'
    ];
    
    if (isset($allowed_mime_types[$file_extension]) && 
        $mime_type !== $allowed_mime_types[$file_extension]) {
        return ['valid' => false, 'message' => 'نوع الملف غير متطابق مع الامتداد'];
    }
    
    return ['valid' => true];
}

// Function to upload file and save to database
function upload_document($file, $related_type = null, $related_id = null, $description = '') {
    try {
        // Validate and upload file
        $upload_result = handle_file_upload($file);
        if (!$upload_result['success']) {
            return $upload_result;
        }
        
        // Save to database
        $db = get_db();
        
        $document_data = [
            'filename' => $upload_result['filename'],
            'original_name' => $upload_result['original_name'],
            'file_path' => $upload_result['file_path'],
            'file_size' => $upload_result['file_size'],
            'file_type' => $upload_result['file_type'],
            'description' => $description,
            'related_type' => $related_type,
            'related_id' => $related_id,
            'uploaded_by' => get_current_user_id()
        ];

        // compute sha256 of stored file for integrity
        try {
            if (file_exists($upload_result['file_path'])) {
                $sha = hash_file('sha256', $upload_result['file_path']);
                $document_data['sha256'] = $sha;
            }
        } catch (Exception $e) {
            error_log('SHA256 compute failed: ' . $e->getMessage());
        }

        $document_id = $db->insert('documents', $document_data);
        
        if ($document_id) {
            // Log the upload
            log_audit('document_uploaded', 'documents', $document_id, 
                "Document uploaded: {$upload_result['original_name']} for {$related_type}:{$related_id}");
            
            return [
                'success' => true,
                'document_id' => $document_id,
                'filename' => $upload_result['filename'],
                'original_name' => $upload_result['original_name'],
                'file_size' => $upload_result['file_size']
            ];
        }
        
        return ['success' => false, 'message' => 'فشل في حفظ معلومات الملف'];
        
    } catch (Exception $e) {
        error_log("Upload document error: " . $e->getMessage());
        return ['success' => false, 'message' => 'حدث خطأ أثناء رفع الملف'];
    }
}

// Function to delete uploaded file
function delete_uploaded_file($document_id) {
    try {
        $db = get_db();
        
        // Get document info
        $sql = "SELECT * FROM documents WHERE id = ?";
        $document = $db->fetch($sql, [$document_id]);
        
        if (!$document) {
            return ['success' => false, 'message' => 'الملف غير موجود'];
        }
        
        // Check permission
        if (!can_access_record('documents', $document_id, 'delete')) {
            return ['success' => false, 'message' => 'ليس لديك صلاحية لحذف هذا الملف'];
        }
        
        // Delete physical file
        if (file_exists($document['file_path'])) {
            unlink($document['file_path']);
        }
        
        // Delete from database
        $deleted = $db->delete('documents', 'id = ?', [$document_id]);
        
        if ($deleted) {
            log_audit('document_deleted', 'documents', $document_id, 
                "Document deleted: {$document['original_name']}");
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => 'فشل في حذف الملف'];
        
    } catch (Exception $e) {
        error_log("Delete file error: " . $e->getMessage());
        return ['success' => false, 'message' => 'حدث خطأ أثناء حذف الملف'];
    }
}

// Function to get document info
function get_document($document_id) {
    try {
        $db = get_db();
        
        $sql = "SELECT d.*, u.full_name as uploader_name 
                FROM documents d 
                JOIN users u ON d.uploaded_by = u.id 
                WHERE d.id = ?";
        
        return $db->fetch($sql, [$document_id]);
        
    } catch (Exception $e) {
        error_log("Get document error: " . $e->getMessage());
        return null;
    }
}

// Function to get documents list
function get_documents($related_type = null, $related_id = null, $page = 1, $per_page = null) {
    try {
        $db = get_db();
        
        $sql = "SELECT d.*, u.full_name as uploader_name 
                FROM documents d 
                JOIN users u ON d.uploaded_by = u.id";
        
        $params = [];
        $where_clauses = [];
        
        if ($related_type) {
            $where_clauses[] = "d.related_type = ?";
            $params[] = $related_type;
        }
        
        if ($related_id) {
            $where_clauses[] = "d.related_id = ?";
            $params[] = $related_id;
        }
        
        // Add user permissions
        if (get_current_user_role() !== 'admin') {
            $where_clauses[] = "d.uploaded_by = ?";
            $params[] = get_current_user_id();
        }
        
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }
        
        $sql .= " ORDER BY d.created_at DESC";
        
        return paginate($sql, $params, $page, $per_page);
        
    } catch (Exception $e) {
        error_log("Get documents error: " . $e->getMessage());
        return ['data' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0];
    }
}

// Function to download file
function download_file($document_id) {
    try {
        $document = get_document($document_id);
        
        if (!$document) {
            return ['success' => false, 'message' => 'الملف غير موجود'];
        }
        
        // Check permission
        if (!can_access_record('documents', $document_id, 'view')) {
            return ['success' => false, 'message' => 'ليس لديك صلاحية لتحميل هذا الملف'];
        }
        
        // Check if file exists
        if (!file_exists($document['file_path'])) {
            return ['success' => false, 'message' => 'الملف غير موجود على الخادم'];
        }
        
        // Log download
        log_audit('document_downloaded', 'documents', $document_id, 
            "Document downloaded: {$document['original_name']}");
        
        return [
            'success' => true,
            'file_path' => $document['file_path'],
            'original_name' => $document['original_name'],
            'file_type' => $document['file_type']
        ];
        
    } catch (Exception $e) {
        error_log("Download file error: " . $e->getMessage());
        return ['success' => false, 'message' => 'حدث خطأ أثناء تحميل الملف'];
    }
}

// Function to serve file for download
function serve_file($file_path, $original_name, $file_type) {
    if (!file_exists($file_path)) {
        header('HTTP/1.0 404 Not Found');
        exit;
    }
    
    // Get file size
    $file_size = filesize($file_path);
    
    // Set headers
    header('Content-Type: ' . $file_type);
    header('Content-Disposition: attachment; filename="' . $original_name . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Read file and output
    readfile($file_path);
    exit;
}

// Function to create upload directory if needed
function ensure_upload_directory($subdirectory = '') {
    $upload_path = UPLOAD_PATH;
    
    if (!empty($subdirectory)) {
        $upload_path .= $subdirectory . '/';
    }
    
    if (!is_dir($upload_path)) {
        mkdir($upload_path, 0755, true);
    }
    
    return $upload_path;
}

// Function to get file icon based on type
function get_file_icon($file_type, $filename = '') {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // Image files
    if (strpos($file_type, 'image/') === 0) {
        return 'fas fa-image text-green-500';
    }
    
    // Document files
    switch ($extension) {
        case 'pdf':
            return 'fas fa-file-pdf text-red-500';
        case 'doc':
        case 'docx':
            return 'fas fa-file-word text-blue-500';
        case 'xls':
        case 'xlsx':
            return 'fas fa-file-excel text-green-500';
        case 'txt':
            return 'fas fa-file-alt text-gray-500';
        default:
            return 'fas fa-file text-gray-400';
    }
}

// Function to format file size for display
function format_file_size_display($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>