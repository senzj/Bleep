<?php

namespace App\Http\Resources;

use App\Models\BulletinPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BulletinPost
 */
class BulletinPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'anonymous_name' => $this->anonymous_name,
            'content' => $this->content,
            'link_url' => $this->link_url,
            'image_url' => $this->image_url,
            'media_url' => $this->media_path
                ? asset('storage/'.ltrim($this->media_path, '/'))
                : null,
            'media_type' => $this->media_mime,
            'media_kind' => $this->media_kind,
            'width' => $this->width,
            'height' => $this->height,
            'color' => $this->paper_color,
            'texture' => $this->paper_texture,
            'created_at' => $this->created_at->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'can_edit' => (bool) ($this->is_owner ?? false) && $this->resource->isEditable(),
            'can_delete' => (bool) ($this->is_owner ?? false),
            'reactions' => [
                'counts' => $this->reaction_counts ?? [],
                'mine' => $this->my_reaction,
            ],
        ];
    }
}
