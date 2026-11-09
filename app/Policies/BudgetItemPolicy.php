<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BudgetItem;
use App\Models\Project;
use App\Models\User;

/**
 * ງົບປະມານແມ່ນ **ແຜນ** ບໍ່ແມ່ນບັນທຶກການເງິນ ຈຶ່ງລຶບຖາວອນໄດ້
 * (ຕ່າງຈາກ expenses ແລະ attachments ທີ່ຫ້າມ hard delete).
 */
final class BudgetItemPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->isMemberOf($project->id);
    }

    public function view(User $user, BudgetItem $item): bool
    {
        return $user->isMemberOf($item->project_id);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->hasProjectRole($project->id, ['full_access', 'modify']);
    }

    public function update(User $user, BudgetItem $item): bool
    {
        return $user->hasProjectRole($item->project_id, ['full_access', 'modify']);
    }

    public function delete(User $user, BudgetItem $item): bool
    {
        return $user->hasProjectRole($item->project_id, ['full_access', 'modify']);
    }
}
