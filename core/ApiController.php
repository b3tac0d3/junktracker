<?php

declare(strict_types=1);

namespace Core;

abstract class ApiController extends Controller
{
    protected function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function ok(array $data = [], int $status = 200): never
    {
        $this->json(['ok' => true, 'data' => $data], $status);
    }

    /**
     * @param array<string, string> $errors
     */
    protected function fail(string $message, int $status = 400, array $errors = []): never
    {
        $payload = [
            'ok' => false,
            'error' => $message,
        ];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }
        $this->json($payload, $status);
    }

    protected function authenticate(): void
    {
        if (!api_authenticate_request()) {
            $this->fail('Unauthorized', 401);
        }
    }

    /**
     * @param list<string> $roles
     */
    protected function requireBusinessRole(array $roles): void
    {
        $this->authenticate();

        if (is_site_admin() && current_business_id() <= 0) {
            $this->fail('Choose a business workspace first.', 403);
        }

        if (current_business_id() <= 0) {
            $this->fail('Business context required.', 403);
        }

        $role = workspace_role();
        if (!in_array($role, $roles, true)) {
            $this->fail('Forbidden', 403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        if ($_POST !== []) {
            return $_POST;
        }

        return api_read_json_body();
    }
}
