<?php

namespace Spatie\Activitylog\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Activity;

trait CausesActivity
{
    /**
     * @return MorphMany
     */
    public function activity()
    {
        return $this->morphMany(Activity::class, 'causer');
    }


    /**
     * @return MorphMany
     * @deprecated Use activity() instead
     */
    public function loggedActivity()
    {
        return $this->activity();
    }
}
