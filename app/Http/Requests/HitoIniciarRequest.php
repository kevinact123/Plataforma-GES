<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HitoIniciarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return [
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
