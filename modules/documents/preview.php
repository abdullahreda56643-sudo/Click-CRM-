<?php
// Serve document for inline preview when possible
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/upload.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$doc = get_document($id);
if (!$doc) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

if (!can_access_record('documents', $id, 'view')) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$file_path = $doc['file_path'];
if (!file_exists($file_path)) {
    http_response_code(404);
    echo 'File missing';
    exit;
}

// Determine MIME type
$mime = $doc['file_type'] ?: mime_content_type($file_path);

// For known types, set inline disposition to allow browser preview when supported
// If the file is an Office document, try to convert to PDF using LibreOffice (soffice)
$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$office_exts = ['doc','docx','xls','xlsx','ppt','pptx'];

if (in_array($ext, $office_exts)) {
    // prepare preview cache
    $preview_dir = rtrim(UPLOAD_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'previews' . DIRECTORY_SEPARATOR;
    if (!is_dir($preview_dir)) mkdir($preview_dir, 0755, true);

    $pdf_name = pathinfo($doc['filename'], PATHINFO_FILENAME) . '.pdf';
    $pdf_path = $preview_dir . $pdf_name;

    // convert if missing or stale
    if (!file_exists($pdf_path) || filemtime($pdf_path) < filemtime($file_path)) {
        // locate soffice
        $possible = [
            'soffice',
            '/usr/bin/soffice',
            '/usr/bin/libreoffice',
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe'
        ];
        $soffice = null;
        foreach ($possible as $p) {
            // try which-like check
            if (DIRECTORY_SEPARATOR === '\\') {
                // on Windows, check common paths or rely on `where` command
                if (is_executable($p) || file_exists($p)) { $soffice = $p; break; }
            } else {
                // *nix
                $which = trim(shell_exec('which ' . escapeshellarg($p) . ' 2>/dev/null'));
                if ($which) { $soffice = $which; break; }
                if (is_executable($p)) { $soffice = $p; break; }
            }
        }

        if ($soffice) {
            // run conversion
            $outdir = $preview_dir;
            $in = $file_path;
            $cmd = escapeshellcmd($soffice) . ' --headless --convert-to pdf --outdir ' . escapeshellarg($outdir) . ' ' . escapeshellarg($in) . ' 2>&1';
            @set_time_limit(120);
            $output = shell_exec($cmd);
            // conversion should produce $pdf_path
        }
    }

    if (file_exists($pdf_path)) {
        $mime = 'application/pdf';
        $file_path = $pdf_path;
    }
}

// Serve the (possibly converted) file inline
header('Content-Type: ' . $mime);
// Keep original filename but inline
$disposition_name = str_replace(['"','\\'], ['',''], $doc['original_name']);
header('Content-Disposition: inline; filename="' . $disposition_name . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (ob_get_level()) ob_end_clean();
readfile($file_path);
exit;
