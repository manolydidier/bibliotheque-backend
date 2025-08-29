<?php

namespace App\Http\Requests;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('article'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:500', 'min:3'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'content' => ['sometimes', 'string', 'min:50'],
            'featured_image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'featured_image_alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta' => ['sometimes', 'nullable', 'array'],
            'meta.keywords' => ['sometimes', 'nullable', 'string', 'max:500'],
            'meta.description' => ['sometimes', 'nullable', 'string', 'max:160'],
            'seo_data' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', Rule::in(ArticleStatus::cases())],
            'visibility' => ['sometimes', Rule::in(ArticleVisibility::cases())],
            'password' => ['sometimes', 'nullable', 'string', 'min:6', 'required_if:visibility,password_protected'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'scheduled_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:published_at'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_sticky' => ['sometimes', 'boolean'],
            'allow_comments' => ['sometimes', 'boolean'],
            'allow_sharing' => ['sometimes', 'boolean'],
            'allow_rating' => ['sometimes', 'boolean'],
            'author_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'author_bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'author_avatar' => ['sometimes', 'nullable', 'string', 'max:500'],
            'author_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'categories' => ['sometimes', 'nullable', 'array', 'min:1'],
            'categories.*.id' => ['required_with:categories', 'integer', 'exists:categories,id'],
            'categories.*.is_primary' => ['nullable', 'boolean'],
            'categories.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*.id' => ['required_with:tags', 'integer', 'exists:tags,id'],
            'tags.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'tenant_id' => ['sometimes', 'nullable', 'integer', 'exists:tenants,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.min' => 'Le titre doit contenir au moins 3 caractères.',
            'title.max' => 'Le titre ne peut pas dépasser 500 caractères.',
            'content.min' => 'Le contenu doit contenir au moins 50 caractères.',
            'visibility.in' => 'La visibilité sélectionnée n\'est pas valide.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_featured' => $this->boolean('is_featured'),
            'is_sticky' => $this->boolean('is_sticky'),
            'allow_comments' => $this->boolean('allow_comments'),
            'allow_sharing' => $this->boolean('allow_sharing'),
            'allow_rating' => $this->boolean('allow_rating'),
        ]);
    }
}


