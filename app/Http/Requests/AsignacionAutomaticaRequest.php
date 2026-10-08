<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AsignacionAutomaticaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return [
            'id_registro' => ['nullable', 'integer', 'exists:registros_ges,id_registro'],
        ];
    }
}
