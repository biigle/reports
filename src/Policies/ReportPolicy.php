<?php

namespace Biigle\Modules\Export\Policies;

use Biigle\User;
use Biigle\Modules\Export\Report;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReportPolicy
{
    const TABLE = 'reports';

    use HandlesAuthorization;

    /**
     * Intercept all checks.
     *
     * @param User $user
     * @param string $ability
     * @return bool|null
     */
    public function before($user, $ability)
    {
        if ($user->isAdmin) {
            return true;
        }
    }

    /**
     * Determine if the given report can be accessed by the user.
     *
     * @param  User  $user
     * @param  Report  $report
     * @return bool
     */
    public function access(User $user, Report $report)
    {
        return $report->user_id === $user->id;
    }
}