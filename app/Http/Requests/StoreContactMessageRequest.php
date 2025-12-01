<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'type'    => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'min:10'],
            'consent' => ['required', 'boolean', 'accepted'],

            // Honeypot (NE DOIT PAS être rempli)
            'company' => ['nullable', 'string', 'max:255'],

            // Si tu veux, tu peux aussi valider un captcha ici plus tard
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted' => 'Vous devez accepter le traitement de vos données.',
        ];
    }
}
