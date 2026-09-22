<?php

namespace App\Policies;

use App\Models\CurriculumSession;
use App\Models\User;

class CurriculumSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CurriculumSession $curriculumSession): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    public function update(User $user, CurriculumSession $curriculumSession): bool
    {
        return $user->canEdit();
    }

    public function delete(User $user, CurriculumSession $curriculumSession): bool
    {
        return $user->canEdit();
    }

    public function restore(User $user, CurriculumSession $curriculumSession): bool
    {
        return $user->canEdit();
    }

    public function forceDelete(User $user, CurriculumSession $curriculumSession): bool
    {
        return $user->canEdit();
    }
}
