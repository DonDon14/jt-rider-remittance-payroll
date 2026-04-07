<?php

namespace App\Filters;

use App\Models\RiderModel;
use App\Models\UserModel;
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

        if (session()->get('force_password_change')) {
            return redirect()->to('/change-password')->with('error', 'You need to change your password before continuing.');
        }

        $role = (string) session()->get('role');
        if ($role === 'admin') {
            return redirect()->to('/admin');
        }

        if ($role !== 'rider') {
            return redirect()->to('/login')->with('error', 'Access denied.');
        }

        $sessionRiderId = $this->resolveSessionRiderId();
        if ($sessionRiderId <= 0) {
            session()->destroy();

            return redirect()->to('/login')->with('error', 'Rider account is not linked to a rider profile.');
        }

        $requestedId = (int) service('uri')->getSegment(2);
        if ($requestedId > 0 && $requestedId !== $sessionRiderId) {
            return redirect()->to('/rider-dashboard')->with('error', 'You can only access your own dashboard.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function resolveSessionRiderId(): int
    {
        $sessionRiderId = (int) session()->get('rider_id');
        if ($sessionRiderId > 0) {
            $rider = (new RiderModel())->find($sessionRiderId);
            if ($rider) {
                return $sessionRiderId;
            }
        }

        $user = (new UserModel())->find((int) session()->get('user_id'));
        if (! $user || ($user['role'] ?? '') !== 'rider') {
            return 0;
        }

        $resolvedRiderId = (int) ($user['rider_id'] ?? 0);
        if ($resolvedRiderId <= 0) {
            $username = strtolower(trim((string) ($user['username'] ?? '')));
            if ($username !== '') {
                foreach ((new RiderModel())->findAll() as $rider) {
                    if (strtolower((string) ($rider['rider_code'] ?? '')) === $username) {
                        $resolvedRiderId = (int) $rider['id'];
                        break;
                    }
                }
            }
        }

        if ($resolvedRiderId > 0) {
            if ((int) ($user['rider_id'] ?? 0) !== $resolvedRiderId) {
                (new UserModel())->update((int) $user['id'], ['rider_id' => $resolvedRiderId]);
            }
            session()->set('rider_id', $resolvedRiderId);
        }

        return $resolvedRiderId;
    }
}
