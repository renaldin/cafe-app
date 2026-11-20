<?php

namespace App\Exceptions;

use RuntimeException;

/** Kesalahan aturan bisnis yang aman ditampilkan ke pengguna. */
class BusinessException extends RuntimeException
{
    public function __construct(string $message, public int $status = 422, public array $errors = [])
    {
        parent::__construct($message);
    }
}
