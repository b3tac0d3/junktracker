<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Attachment;
use Core\Controller;

final class PhotosController extends Controller
{
    public function index(): void
    {
        $this->ensureAccess();

        $search = trim((string) ($_GET['q'] ?? ''));
        $jobId = $this->toIntOrNull($_GET['job_id'] ?? null) ?? 0;
        $tag = $this->normalizePhotoTag((string) ($_GET['tag'] ?? ''));

        $mode = 'jobs';
        $jobs = [];
        $job = null;
        $tagGroups = [];
        $photos = [];

        if ($jobId > 0) {
            $job = Attachment::jobPhotoJob($jobId);
            if ($job === null) {
                flash('error', 'Job not found.');
                redirect('/photos');
            }

            if ($tag !== null) {
                $mode = 'photos';
                $photos = Attachment::jobPhotosByTag($jobId, $tag, $search, 1200);
            } else {
                $mode = 'tags';
                $tagGroups = Attachment::jobPhotoTagGroups($jobId);
            }
        } else {
            $jobs = Attachment::jobPhotoJobs($search, 600);
        }

        $this->render('photos/index', [
            'pageTitle' => 'Photo Library',
            'mode' => $mode,
            'search' => $search,
            'selectedJob' => $job,
            'selectedTag' => $tag,
            'jobs' => $jobs,
            'tagGroups' => $tagGroups,
            'photos' => $photos,
            'pageScripts' => implode("\n", [
                '<script src="' . asset('js/photos-library.js') . '"></script>',
                '<script src="' . asset('js/job-photo-modal.js') . '"></script>',
            ]),
        ]);
    }

    public function downloadSelected(): void
    {
        $this->ensureAccess();
        $returnPath = $this->returnPath('/photos');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect($returnPath);
        }

        $ids = $_POST['attachment_ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            flash('error', 'Select at least one photo to download.');
            redirect($returnPath);
        }

        $photos = Attachment::findPhotosByIds($ids, ['job']);
        if (empty($photos)) {
            flash('error', 'No valid photos selected.');
            redirect($returnPath);
        }

        if (!class_exists(\ZipArchive::class)) {
            flash('error', 'ZIP download is not available on this server.');
            redirect($returnPath);
        }

        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (\Throwable) {
            $suffix = substr(sha1(uniqid('photos', true)), 0, 8);
        }
        $zipPath = sys_get_temp_dir() . '/junktracker-photos-' . date('Ymd-His') . '-' . $suffix . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            flash('error', 'Could not prepare the photo archive.');
            redirect($returnPath);
        }

        $nameCounts = [];
        $added = 0;
        foreach ($photos as $photo) {
            $storagePath = trim((string) ($photo['storage_path'] ?? ''));
            $absolutePath = Attachment::absoluteStoragePath($storagePath);
            if ($absolutePath === null) {
                continue;
            }

            $fileName = $this->zipEntryName($photo);
            if (isset($nameCounts[$fileName])) {
                $nameCounts[$fileName]++;
                $dot = strrpos($fileName, '.');
                if ($dot !== false) {
                    $base = substr($fileName, 0, $dot);
                    $ext = substr($fileName, $dot);
                    $fileName = $base . '-' . $nameCounts[$fileName] . $ext;
                } else {
                    $fileName .= '-' . $nameCounts[$fileName];
                }
            } else {
                $nameCounts[$fileName] = 1;
            }

            $zip->addFile($absolutePath, $fileName);
            $added++;
        }
        $zip->close();

        if ($added < 1 || !is_file($zipPath)) {
            @unlink($zipPath);
            flash('error', 'No downloadable photos were found for the selected items.');
            redirect($returnPath);
        }

        log_user_action(
            'photos_bulk_downloaded',
            'attachments',
            null,
            'Downloaded ' . $added . ' photos from photo library.'
        );

        header('Content-Type: application/zip');
        header('Content-Length: ' . (string) filesize($zipPath));
        header('Content-Disposition: attachment; filename="junktracker-photos-' . date('Ymd-His') . '.zip"');
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    private function ensureAccess(): void
    {
        if (!is_authenticated()) {
            redirect('/login');
        }

        require_permission('jobs', 'view');
    }

    private function zipEntryName(array $photo): string
    {
        $linkType = strtolower(trim((string) ($photo['link_type'] ?? 'file')));
        $linkId = (int) ($photo['link_id'] ?? 0);
        $tag = strtolower(trim((string) ($photo['tag'] ?? 'photo')));
        $original = trim((string) ($photo['original_name'] ?? 'photo'));
        $original = preg_replace('/[^A-Za-z0-9._ -]/', '', $original) ?? 'photo';
        $original = trim(preg_replace('/\s+/', ' ', $original) ?? 'photo');
        if ($original === '') {
            $original = 'photo';
        }

        return $linkType . '-' . $linkId . '-' . $tag . '-' . $original;
    }

    private function normalizePhotoTag(string $raw): ?string
    {
        $value = strtolower(trim($raw));
        if ($value === '') {
            return null;
        }

        return in_array($value, Attachment::PHOTO_TAGS, true) ? $value : null;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function returnPath(string $fallback): string
    {
        $raw = trim((string) ($_POST['return_to'] ?? $_GET['return_to'] ?? ''));
        if ($raw === '') {
            return $fallback;
        }

        if (preg_match('/^https?:\/\//i', $raw) === 1) {
            $parts = parse_url($raw);
            if (!is_array($parts)) {
                return $fallback;
            }

            $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
            $urlHost = strtolower((string) ($parts['host'] ?? ''));
            if ($urlHost !== '' && $requestHost !== '' && $urlHost !== $requestHost) {
                return $fallback;
            }

            $path = (string) ($parts['path'] ?? '/');
            $query = trim((string) ($parts['query'] ?? ''));
            $raw = $path . ($query !== '' ? '?' . $query : '');
        }

        if (!str_starts_with($raw, '/') || str_starts_with($raw, '//')) {
            return $fallback;
        }

        $basePath = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($basePath !== '' && $basePath !== '/' && str_starts_with($raw, $basePath . '/')) {
            $raw = substr($raw, strlen($basePath));
            if ($raw === false || $raw === '') {
                $raw = '/';
            }
        }

        if (!str_starts_with($raw, '/') || str_starts_with($raw, '//')) {
            return $fallback;
        }

        return $raw;
    }
}
