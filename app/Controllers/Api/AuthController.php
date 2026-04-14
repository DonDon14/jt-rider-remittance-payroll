<?php

namespace App\Controllers\Api;

use App\Models\ApiTokenModel;
use App\Models\RiderModel;
use App\Models\UserModel;

class AuthController extends BaseApiController
{
    private const DEFAULT_TOKEN_TTL_HOURS = 168;
    private const ADMIN_RECOVERY_KEY_ENV = 'auth.adminRecoveryKey';
    private const DEFAULT_LOGIN_MAX_ATTEMPTS = 5;
    private const DEFAULT_LOGIN_LOCK_SECONDS = 900;

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
        $lockRemainingSeconds = $this->remainingLoginLockSeconds($username);
        if ($lockRemainingSeconds > 0) {
            return $this->response->setStatusCode(429)->setJSON([
                'status' => 'error',
                'message' => 'Too many failed login attempts. Try again later.',
                'retry_after_seconds' => $lockRemainingSeconds,
            ]);
        }

        $userModel = new UserModel();
        $user = $userModel->where('username', $username)->first();
        if (! $user || ! (bool) ($user['is_active'] ?? true) || ! password_verify($password, (string) $user['password_hash'])) {
            $this->recordFailedLoginAttempt($username);

            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Invalid username or password.',
            ]);
        }

        $this->clearFailedLoginAttempts($username);

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

        if (! $this->isSelfServiceForgotPasswordEnabled() && ($user['role'] ?? '') !== 'admin') {
            return $this->response->setStatusCode(403)->setJSON([
                'status' => 'error',
                'message' => 'Self-service reset is disabled. Contact admin for password reset.',
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

        $responseData = [
            'message' => 'Temporary password issued. Change it immediately after login.',
            'username' => (string) $user['username'],
            'requires_password_change' => true,
        ];
        if ($this->shouldExposeTemporaryPassword()) {
            $responseData['temporary_password'] = $temporaryPassword;
        }

        return $this->success($responseData);
    }

    private function getApiTokenTtlHours(): int
    {
        $ttlHours = (int) env('auth.apiTokenTtlHours', self::DEFAULT_TOKEN_TTL_HOURS);

        return max(1, $ttlHours);
    }

    private function getLoginMaxAttempts(): int
    {
        $maxAttempts = (int) env('auth.loginMaxAttempts', self::DEFAULT_LOGIN_MAX_ATTEMPTS);

        return max(1, $maxAttempts);
    }

    private function getLoginLockSeconds(): int
    {
        $lockSeconds = (int) env('auth.loginLockSeconds', self::DEFAULT_LOGIN_LOCK_SECONDS);

        return max(60, $lockSeconds);
    }

    private function loginAttemptCacheKey(string $username): string
    {
        $ip = (string) $this->request->getIPAddress();

        return 'auth:api:login-attempts:' . sha1(strtolower(trim($username)) . '|' . $ip);
    }

    private function remainingLoginLockSeconds(string $username): int
    {
        $state = cache($this->loginAttemptCacheKey($username));
        if (! is_array($state)) {
            return 0;
        }

        $lockedUntil = (int) ($state['locked_until'] ?? 0);
        if ($lockedUntil <= time()) {
            return 0;
        }

        return $lockedUntil - time();
    }

    private function recordFailedLoginAttempt(string $username): void
    {
        $key = $this->loginAttemptCacheKey($username);
        $state = cache($key);
        if (! is_array($state)) {
            $state = ['attempts' => 0, 'locked_until' => 0];
        }

        $state['attempts'] = (int) ($state['attempts'] ?? 0) + 1;
        if ($state['attempts'] >= $this->getLoginMaxAttempts()) {
            $state['locked_until'] = time() + $this->getLoginLockSeconds();
            $state['attempts'] = 0;
        }

        cache()->save($key, $state, $this->getLoginLockSeconds() + 60);
    }

    private function clearFailedLoginAttempts(string $username): void
    {
        cache()->delete($this->loginAttemptCacheKey($username));
    }

    private function shouldExposeTemporaryPassword(): bool
    {
        $value = env('auth.exposeTemporaryPassword', ENVIRONMENT !== 'production');
        if (is_bool($value)) {
            return $value;
        }

        return filter_var((string) $value, FILTER_VALIDATE_BOOL);
    }

    private function isSelfServiceForgotPasswordEnabled(): bool
    {
        $mode = strtolower(trim((string) env('auth.forgotPasswordMode', ENVIRONMENT === 'production' ? 'admin_only' : 'self_service')));

        return $mode === 'self_service';
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
