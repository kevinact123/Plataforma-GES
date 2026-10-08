<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegistroGesPatologiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return [
            'id_patologia' => ['required', 'integer', 'exists:patologias,id_patologia'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'observacion' => ['nullable', 'string'],
        ];
    }
}
