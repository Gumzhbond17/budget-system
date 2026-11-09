<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProjectMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectMember
 */
final class ProjectMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'role' => $this->role,

            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'is_active' => $this->user->is_active,
            ]),

            'project' => $this->whenLoaded('project', fn () => [
                'uuid' => $this->project->uuid,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),

            'assigned_at' => $this->created_at?->toDateString(),
        ];
    }
}
