<?php

namespace App\Cms\Roles;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Models\User;

/**
 * Reguły dostępu do treści w stylu WordPressa (meta capabilities):
 * własne treści wymagają edit_*, cudze edit_others_*, a opublikowane dodatkowo edit_published_*.
 */
class ContentAccess
{
    public function cap(ContentType $type, string $action): string
    {
        return $action.'_'.($type->capability_type ?: 'post').'s';
    }

    public function can(User $user, ContentType $type, string $action): bool
    {
        // Części globalne (nagłówek, stopka) zmieniają wygląd całej witryny: tylko administrator.
        if ($type->key === 'part' && ! RoleRegistry::grants($user->role, 'manage_options')) {
            return false;
        }

        return RoleRegistry::grants($user->role, $this->cap($type, $action));
    }

    public function canCreate(User $user, ContentType $type): bool
    {
        return $this->can($user, $type, 'edit');
    }

    public function canPublish(User $user, ContentType $type): bool
    {
        return $this->can($user, $type, 'publish');
    }

    public function canEditOthers(User $user, ContentType $type): bool
    {
        return $this->can($user, $type, 'edit_others');
    }

    public function canEdit(User $user, Content $content): bool
    {
        return $this->check($user, $content, 'edit');
    }

    public function canDelete(User $user, Content $content): bool
    {
        return $this->check($user, $content, 'delete');
    }

    private function check(User $user, Content $content, string $action): bool
    {
        $type = $content->type;
        $own = $content->author_id !== null && $content->author_id === $user->id;

        if (! $this->can($user, $type, $own ? $action : "{$action}_others")) {
            return false;
        }

        // Opublikowane i zaplanowane treści wymagają osobnego uprawnienia.
        if (in_array($content->status, [Content::STATUS_PUBLISHED, Content::STATUS_SCHEDULED], true)) {
            return $this->can($user, $type, "{$action}_published");
        }

        return true;
    }
}
