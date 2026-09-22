<?php

namespace App\Policies;

use App\Models\CurriculumModule;
use App\Models\User;

/**
 * Everyone may view modules; editors and admins may edit any module and may create or delete
 * learning-map modules. The intro and toolkit rows are seeded, matched to fixed layout
 * positions by key, and can never be deleted.
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
        return $user->canEdit();
    }

    public function update(User $user, CurriculumModule $curriculumModule): bool
    {
        return $user->canEdit();
    }

    public function delete(User $user, CurriculumModule $curriculumModule): bool
    {
        if (! $curriculumModule->isMapModule()) {
            return false;
        }

        return $user->canEdit();
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
