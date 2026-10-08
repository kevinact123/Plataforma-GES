<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegistroGesPatologiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return [
            'id_patologia' => ['sometimes', 'required', 'integer', 'exists:patologias,id_patologia'],
            'tipo' => ['sometimes', 'nullable', 'string', 'max:50'],
            'observacion' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
