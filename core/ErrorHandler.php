<?php

declare(strict_types=1);

namespace Core;

use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler([self::class, 'handlePhpError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handlePhpError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(Throwable $exception): void
    {
        $reference = self::logThrowable($exception);
        self::renderHttpError(
            500,
            'Something went wrong',
            'The request failed. Use the reference below to trace it in logs.',
            $reference,
            $exception
        );
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
            return;
        }

        $exception = new \ErrorException(
            (string) ($error['message'] ?? 'Fatal error'),
            0,
            (int) ($error['type'] ?? E_ERROR),
            (string) ($error['file'] ?? 'unknown'),
            (int) ($error['line'] ?? 0)
        );

        $reference = self::logThrowable($exception);
        self::renderHttpError(
            500,
            'Something went wrong',
            'The request failed. Use the reference below to trace it in logs.',
            $reference,
            $exception
        );
    }

    public static function renderHttpError(
        int $status,
        string $title,
        string $message,
        ?string $reference = null,
        ?Throwable $exception = null
    ): void {
        if (!headers_sent()) {
            http_response_code($status);
        }

        $context = [
            'Request' => sprintf(
                '%s %s',
                (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)
            ),
        ];

        $userId = \auth_user_id();
        if ($userId !== null) {
            $context['User ID'] = (string) $userId;
        }

        $businessId = \current_business_id();
        if ($businessId > 0) {
            $context['Business ID'] = (string) $businessId;
        }

        if ($reference !== null && $reference !== '') {
            $context['Reference'] = $reference;
        }

        if ((bool) \config('app.debug', false) && $exception !== null) {
            $context['Debug'] = sprintf('%s in %s:%d', $exception->getMessage(), $exception->getFile(), $exception->getLine());
        }

        View::renderFile('layouts/main', [
            'pageTitle' => $title,
            'publicPage' => true,
            'viewFile' => 'app/Views/errors/http.php',
            'errorStatus' => $status,
            'errorTitle' => $title,
            'errorMessage' => $message,
            'errorContext' => $context,
        ]);
    }

    private static function logThrowable(Throwable $exception): string
    {
        $reference = date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $logDir = \base_path('storage/logs');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $payload = [
            'reference' => $reference,
            'timestamp' => date('c'),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'request_method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            'user_id' => \auth_user_id(),
            'business_id' => \current_business_id(),
            'trace' => $exception->getTraceAsString(),
        ];

        $logFile = $logDir . '/error-' . date('Y-m-d') . '.log';
        @file_put_contents($logFile, json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);

        return $reference;
    }
}
