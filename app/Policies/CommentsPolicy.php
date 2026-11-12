<?php

namespace App\Policies;

use App\Models\Comments;
use App\Models\User;
use App\Services\ContentWindowService;

class CommentsPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Comments $comment): bool
    {
        return $user->id === $comment->user_id
            && ContentWindowService::canEdit($comment->created_at);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Comments $comment): bool
    {
        return $user->id === $comment->user_id
            && ContentWindowService::canDelete($comment->created_at);
    }
}
