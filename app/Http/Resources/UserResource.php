<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAdmin = $user && $user->hasRole(['admin', 'super_admin']);
        $isSelf = $user && $user->id === $this->id;
        $canViewDetails = $isAdmin || $isSelf;

        return [
            // Identifiants
            'id' => $this->id,
            'uuid' => $this->uuid,

            // Informations de base
            'name' => $this->name,
            'email' => $this->when($canViewDetails, $this->email),
            'username' => $this->username ?? null,

            // Avatar et métadonnées
            'avatar' => $this->avatar,
            'bio' => $this->when($canViewDetails, $this->bio),

            // Rôles et permissions
            'roles' => $this->when($canViewDetails, $this->roles->pluck('name')),
            'permissions' => $this->when($isAdmin, $this->permissions->pluck('name')),

            // Statuts
            'is_active' => $this->when($canViewDetails, $this->is_active),
            'email_verified_at' => $this->when($canViewDetails, $this->email_verified_at?->toISOString()),

            // Dates
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Liens HATEOAS
            '_links' => [
                'self' => [
                    'href' => route('api.users.show', $this->id),
                    'method' => 'GET',
                ],
                'edit' => $this->when($canViewDetails, [
                    'href' => route('api.users.update', $this->id),
                    'method' => 'PUT',
                ]),
                'articles' => [
                    'href' => route('api.users.articles', $this->id),
                    'method' => 'GET',
                ],
                'comments' => [
                    'href' => route('api.users.comments', $this->id),
                    'method' => 'GET',
                ],
            ],

            // Actions disponibles
            '_actions' => [
                'can_view_details' => $canViewDetails,
                'can_edit' => $canViewDetails,
                'can_delete' => $isAdmin && !$isSelf,
                'can_manage_roles' => $isAdmin,
            ],
        ];
    }
}
