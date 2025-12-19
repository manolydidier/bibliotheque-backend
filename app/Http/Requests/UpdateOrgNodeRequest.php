<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrgNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'title'      => ['sometimes','required','string','max:255'],
            'user_id' => ['sometimes','nullable','integer','exists:users,id']
,
            'parent_id'  => ['sometimes','nullable','integer','exists:org_nodes,id'],
            'department' => ['sometimes','nullable','string','max:255'],
            'badge'      => ['sometimes','nullable','string','max:255'],
            'subtitle'   => ['sometimes','nullable','string','max:255'],
            'bio'        => ['sometimes','nullable','string'],
            'level'      => ['sometimes','nullable','integer','min:0','max:50'],
            'accent'     => ['sometimes','nullable','string','max:50'],
            'sort_order' => ['sometimes','nullable','integer','min:0','max:9999'],
            'pos_x'      => ['sometimes','nullable','integer'],
            'pos_y'      => ['sometimes','nullable','integer'],
            'is_active'  => ['sometimes','nullable','boolean'],

            'avatar'     => ['sometimes','nullable','image','max:4096'],
        ];
    }
}
