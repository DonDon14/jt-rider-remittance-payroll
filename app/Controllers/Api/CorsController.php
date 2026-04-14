<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

class CorsController extends BaseController
{
    public function preflight()
    {
        $origin = trim((string) $this->request->getHeaderLine('Origin'));
        $allowOrigin = $this->resolveAllowedOrigin($origin);

        return $this->response
            ->setStatusCode(204)
            ->setHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->setHeader('Vary', 'Origin')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Requested-With, Accept, Origin')
            ->setHeader('Access-Control-Max-Age', '7200');
    }

    private function resolveAllowedOrigin(string $origin): string
    {
        if ($origin === '') {
            return '*';
        }

        $isLocalhost = (bool) preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#i', $origin);
        $isVpsHost = (bool) preg_match('#^https?://187\.127\.105\.169(:\d+)?$#i', $origin);

        if ($isLocalhost || $isVpsHost) {
            return $origin;
        }

        return '*';
    }
}
