<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrgNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'title'      => ['required','string','max:255'],
            'user_id' => ['sometimes','nullable','integer','exists:users,id']
            ,
            'parent_id'  => ['nullable','integer','exists:org_nodes,id'],
            'department' => ['nullable','string','max:255'],
            'badge'      => ['nullable','string','max:255'],
            'subtitle'   => ['nullable','string','max:255'],
            'bio'        => ['nullable','string'],
            'level'      => ['nullable','integer','min:0','max:50'],
            'accent'     => ['nullable','string','max:50'],
            'sort_order' => ['nullable','integer','min:0','max:9999'],
            'pos_x'      => ['nullable','integer'],
            'pos_y'      => ['nullable','integer'],
            'is_active'  => ['nullable','boolean'],

            'avatar'     => ['nullable','image','max:4096'], // 4MB
        ];
    }
}
