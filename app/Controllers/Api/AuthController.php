<?php

namespace App\Controllers\Api;

use App\Models\ApiTokenModel;
use App\Models\RiderModel;
use App\Models\UserModel;

class AuthController extends BaseApiController
{
    public function login()
    {
        $payload = $this->request->getJSON(true);
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
        (new ApiTokenModel())->insert([
            'user_id' => (int) $user['id'],
            'token_hash' => hash('sha256', $plainToken),
            'token_name' => $deviceName !== '' ? $deviceName : 'mobile',
        ]);

        return $this->success([
            'token' => $plainToken,
            'token_type' => 'Bearer',
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
}
