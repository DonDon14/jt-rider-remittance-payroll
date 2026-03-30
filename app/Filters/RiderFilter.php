<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RiderFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/login')->with('error', 'Please log in to continue.');
        }

        $role = (string) session()->get('role');
        if ($role === 'admin') {
            return null;
        }

        if ($role !== 'rider') {
            return redirect()->to('/login')->with('error', 'Access denied.');
        }

        $requestedId = (int) service('uri')->getSegment(2);
        $sessionRiderId = (int) session()->get('rider_id');

        if ($requestedId > 0 && $requestedId !== $sessionRiderId) {
            return redirect()->to('/rider-dashboard')->with('error', 'You can only access your own dashboard.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
