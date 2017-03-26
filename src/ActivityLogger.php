<?php

namespace Spatie\Activitylog;

use Illuminate\Auth\AuthManager;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Config\Repository;
use Spatie\Activitylog\Exceptions\CouldNotLogActivity;

class ActivityLogger
{
    use Macroable;

    /** @var \Illuminate\Auth\AuthManager */
    protected $auth;

    protected $logName = '';

    /** @var bool */
    protected $logEnabled;

    /** @var \Illuminate\Database\Eloquent\Model */
    protected $performedOn;

    /** @var \Illuminate\Database\Eloquent\Model */
    protected $causedBy;

    /** @var \Illuminate\Support\Collection */
    protected $properties;

    public function __construct(AuthManager $auth, Repository $config)
    {
        $this->auth = $auth;

        $this->properties = collect();

        $authDriver = isset($config['laravel-activitylog']['default_auth_driver'])
            ? $config['laravel-activitylog']['default_auth_driver']
            : $auth->getDefaultDriver();

        if (starts_with(app()->version(), '5.1')) {
            $this->causedBy = $auth->driver($authDriver)->user();
        } else {
            $this->causedBy = $auth->guard($authDriver)->user();
        }

        $this->logName = $config['laravel-activitylog']['default_log_name'];

        $this->logEnabled = isset($config['laravel-activitylog']['enabled'])
            ? $config['laravel-activitylog']['enabled']
            : true;
    }

    public function performedOn(Model $model)
    {
        $this->performedOn = $model;

        return $this;
    }

    public function on(Model $model)
    {
        return $this->performedOn($model);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model|int|string $modelOrId
     *
     * @return $this
     */
    public function causedBy($modelOrId)
    {
        $model = $this->normalizeCauser($modelOrId);

        $this->causedBy = $model;

        return $this;
    }

    public function by($modelOrId)
    {
        return $this->causedBy($modelOrId);
    }

    /**
     * @param array|\Illuminate\Support\Collection $properties
     *
     * @return $this
     */
    public function withProperties($properties)
    {
        $this->properties = collect($properties);

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function withProperty($key, $value)
    {
        $this->properties->put($key, $value);

        return $this;
    }


    /**
     * @param string $logName
     * @return $this
     */
    public function useLog($logName)
    {
        $this->logName = $logName;

        return $this;
    }


    /**
     * @param string $logName
     * @return ActivityLogger
     */
    public function inLog($logName)
    {
        return $this->useLog($logName);
    }

    /**
     * @param string $description
     *
     * @return null|mixed
     */
    public function log($description)
    {
        if (! $this->logEnabled) {
            return null;
        }

        $activity = ActivitylogServiceProvider::getActivityModelInstance();

        if ($this->performedOn) {
            $activity->subject()->associate($this->performedOn);
        }

        if ($this->causedBy) {
            $activity->causer()->associate($this->causedBy);
        }

        $activity->properties = $this->properties;

        $activity->description = $this->replacePlaceholders($description, $activity);

        $activity->log_name = $this->logName;

        $activity->save();

        return $activity;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model|int|string $modelOrId
     *
     * @throws \Spatie\Activitylog\Exceptions\CouldNotLogActivity
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function normalizeCauser($modelOrId)
    {
        if ($modelOrId instanceof Model) {
            return $modelOrId;
        }

        if ($model = $this->auth->getProvider()->retrieveById($modelOrId)) {
            return $model;
        }

        throw CouldNotLogActivity::couldNotDetermineUser($modelOrId);
    }


    /**
     * @param string   $description
     * @param Activity $activity
     * @return string
     */
    protected function replacePlaceholders($description, Activity $activity)
    {
        return preg_replace_callback('/:[a-z0-9._-]+/i', function ($match) use ($activity) {
            $match = $match[0];

            $attribute = (string) string($match)->between(':', '.');

            if (! in_array($attribute, ['subject', 'causer', 'properties'])) {
                return $match;
            }

            $propertyName = substr($match, strpos($match, '.') + 1);

            $attributeValue = $activity->$attribute;

            if (is_null($attributeValue)) {
                return $match;
            }

            $attributeValue = $attributeValue->toArray();

            return array_get($attributeValue, $propertyName, $match);
        }, $description);
    }
}
