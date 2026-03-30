<?php

namespace App\Controllers;

use App\Models\UserModel;

class AuthController extends BaseController
{
    public function loginForm()
    {
        if ($this->session->get('isLoggedIn')) {
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

        $user = (new UserModel())->where('username', $username)->first();
        if (! $user || ! (bool) ($user['is_active'] ?? true) || ! password_verify($password, (string) $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Invalid username or password.');
        }

        $this->session->regenerate();
        $this->session->set([
            'user_id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'role' => (string) $user['role'],
            'rider_id' => $user['rider_id'] === null ? null : (int) $user['rider_id'],
            'isLoggedIn' => true,
        ]);

        return redirect()->to($user['role'] === 'admin' ? '/admin' : '/rider-dashboard');
    }

    public function logout()
    {
        $this->session->destroy();

        return redirect()->to('/login')->with('success', 'You have been logged out.');
    }
}
