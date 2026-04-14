<?php

namespace App\Controllers\Api;

use App\Models\ApiTokenModel;
use App\Models\RiderModel;
use App\Models\UserModel;

class AuthController extends BaseApiController
{
    private const DEFAULT_TOKEN_TTL_HOURS = 168;
    private const ADMIN_RECOVERY_KEY_ENV = 'auth.adminRecoveryKey';

    public function login()
    {
        $payload = null;
        try {
            $payload = $this->request->getJSON(true);
        } catch (\Throwable $exception) {
            $payload = null;
        }
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $rules = [
            'username' => 'required|max_length[80]',
            'password' => 'required|max_length[255]',
            'device_name' => 'permit_empty|max_length[50]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->failValidation($this->validator->getErrors());
        }

        $username = trim((string) ($payload['username'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $deviceName = trim((string) ($payload['device_name'] ?? 'mobile'));

        $userModel = new UserModel();
        $user = $userModel->where('username', $username)->first();
        if (! $user || ! (bool) ($user['is_active'] ?? true) || ! password_verify($password, (string) $user['password_hash'])) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Invalid username or password.',
            ]);
        }

        $rider = null;
        if (($user['role'] ?? '') === 'rider') {
            $riderId = (int) ($user['rider_id'] ?? 0);
            if ($riderId <= 0) {
                return $this->response->setStatusCode(409)->setJSON([
                    'status' => 'error',
                    'message' => 'This rider account is not linked to a rider profile.',
                ]);
            }

            $rider = (new RiderModel())->find($riderId);
            if (! $rider) {
                return $this->response->setStatusCode(409)->setJSON([
                    'status' => 'error',
                    'message' => 'This rider profile no longer exists.',
                ]);
            }
        }

        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + ($this->getApiTokenTtlHours() * 3600));
        (new ApiTokenModel())->insert([
            'user_id' => (int) $user['id'],
            'token_hash' => hash('sha256', $plainToken),
            'token_name' => $deviceName !== '' ? $deviceName : 'mobile',
            'expires_at' => $expiresAt,
        ]);

        return $this->success([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
            'user' => [
                'id' => (int) $user['id'],
                'username' => (string) $user['username'],
                'role' => (string) $user['role'],
                'force_password_change' => (bool) ($user['force_password_change'] ?? false),
            ],
            'rider' => $rider ? [
                'id' => (int) $rider['id'],
                'rider_code' => (string) $rider['rider_code'],
                'name' => (string) $rider['name'],
                'commission_rate' => round((float) ($rider['commission_rate'] ?? 0), 2),
            ] : null,
        ]);
    }

    public function logout()
    {
        $user = $this->requireApiUser();
        if (! is_array($user)) {
            return $user;
        }

        $token = $this->getCurrentApiToken();
        if (! $token) {
            return $this->failUnauthorized('API token not found.');
        }

        (new ApiTokenModel())->delete((int) $token['id']);

        return $this->success([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function logoutAll()
    {
        $user = $this->requireApiUser();
        if (! is_array($user)) {
            return $user;
        }

        (new ApiTokenModel())
            ->where('user_id', (int) $user['id'])
            ->delete();

        return $this->success([
            'message' => 'All API sessions were revoked.',
        ]);
    }

    public function forgotPassword()
    {
        $payload = null;
        try {
            $payload = $this->request->getJSON(true);
        } catch (\Throwable $exception) {
            $payload = null;
        }
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $rules = [
            'username' => 'required|max_length[80]',
            'rider_code' => 'permit_empty|max_length[40]',
            'contact_number' => 'permit_empty|max_length[30]',
            'recovery_key' => 'permit_empty|max_length[255]',
        ];
        if (! $this->validateData($payload, $rules)) {
            return $this->failValidation($this->validator->getErrors());
        }

        $username = trim((string) ($payload['username'] ?? ''));
        $riderCode = strtolower(trim((string) ($payload['rider_code'] ?? '')));
        $contactNumber = preg_replace('/\D+/', '', (string) ($payload['contact_number'] ?? '')) ?? '';
        $recoveryKey = trim((string) ($payload['recovery_key'] ?? ''));

        $userModel = new UserModel();
        $user = $userModel->where('username', $username)->first();
        if (! $user || ! (bool) ($user['is_active'] ?? true)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Account not found or inactive.',
            ]);
        }

        if (($user['role'] ?? '') === 'rider') {
            $rider = $this->resolveRiderForUser($user);
            if (! $rider) {
                return $this->response->setStatusCode(409)->setJSON([
                    'status' => 'error',
                    'message' => 'Rider account is not linked to a valid profile.',
                ]);
            }

            $savedRiderCode = strtolower(trim((string) ($rider['rider_code'] ?? '')));
            $savedContactNumber = preg_replace('/\D+/', '', (string) ($rider['contact_number'] ?? '')) ?? '';
            if ($riderCode === '' || $contactNumber === '' || $riderCode !== $savedRiderCode || $contactNumber !== $savedContactNumber) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status' => 'error',
                    'message' => 'Rider verification failed. Check rider code and contact number.',
                ]);
            }
        } else {
            $adminRecoveryKey = trim((string) env(self::ADMIN_RECOVERY_KEY_ENV));
            if ($adminRecoveryKey === '') {
                return $this->response->setStatusCode(500)->setJSON([
                    'status' => 'error',
                    'message' => 'Admin recovery is not configured. Set auth.adminRecoveryKey in environment.',
                ]);
            }
            if ($recoveryKey === '' || ! hash_equals($adminRecoveryKey, $recoveryKey)) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status' => 'error',
                    'message' => 'Admin recovery key is invalid.',
                ]);
            }
        }

        $temporaryPassword = $this->generateTemporaryPassword();
        $userModel->update((int) $user['id'], [
            'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
            'force_password_change' => 1,
        ]);
        (new ApiTokenModel())->where('user_id', (int) $user['id'])->delete();

        return $this->success([
            'message' => 'Temporary password issued. Change it immediately after login.',
            'temporary_password' => $temporaryPassword,
            'username' => (string) $user['username'],
            'requires_password_change' => true,
        ]);
    }

    private function getApiTokenTtlHours(): int
    {
        $ttlHours = (int) env('auth.apiTokenTtlHours', self::DEFAULT_TOKEN_TTL_HOURS);

        return max(1, $ttlHours);
    }

    private function resolveRiderForUser(array $user): ?array
    {
        if (($user['role'] ?? '') !== 'rider') {
            return null;
        }

        $riderId = (int) ($user['rider_id'] ?? 0);
        if ($riderId <= 0) {
            return null;
        }

        return (new RiderModel())->find($riderId);
    }

    private function generateTemporaryPassword(int $length = 12): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
        $max = strlen($alphabet) - 1;
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }
}
