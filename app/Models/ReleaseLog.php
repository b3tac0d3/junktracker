<?php

declare(strict_types=1);

namespace App\Models;

final class ReleaseLog
{
    private const DEFAULT_LIMIT = 10;

    private const RELEASES_DIR = 'docs/releases';

    /**
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = self::DEFAULT_LIMIT): array
    {
        $all = self::allIndexed();
        usort($all, static function (array $a, array $b): int {
            return version_compare((string) ($b['version_sort'] ?? '0'), (string) ($a['version_sort'] ?? '0'));
        });

        return array_slice($all, 0, max(1, min($limit, 50)));
    }

    public static function findByVersion(string $version): ?array
    {
        $version = self::normalizeVersion($version);
        if ($version === '') {
            return null;
        }

        foreach (self::allIndexed() as $release) {
            if (strcasecmp((string) ($release['version'] ?? ''), $version) === 0) {
                return $release;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function allIndexed(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $dir = base_path(self::RELEASES_DIR);
        if (!is_dir($dir)) {
            $cache = [];
            return $cache;
        }

        $files = glob($dir . '/live-*.md') ?: [];
        $releases = [];
        foreach ($files as $file) {
            if (!is_string($file) || !is_file($file)) {
                continue;
            }
            $parsed = self::parseFile($file);
            if ($parsed !== null) {
                $releases[] = $parsed;
            }
        }

        $cache = $releases;
        return $cache;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function parseFile(string $path): ?array
    {
        $basename = basename($path, '.md');
        if (!preg_match('/^live-(.+)$/i', $basename, $matches)) {
            return null;
        }

        $version = self::normalizeVersion((string) ($matches[1] ?? ''));
        if ($version === '') {
            return null;
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $lines = preg_split('/\R/', $raw) ?: [];
        $date = self::extractDate($lines);
        $patchOn = self::extractPatchOn($raw);
        $highlights = self::extractSectionBullets($raw, 'Highlights');
        $migrations = self::extractSectionBody($raw, 'Database migrations in this release');
        $opsNotes = self::extractSectionBody($raw, 'Ops notes');

        $items = self::categorizeItems($highlights);
        $counts = [
            'updates' => 0,
            'fixes' => 0,
            'patches' => 0,
        ];
        foreach ($items as $item) {
            $type = (string) ($item['type'] ?? 'update');
            if ($type === 'fix') {
                $counts['fixes']++;
            } elseif ($type === 'patch') {
                $counts['patches']++;
            } else {
                $counts['updates']++;
            }
        }

        $summary = '';
        if ($items !== []) {
            $summary = (string) ($items[0]['text'] ?? '');
        }

        return [
            'version' => $version,
            'version_sort' => self::versionSortKey($version),
            'date' => $date,
            'is_patch' => $patchOn !== '',
            'patch_on' => $patchOn,
            'summary' => $summary,
            'items' => $items,
            'counts' => $counts,
            'migrations' => $migrations,
            'ops_notes' => $opsNotes,
            'source_file' => basename($path),
        ];
    }

    /**
     * @param list<string> $lines
     */
    private static function extractDate(array $lines): string
    {
        foreach ($lines as $line) {
            if (preg_match('/^Date:\s*(\d{4}-\d{2}-\d{2})/i', trim($line), $matches) === 1) {
                return (string) ($matches[1] ?? '');
            }
        }

        return '';
    }

    private static function extractPatchOn(string $raw): string
    {
        if (preg_match('/^Patch on \[([^\]]+)\]/mi', $raw, $matches) !== 1) {
            return '';
        }

        $linked = trim((string) ($matches[1] ?? ''));
        if (preg_match('/live-([^\]]+\.md)/i', $linked, $fileMatch) === 1) {
            return self::normalizeVersion(str_replace('.md', '', (string) ($fileMatch[1] ?? '')));
        }

        return self::normalizeVersion($linked);
    }

    /**
     * @return list<string>
     */
    private static function extractSectionBullets(string $raw, string $sectionTitle): array
    {
        $body = self::extractSectionBody($raw, $sectionTitle);
        if ($body === '') {
            return [];
        }

        $bullets = [];
        foreach (preg_split('/\R/', $body) as $line) {
            $line = trim((string) $line);
            if ($line === '' || !str_starts_with($line, '- ')) {
                continue;
            }
            $bullets[] = substr($line, 2);
        }

        return $bullets;
    }

    private static function extractSectionBody(string $raw, string $sectionTitle): string
    {
        $pattern = '/^##\s+' . preg_quote($sectionTitle, '/') . '\s*\R(.*?)(?=^##\s|\z)/ms';
        if (preg_match($pattern, $raw, $matches) !== 1) {
            return '';
        }

        return trim((string) ($matches[1] ?? ''));
    }

    /**
     * @param list<string> $bullets
     * @return list<array{type: string, text: string, label: string}>
     */
    private static function categorizeItems(array $bullets): array
    {
        $items = [];
        foreach ($bullets as $bullet) {
            $text = release_note_plain_text($bullet);
            if ($text === '') {
                continue;
            }
            $type = self::detectItemType($bullet, $text);
            $items[] = [
                'type' => $type,
                'text' => $text,
                'label' => match ($type) {
                    'fix' => 'Bug fix',
                    'patch' => 'Patch',
                    default => 'Update',
                },
            ];
        }

        return $items;
    }

    private static function detectItemType(string $rawBullet, string $plainText): string
    {
        $lower = strtolower($plainText);
        if (preg_match('/\b(hotfix|fix(?:es|ed)?|corrects|resolved|500 fix)\b/', $lower) === 1) {
            return 'fix';
        }
        if (preg_match('/\bpatch\b/', strtolower($rawBullet)) === 1 && preg_match('/\bfix\b/', $lower) === 1) {
            return 'patch';
        }

        return 'update';
    }

    private static function normalizeVersion(string $version): string
    {
        return trim(str_replace(['live-', '.md'], '', $version));
    }

    private static function versionSortKey(string $version): string
    {
        $normalized = strtolower(trim($version));
        if ($normalized === '') {
            return '0';
        }

        if (str_ends_with($normalized, '-beta')) {
            $core = substr($normalized, 0, -5);
            return $core . '-beta';
        }

        return $normalized;
    }
}
