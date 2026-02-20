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
        $linkType = Attachment::normalizeLinkType((string) ($_POST['link_type'] ?? ''));
        $linkId = $this->toIntOrNull($_POST['link_id'] ?? null) ?? 0;
        $returnPath = $this->returnPath('/');

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

        $file = $_FILES['attachment_file'] ?? null;
        if (!is_array($file) || empty($file['name'])) {
            flash('error', 'Select a file to upload.');
            redirect($returnPath);
        }

        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            flash('error', 'Upload failed. Please try again.');
            redirect($returnPath);
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            flash('error', 'File exceeds upload limit (20 MB).');
            redirect($returnPath);
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            flash('error', 'Uploaded file is invalid.');
            redirect($returnPath);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? (string) (finfo_file($finfo, $tmpPath) ?: '') : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        $allowedExtensions = self::ALLOWED_MIME_EXTENSIONS[$mime] ?? null;
        if ($allowedExtensions === null) {
            flash('error', 'File type is not supported.');
            redirect($returnPath);
        }

        $originalName = $this->sanitizeFileName((string) ($file['name'] ?? 'file'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            $extension = $allowedExtensions[0];
        }

        $root = Attachment::storageRoot();
        Attachment::ensureStorageRoot();

        $subDir = $linkType . '/' . date('Y') . '/' . date('m');
        $absoluteDir = $root . '/' . $subDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            flash('error', 'Attachment storage is not writable.');
            redirect($returnPath);
        }

        try {
            $randomSuffix = bin2hex(random_bytes(6));
        } catch (\Throwable) {
            $randomSuffix = substr(sha1(uniqid('', true)), 0, 12);
        }

        $storedName = $linkType . '-' . $linkId . '-' . date('YmdHis') . '-' . $randomSuffix . '.' . $extension;
        $absolutePath = $absoluteDir . '/' . $storedName;

        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            flash('error', 'Could not save uploaded file.');
            redirect($returnPath);
        }

        $tag = Attachment::normalizeTag((string) ($_POST['tag'] ?? 'other'));
        $note = trim((string) ($_POST['note'] ?? ''));

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

        log_user_action(
            'attachment_uploaded',
            'attachments',
            $attachmentId,
            'Uploaded attachment for ' . $linkType . ' #' . $linkId . '.',
            'File: ' . $originalName . ' | Tag: ' . $tag
        );

        flash('success', 'Attachment uploaded.');
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

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) filesize($absolutePath));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
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
        $returnTo = trim((string) ($_POST['return_to'] ?? $_GET['return_to'] ?? ''));
        if ($returnTo === '' || !str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return $fallback;
        }

        return $returnTo;
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
