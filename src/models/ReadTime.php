<?php

namespace zaengle\readtime\models;

use Craft;
use craft\base\Model;
use craft\helpers\DateTimeHelper;

/**
 * Custom Id model
 */
class ReadTime extends Model
{
    public int $seconds = 0;

    /**
     * @inheritDoc
     * @return array<array>
     */
    public function defineRules(): array
    {
        return [
            [['seconds'], 'required'],
        ];
    }
    public function dateInterval(): \DateInterval
    {
        return DateTimeHelper::toDateInterval($this->seconds);
    }
    public function human(): string
    {
        // Craft::dd($this);
        return DateTimeHelper::humanDuration($this->seconds, true);
    }
    public function simple(): string
    {
        return DateTimeHelper::humanDuration($this->seconds, false);
    }
}
