<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrgNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // mets ta logique d'autorisation si besoin
    }

    public function rules(): array
    {
        return [
            'parent_id'  => ['nullable', 'integer', 'exists:org_nodes,id'],
            'title'      => ['sometimes', 'required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'badge'      => ['nullable', 'string', 'max:255'],
            'subtitle'   => ['nullable', 'string', 'max:255'],
            'bio'        => ['nullable', 'string'],
            'level'      => ['nullable', 'integer', 'min:0', 'max:50'],
            'accent'     => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
            'pos_x'      => ['nullable', 'integer'],
            'pos_y'      => ['nullable', 'integer'],
            'is_active'  => ['nullable', 'boolean'],
            'avatar'     => ['nullable', 'image', 'max:4096'],
        ];
    }
}
