<?php

namespace App\Policies;

use App\Models\CurriculumModule;
use App\Models\User;

/**
 * Curriculum modules are fixed content rows seeded by CurriculumSeeder and matched to layout
 * positions by key; admins/editors edit them but never create or delete rows.
 */
class CurriculumModulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CurriculumModule $curriculumModule): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CurriculumModule $curriculumModule): bool
    {
        return $user->canEdit();
    }

    public function delete(User $user, CurriculumModule $curriculumModule): bool
    {
        return false;
    }

    public function restore(User $user, CurriculumModule $curriculumModule): bool
    {
        return $user->canEdit();
    }

    public function forceDelete(User $user, CurriculumModule $curriculumModule): bool
    {
        return false;
    }
}
