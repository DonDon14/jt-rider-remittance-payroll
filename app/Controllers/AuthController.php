<?php

namespace App\Controllers;

use App\Models\RiderModel;
use App\Models\UserModel;

class AuthController extends BaseController
{
    public function loginForm()
    {
        if ($this->session->get('isLoggedIn')) {
            if ($this->session->get('force_password_change')) {
                return redirect()->to('/change-password');
            }

            return redirect()->to($this->session->get('role') === 'admin' ? '/admin' : '/rider-dashboard');
        }

        return view('auth/login', [
            'title' => 'Login - J&T Rider Remittance & Payroll',
        ]);
    }

    public function login()
    {
        $rules = [
            'username' => 'required|max_length[80]',
            'password' => 'required|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        $userModel = new UserModel();
        $user = $userModel->where('username', $username)->first();
        if (! $user || ! (bool) ($user['is_active'] ?? true) || ! password_verify($password, (string) $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Invalid username or password.');
        }

        $resolvedRiderId = $this->resolveRiderId($user);
        if (($user['role'] ?? '') === 'rider' && $resolvedRiderId === null) {
            return redirect()->back()->withInput()->with('error', 'This rider login is not linked to a rider profile. Contact the admin to fix the account linkage.');
        }

        if (($user['role'] ?? '') === 'rider' && (int) ($user['rider_id'] ?? 0) !== (int) ($resolvedRiderId ?? 0)) {
            $userModel->update((int) $user['id'], ['rider_id' => $resolvedRiderId]);
            $user['rider_id'] = $resolvedRiderId;
        }

        $this->session->regenerate();
        $this->session->set([
            'user_id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'role' => (string) $user['role'],
            'rider_id' => $resolvedRiderId,
            'force_password_change' => (bool) ($user['force_password_change'] ?? false),
            'isLoggedIn' => true,
        ]);

        if ((bool) ($user['force_password_change'] ?? false)) {
            return redirect()->to('/change-password')->with('error', 'You need to change your password before continuing.');
        }

        return redirect()->to($user['role'] === 'admin' ? '/admin' : '/rider-dashboard');
    }

    public function changePasswordForm()
    {
        if (! $this->session->get('isLoggedIn')) {
            return redirect()->to('/login')->with('error', 'Please log in to continue.');
        }

        return view('auth/change_password', [
            'title' => 'Change Password - J&T Rider Remittance & Payroll',
        ]);
    }

    public function updatePassword()
    {
        if (! $this->session->get('isLoggedIn')) {
            return redirect()->to('/login')->with('error', 'Please log in to continue.');
        }

        $rules = [
            'current_password' => 'required|max_length[255]',
            'new_password' => 'required|min_length[8]|max_length[255]',
            'confirm_password' => 'required|matches[new_password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $userModel = new UserModel();
        $user = $userModel->find((int) $this->session->get('user_id'));
        if (! $user) {
            $this->session->destroy();

            return redirect()->to('/login')->with('error', 'User account not found.');
        }

        $currentPassword = (string) $this->request->getPost('current_password');
        if (! password_verify($currentPassword, (string) $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Current password is incorrect.');
        }

        $newPassword = (string) $this->request->getPost('new_password');
        if (password_verify($newPassword, (string) $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'New password must be different from the current password.');
        }

        $userModel->update((int) $user['id'], [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'force_password_change' => 0,
        ]);

        $this->session->set('force_password_change', false);

        return redirect()->to($this->session->get('role') === 'admin' ? '/admin' : '/rider-dashboard')
            ->with('success', 'Password updated successfully.');
    }

    public function logout()
    {
        $this->session->destroy();

        return redirect()->to('/login')->with('success', 'You have been logged out.');
    }

    private function resolveRiderId(array $user): ?int
    {
        if (($user['role'] ?? '') !== 'rider') {
            return null;
        }

        $currentRiderId = (int) ($user['rider_id'] ?? 0);
        if ($currentRiderId > 0) {
            $rider = (new RiderModel())->find($currentRiderId);
            if ($rider) {
                return $currentRiderId;
            }
        }

        $username = strtolower(trim((string) ($user['username'] ?? '')));
        if ($username === '') {
            return null;
        }

        foreach ((new RiderModel())->findAll() as $rider) {
            if (strtolower((string) ($rider['rider_code'] ?? '')) === $username) {
                return (int) $rider['id'];
            }
        }

        return null;
    }
}
