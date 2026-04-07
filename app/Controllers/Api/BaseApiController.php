<?php

namespace App\Controllers\Api;

use App\Models\ApiTokenModel;
use App\Models\RiderModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

abstract class BaseApiController extends \App\Controllers\BaseController
{
    protected ?array $apiUser = null;
    protected ?array $apiToken = null;

    protected function failUnauthorized(string $message = 'Unauthorized'): ResponseInterface
    {
        return $this->response->setStatusCode(401)->setJSON([
            'status' => 'error',
            'message' => $message,
        ]);
    }

    protected function failValidation(array $errors): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON([
            'status' => 'error',
            'errors' => $errors,
        ]);
    }

    protected function success(array $data = [], int $statusCode = 200): ResponseInterface
    {
        return $this->response->setStatusCode($statusCode)->setJSON([
            'status' => 'ok',
            'data' => $data,
        ]);
    }

    protected function successList(array $items, int $page, int $perPage, int $total): ResponseInterface
    {
        return $this->success([
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'count' => count($items),
                'has_more' => ($page * $perPage) < $total,
            ],
        ]);
    }

    protected function getPagination(): array
    {
        $page = max(1, (int) $this->request->getGet('page'));
        $perPage = (int) $this->request->getGet('per_page');
        $perPage = max(1, min(100, $perPage > 0 ? $perPage : 20));

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    protected function requireApiUser(?string $role = null): array|ResponseInterface
    {
        if ($this->apiUser !== null) {
            if ($role !== null && ($this->apiUser['role'] ?? null) !== $role) {
                return $this->failUnauthorized('This token is not allowed to access that resource.');
            }

            return $this->apiUser;
        }

        $header = trim((string) $this->request->getHeaderLine('Authorization'));
        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $this->failUnauthorized('Missing bearer token.');
        }

        $plainToken = trim((string) ($matches[1] ?? ''));
        if ($plainToken === '') {
            return $this->failUnauthorized('Invalid bearer token.');
        }

        $token = (new ApiTokenModel())
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $token) {
            return $this->failUnauthorized('API token not found.');
        }

        if (! empty($token['expires_at']) && strtotime((string) $token['expires_at']) < time()) {
            return $this->failUnauthorized('API token has expired.');
        }

        $user = (new UserModel())->find((int) ($token['user_id'] ?? 0));
        if (! $user || ! (bool) ($user['is_active'] ?? true)) {
            return $this->failUnauthorized('User account is inactive.');
        }

        $rider = null;
        if (($user['role'] ?? '') === 'rider') {
            $riderId = (int) ($user['rider_id'] ?? 0);
            if ($riderId > 0) {
                $rider = (new RiderModel())->find($riderId);
            }
        }

        (new ApiTokenModel())->update((int) $token['id'], [
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);

        $this->apiToken = $token;
        $this->apiUser = array_merge($user, [
            'resolved_rider' => $rider,
        ]);

        if ($role !== null && ($this->apiUser['role'] ?? null) !== $role) {
            return $this->failUnauthorized('This token is not allowed to access that resource.');
        }

        return $this->apiUser;
    }

    protected function getCurrentApiToken(): ?array
    {
        return $this->apiToken;
    }
}
