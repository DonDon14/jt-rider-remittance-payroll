<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        if (! $this->session->get('isLoggedIn')) {
            return redirect()->to('/login');
        }

        return redirect()->to($this->session->get('role') === 'admin' ? '/admin' : '/rider-dashboard');
    }
}
