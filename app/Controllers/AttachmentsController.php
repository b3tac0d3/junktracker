<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Attachment;
use App\Models\Client;
use App\Models\Job;
use App\Models\Prospect;
use App\Models\Sale;
use Core\Controller;

final class AttachmentsController extends Controller
{
    private const MAX_BYTES = 20971520; // 20 MB

    private const ALLOWED_MIME_EXTENSIONS = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif'],
        'text/plain' => ['txt'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
    ];

    public function upload(): void
    {
        $returnPath = $this->returnPath('/');

        if ($this->requestExceededPostLimit()) {
            $postMaxBytes = $this->iniSizeToBytes((string) ini_get('post_max_size'));
            $uploadMaxBytes = $this->iniSizeToBytes((string) ini_get('upload_max_filesize'));
            $effectiveLimit = $postMaxBytes > 0 && $uploadMaxBytes > 0
                ? min($postMaxBytes, $uploadMaxBytes)
                : max($postMaxBytes, $uploadMaxBytes);

            $limitLabel = $effectiveLimit > 0
                ? $this->formatBytes($effectiveLimit)
                : 'the server upload limit';

            flash('error', 'Upload failed: total files exceed ' . $limitLabel . '. Try fewer/smaller files.');
            redirect($returnPath);
        }

        $linkType = Attachment::normalizeLinkType((string) ($_POST['link_type'] ?? ''));
        $linkId = $this->toIntOrNull($_POST['link_id'] ?? null) ?? 0;

        if ($linkType === null || $linkId <= 0) {
            flash('error', 'Invalid attachment target.');
            redirect($returnPath);
        }

        $module = $this->moduleForLinkType($linkType);
        require_permission($module, 'edit');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect($returnPath);
        }

        if (!$this->linkExists($linkType, $linkId)) {
            flash('error', 'Linked record no longer exists.');
            redirect($returnPath);
        }

        $uploadedFiles = $this->collectUploadedFiles($_FILES['attachment_file'] ?? $_FILES['attachment_file[]'] ?? null);
        if (empty($uploadedFiles)) {
            flash('error', 'Select a file to upload.');
            redirect($returnPath);
        }

        $tag = Attachment::normalizeTag((string) ($_POST['tag'] ?? 'other'));
        $note = trim((string) ($_POST['note'] ?? ''));
        $imagesOnly = !empty($_POST['images_only']);

        $savedCount = 0;
        $errors = [];
        foreach ($uploadedFiles as $file) {
            $result = $this->saveUploadedAttachment($file, $linkType, $linkId, $tag, $note, $imagesOnly);
            if (!empty($result['error'])) {
                $errors[] = (string) $result['error'];
                continue;
            }

            $savedCount++;
            $attachmentId = (int) ($result['attachment_id'] ?? 0);
            $originalName = (string) ($result['original_name'] ?? 'file');
            log_user_action(
                'attachment_uploaded',
                'attachments',
                $attachmentId,
                'Uploaded attachment for ' . $linkType . ' #' . $linkId . '.',
                'File: ' . $originalName . ' | Tag: ' . $tag
            );
        }

        if ($savedCount > 0) {
            $message = $savedCount === 1 ? 'Attachment uploaded.' : ($savedCount . ' attachments uploaded.');
            flash('success', $message);
        } elseif (!empty($errors)) {
            flash('error', $errors[0]);
        } else {
            flash('error', 'Upload failed. Please try again.');
        }

        if (!empty($errors) && $savedCount > 0) {
            flash('error', 'Some files were skipped: ' . $errors[0]);
        }

        redirect($returnPath);
    }

    public function download(array $params): void
    {
        $attachmentId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($attachmentId <= 0) {
            redirect('/');
        }

        $attachment = Attachment::findById($attachmentId);
        if (!$attachment || !empty($attachment['deleted_at'])) {
            $this->renderNotFound();
            return;
        }

        $linkType = Attachment::normalizeLinkType((string) ($attachment['link_type'] ?? ''));
        $linkId = (int) ($attachment['link_id'] ?? 0);
        if ($linkType === null || $linkId <= 0) {
            $this->renderNotFound();
            return;
        }

        $module = $this->moduleForLinkType($linkType);
        require_permission($module, 'view');

        $storagePath = trim((string) ($attachment['storage_path'] ?? ''));
        if ($storagePath === '') {
            $this->renderNotFound();
            return;
        }

        $root = realpath(Attachment::storageRoot());
        $absolutePath = realpath(Attachment::storageRoot() . '/' . ltrim($storagePath, '/'));

        if ($root === false || $absolutePath === false || !str_starts_with($absolutePath, $root) || !is_file($absolutePath)) {
            $this->renderNotFound();
            return;
        }

        $downloadName = $this->sanitizeFileName((string) ($attachment['original_name'] ?? 'attachment'));
        $mimeType = trim((string) ($attachment['mime_type'] ?? 'application/octet-stream'));
        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }
        $inline = isset($_GET['inline']) && (string) ($_GET['inline'] ?? '') === '1';
        $disposition = $inline ? 'inline' : 'attachment';

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) filesize($absolutePath));
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $downloadName) . '"');
        readfile($absolutePath);
        exit;
    }

    public function delete(array $params): void
    {
        $attachmentId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($attachmentId <= 0) {
            redirect('/');
        }

        $attachment = Attachment::findById($attachmentId);
        if (!$attachment || !empty($attachment['deleted_at'])) {
            $this->renderNotFound();
            return;
        }

        $linkType = Attachment::normalizeLinkType((string) ($attachment['link_type'] ?? ''));
        $linkId = (int) ($attachment['link_id'] ?? 0);
        if ($linkType === null || $linkId <= 0) {
            $this->renderNotFound();
            return;
        }

        $module = $this->moduleForLinkType($linkType);
        require_permission($module, 'edit');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect($this->returnPath($this->defaultReturnPath($linkType, $linkId)));
        }

        Attachment::softDelete($attachmentId, auth_user_id());
        log_user_action('attachment_deleted', 'attachments', $attachmentId, 'Deleted attachment #' . $attachmentId . '.');

        flash('success', 'Attachment removed.');
        redirect($this->returnPath($this->defaultReturnPath($linkType, $linkId)));
    }

    public function updateLabel(array $params): void
    {
        $attachmentId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($attachmentId <= 0) {
            redirect('/');
        }

        $attachment = Attachment::findById($attachmentId);
        if (!$attachment || !empty($attachment['deleted_at'])) {
            $this->renderNotFound();
            return;
        }

        $linkType = Attachment::normalizeLinkType((string) ($attachment['link_type'] ?? ''));
        $linkId = (int) ($attachment['link_id'] ?? 0);
        if ($linkType === null || $linkId <= 0) {
            $this->renderNotFound();
            return;
        }

        $module = $this->moduleForLinkType($linkType);
        require_permission($module, 'edit');

        $returnPath = $this->returnPath($this->defaultReturnPath($linkType, $linkId));
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect($returnPath);
        }

        $label = trim((string) ($_POST['label'] ?? ''));
        if (mb_strlen($label) > 255) {
            $label = mb_substr($label, 0, 255);
        }

        Attachment::updateNote($attachmentId, $label !== '' ? $label : null, auth_user_id());
        log_user_action(
            'attachment_label_updated',
            'attachments',
            $attachmentId,
            'Updated photo label for attachment #' . $attachmentId . '.'
        );

        flash('success', 'Photo label updated.');
        redirect($returnPath);
    }

    private function moduleForLinkType(string $linkType): string
    {
        return match ($linkType) {
            'job' => 'jobs',
            'client' => 'clients',
            'prospect' => 'prospects',
            'sale' => 'sales',
            default => 'dashboard',
        };
    }

    private function linkExists(string $linkType, int $linkId): bool
    {
        return match ($linkType) {
            'job' => Job::findById($linkId) !== null,
            'client' => Client::findById($linkId) !== null,
            'prospect' => Prospect::findById($linkId) !== null,
            'sale' => Sale::findById($linkId) !== null,
            default => false,
        };
    }

    private function defaultReturnPath(string $linkType, int $linkId): string
    {
        return match ($linkType) {
            'job' => '/jobs/' . $linkId,
            'client' => '/clients/' . $linkId,
            'prospect' => '/prospects/' . $linkId,
            'sale' => '/sales/' . $linkId,
            default => '/',
        };
    }

    private function returnPath(string $fallback): string
    {
        $returnTo = $this->normalizeReturnPath($_POST['return_to'] ?? $_GET['return_to'] ?? null);
        if ($returnTo !== null) {
            return $returnTo;
        }

        $referer = $this->normalizeReturnPath($_SERVER['HTTP_REFERER'] ?? null);
        if ($referer !== null) {
            return $referer;
        }

        return $fallback;
    }

    private function sanitizeFileName(string $fileName): string
    {
        $value = trim($fileName);
        if ($value === '') {
            return 'attachment';
        }

        $value = str_replace(["\r", "\n", "\t", '/','\\'], ' ', $value);
        $value = preg_replace('/[^A-Za-z0-9._ -]/', '', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        $value = trim($value);

        return $value !== '' ? $value : 'attachment';
    }

    private function toIntOrNull(mixed $value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function collectUploadedFiles(mixed $raw): array
    {
        if (!is_array($raw) || !array_key_exists('name', $raw)) {
            return [];
        }

        $name = $raw['name'] ?? null;
        if (!is_array($name)) {
            return [$raw];
        }

        $files = [];
        foreach ($name as $index => $singleName) {
            $files[] = [
                'name' => $singleName,
                'type' => $raw['type'][$index] ?? '',
                'tmp_name' => $raw['tmp_name'][$index] ?? '',
                'error' => $raw['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $raw['size'][$index] ?? 0,
            ];
        }

        return $files;
    }

    private function normalizeReturnPath(mixed $raw): ?string
    {
        $value = trim((string) ($raw ?? ''));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $value) === 1) {
            $parts = parse_url($value);
            if (!is_array($parts)) {
                return null;
            }

            $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
            $urlHost = strtolower((string) ($parts['host'] ?? ''));
            if ($urlHost !== '' && $requestHost !== '' && $urlHost !== $requestHost) {
                return null;
            }

            $path = (string) ($parts['path'] ?? '/');
            $query = trim((string) ($parts['query'] ?? ''));
            $value = $path . ($query !== '' ? '?' . $query : '');
        }

        if (!str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return null;
        }

        $basePath = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($basePath !== '' && $basePath !== '/' && str_starts_with($value, $basePath . '/')) {
            $value = substr($value, strlen($basePath));
            if ($value === false || $value === '') {
                $value = '/';
            }
        }

        if (!str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return null;
        }

        return $value;
    }

    private function requestExceededPostLimit(): bool
    {
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        if ($contentLength <= 0) {
            return false;
        }

        $postMaxBytes = $this->iniSizeToBytes((string) ini_get('post_max_size'));
        if ($postMaxBytes <= 0 || $contentLength <= $postMaxBytes) {
            return false;
        }

        return empty($_POST) && empty($_FILES);
    }

    private function iniSizeToBytes(string $size): int
    {
        $value = trim($size);
        if ($value === '') {
            return 0;
        }

        if (!preg_match('/^\s*([0-9]+(?:\.[0-9]+)?)\s*([kmgtp]?)\s*$/i', $value, $matches)) {
            return (int) $value;
        }

        $number = (float) ($matches[1] ?? 0);
        $suffix = strtolower((string) ($matches[2] ?? ''));
        $multiplier = match ($suffix) {
            'k' => 1024,
            'm' => 1024 ** 2,
            'g' => 1024 ** 3,
            't' => 1024 ** 4,
            'p' => 1024 ** 5,
            default => 1,
        };

        return (int) round($number * $multiplier);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $bytes;
        $unit = 0;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $size >= 10 || $unit === 0 ? 0 : 1) . ' ' . $units[$unit];
    }

    private function saveUploadedAttachment(
        array $file,
        string $linkType,
        int $linkId,
        string $tag,
        string $note,
        bool $imagesOnly
    ): array {
        $originalInputName = trim((string) ($file['name'] ?? ''));
        if ($originalInputName === '') {
            return ['error' => 'One of the selected files is missing a file name.'];
        }

        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            return ['error' => $this->uploadErrorMessage($uploadError, $originalInputName)];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return ['error' => 'File exceeds upload limit (20 MB): ' . $originalInputName . '.'];
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return ['error' => 'Uploaded file is invalid: ' . $originalInputName . '.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? (string) (finfo_file($finfo, $tmpPath) ?: '') : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        $allowedExtensions = self::ALLOWED_MIME_EXTENSIONS[$mime] ?? null;
        if ($allowedExtensions === null) {
            return ['error' => 'File type is not supported: ' . $originalInputName . '.'];
        }
        if ($imagesOnly && !str_starts_with($mime, 'image/')) {
            return ['error' => 'Only image files are allowed in this section: ' . $originalInputName . '.'];
        }

        $originalName = $this->sanitizeFileName($originalInputName);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            $extension = $allowedExtensions[0];
        }

        $root = Attachment::storageRoot();
        Attachment::ensureStorageRoot();

        $subDir = $linkType . '/' . date('Y') . '/' . date('m');
        $absoluteDir = $root . '/' . $subDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            return ['error' => 'Attachment storage is not writable.'];
        }

        try {
            $randomSuffix = bin2hex(random_bytes(6));
        } catch (\Throwable) {
            $randomSuffix = substr(sha1(uniqid('', true)), 0, 12);
        }

        $storedName = $linkType . '-' . $linkId . '-' . date('YmdHis') . '-' . $randomSuffix . '.' . $extension;
        $absolutePath = $absoluteDir . '/' . $storedName;

        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            return ['error' => 'Could not save uploaded file: ' . $originalInputName . '.'];
        }

        $attachmentId = Attachment::create([
            'link_type' => $linkType,
            'link_id' => $linkId,
            'tag' => $tag,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'storage_path' => $subDir . '/' . $storedName,
            'mime_type' => $mime !== '' ? $mime : null,
            'file_size' => $size,
            'note' => $note,
        ], auth_user_id());

        return [
            'attachment_id' => $attachmentId,
            'original_name' => $originalName,
        ];
    }

    private function uploadErrorMessage(int $uploadError, string $fileName): string
    {
        return match ($uploadError) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds upload size limit: ' . $fileName . '.',
            UPLOAD_ERR_PARTIAL => 'File upload was incomplete: ' . $fileName . '.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded for ' . $fileName . '.',
            UPLOAD_ERR_NO_TMP_DIR => 'Upload temp directory is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write uploaded file: ' . $fileName . '.',
            UPLOAD_ERR_EXTENSION => 'A server extension stopped upload: ' . $fileName . '.',
            default => 'Upload failed for ' . $fileName . '.',
        };
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        if (class_exists('App\\Controllers\\ErrorController')) {
            (new ErrorController())->notFound();
            return;
        }

        echo '404 Not Found';
    }
}
