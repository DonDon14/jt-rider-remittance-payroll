<?php

if (! function_exists('app_generate_temporary_password')) {
    function app_generate_temporary_password(int $length = 14): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $maxIndex = strlen($alphabet) - 1;
        $password = '';

        for ($index = 0; $index < $length; $index++) {
            $password .= $alphabet[random_int(0, $maxIndex)];
        }

        return $password;
    }
}
