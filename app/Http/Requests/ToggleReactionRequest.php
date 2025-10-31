<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ToggleReactionRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'reactable_type' => ['required', 'string'],
            'reactable_id'   => ['required', 'integer'],
            'type'           => ['required', Rule::in(['like', 'favorite'])],
            'action'         => ['sometimes', Rule::in(['toggle','add','remove'])], // toggle par défaut
        ];
    }
}
