<?php

namespace Spatie\Activitylog\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * Class Activity
 * @package Spatie\Activitylog\Models
 *
 * @property Collection $properties
 * @property string $description
 * @property string $log_name
 */
class Activity extends Eloquent
{
    protected $table = 'activity_log';

    public $guarded = [];

    protected $casts = [
        'properties' => 'collection',
    ];


    /**
     * @return MorphTo
     */
    public function subject()
    {
        return $this->morphTo();
    }


    /**
     * @return MorphTo
     */
    public function causer()
    {
        return $this->morphTo();
    }

    /**
     * Get the extra properties with the given name.
     *
     * @param string $propertyName
     *
     * @return mixed
     */
    public function getExtraProperty($propertyName)
    {
        return array_get($this->properties->toArray(), $propertyName);
    }


    /**
     * @return Collection
     */
    public function getChangesAttribute()
    {
        return collect(array_filter($this->properties->toArray(), function ($key) {
            return in_array($key, ['attributes', 'old']);
        }, ARRAY_FILTER_USE_KEY));
    }


    /**
     * @param Builder $query
     * @param array   ...$logNames
     * @return Builder
     */
    public function scopeInLog(Builder $query, ...$logNames)
    {
        if (is_array($logNames[0])) {
            $logNames = $logNames[0];
        }

        return $query->whereIn('log_name', $logNames);
    }
}
