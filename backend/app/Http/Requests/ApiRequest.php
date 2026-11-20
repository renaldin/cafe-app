<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Otorisasi utama ditegakkan di middleware permission route. */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
