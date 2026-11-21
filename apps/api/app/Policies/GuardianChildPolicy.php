<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class GuardianChildPolicy
{
    /**
     * Determine whether the user can access the child's data.
     */
    public function viewChild(User $user, string|Student $child): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        $guardian = $user->guardian;
        if (! $guardian) {
            return false;
        }

        $childId = $child instanceof Student ? $child->id : $child;

        return $guardian->students()
            ->where('students.id', $childId)
            ->exists();
    }
}
