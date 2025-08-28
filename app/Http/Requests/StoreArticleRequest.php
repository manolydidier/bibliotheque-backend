<?php

namespace App\Http\Requests;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Article::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:500',
                'min:3',
            ],
            'excerpt' => [
                'nullable',
                'string',
                'max:500',
            ],
            'content' => [
                'required',
                'string',
                'min:100',
            ],
            'featured_image' => [
                'nullable',
                'string',
                'max:500',
            ],
            'featured_image_alt' => [
                'nullable',
                'string',
                'max:255',
            ],
            'meta' => [
                'nullable',
                'array',
            ],
            'meta.keywords' => [
                'nullable',
                'string',
                'max:500',
            ],
            'meta.description' => [
                'nullable',
                'string',
                'max:160',
            ],
            'seo_data' => [
                'nullable',
                'array',
            ],
            'status' => [
                'required',
                Rule::in(ArticleStatus::cases()),
            ],
            'visibility' => [
                'required',
                Rule::in(ArticleVisibility::cases()),
            ],
            'password' => [
                'nullable',
                'string',
                'min:6',
                'required_if:visibility,password_protected',
            ],
            'published_at' => [
                'nullable',
                'date',
                'after_or_equal:now',
            ],
            'scheduled_at' => [
                'nullable',
                'date',
                'after:now',
            ],
            'expires_at' => [
                'nullable',
                'date',
                'after:published_at',
            ],
            'is_featured' => [
                'boolean',
            ],
            'is_sticky' => [
                'boolean',
            ],
            'allow_comments' => [
                'boolean',
            ],
            'allow_sharing' => [
                'boolean',
            ],
            'allow_rating' => [
                'boolean',
            ],
            'author_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'author_bio' => [
                'nullable',
                'string',
                'max:500',
            ],
            'author_avatar' => [
                'nullable',
                'string',
                'max:500',
            ],
            'author_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'categories' => [
                'nullable',
                'array',
                'min:1',
            ],
            'categories.*.id' => [
                'required_with:categories',
                'integer',
                'exists:categories,id',
            ],
            'categories.*.is_primary' => [
                'nullable',
                'boolean',
            ],
            'categories.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'tags' => [
                'nullable',
                'array',
            ],
            'tags.*.id' => [
                'required_with:tags',
                'integer',
                'exists:tags,id',
            ],
            'tags.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'tenant_id' => [
                'nullable',
                'integer',
                'exists:tenants,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Le titre de l\'article est requis.',
            'title.min' => 'Le titre doit contenir au moins 3 caractères.',
            'title.max' => 'Le titre ne peut pas dépasser 500 caractères.',
            'content.required' => 'Le contenu de l\'article est requis.',
            'content.min' => 'Le contenu doit contenir au moins 100 caractères.',
            'status.in' => 'Le statut sélectionné n\'est pas valide.',
            'visibility.in' => 'La visibilité sélectionnée n\'est pas valide.',
            'password.required_if' => 'Un mot de passe est requis pour les articles protégés.',
            'published_at.after_or_equal' => 'La date de publication doit être aujourd\'hui ou dans le futur.',
            'scheduled_at.after' => 'La date de planification doit être dans le futur.',
            'expires_at.after' => 'La date d\'expiration doit être après la date de publication.',
            'categories.min' => 'Au moins une catégorie doit être sélectionnée.',
            'categories.*.id.exists' => 'Une des catégories sélectionnées n\'existe pas.',
            'tags.*.id.exists' => 'Un des tags sélectionnés n\'existe pas.',
            'author_id.exists' => 'L\'auteur sélectionné n\'existe pas.',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'title' => 'titre',
            'excerpt' => 'extrait',
            'content' => 'contenu',
            'featured_image' => 'image à la une',
            'featured_image_alt' => 'texte alternatif de l\'image',
            'status' => 'statut',
            'visibility' => 'visibilité',
            'password' => 'mot de passe',
            'published_at' => 'date de publication',
            'scheduled_at' => 'date de planification',
            'expires_at' => 'date d\'expiration',
            'is_featured' => 'mis en avant',
            'is_sticky' => 'épinglé',
            'allow_comments' => 'autoriser les commentaires',
            'allow_sharing' => 'autoriser le partage',
            'allow_rating' => 'autoriser les évaluations',
            'author_name' => 'nom de l\'auteur',
            'author_bio' => 'biographie de l\'auteur',
            'author_avatar' => 'avatar de l\'auteur',
            'categories' => 'catégories',
            'tags' => 'tags',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'status' => $this->status ?? ArticleStatus::DRAFT,
            'visibility' => $this->visibility ?? ArticleVisibility::PUBLIC,
            'is_featured' => $this->boolean('is_featured'),
            'is_sticky' => $this->boolean('is_sticky'),
            'allow_comments' => $this->boolean('allow_comments', true),
            'allow_sharing' => $this->boolean('allow_sharing', true),
            'allow_rating' => $this->boolean('allow_rating', true),
        ]);

        // Set tenant_id if not provided
        if (!$this->filled('tenant_id') && $this->user()->tenant_id) {
            $this->merge(['tenant_id' => $this->user()->tenant_id]);
        }

        // Set author_id if not provided
        if (!$this->filled('author_id')) {
            $this->merge(['author_id' => $this->user()->id]);
        }
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        throw new \Illuminate\Validation\ValidationException($validator, response()->json([
            'message' => 'Les données fournies ne sont pas valides.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
