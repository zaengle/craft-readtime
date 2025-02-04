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
        return $this->seconds ? DateTimeHelper::humanDuration($this->seconds, true) : '';
    }
    public function simple(): string
    {
        return $this->seconds ? DateTimeHelper::humanDuration($this->seconds, false) : '';
    }
    public function rounded(): string
    {
        if ($this->seconds < 60) {
            return "Less than a minute";
        } elseif ($this->seconds > 3600) {
            $rounded = 60 * 5; // Round to nearest five minutes
            
            return $this->seconds ? DateTimeHelper::humanDuration(round($this->seconds / $rounded) * $rounded, false) : '';
        } else {
            return $this->seconds ? DateTimeHelper::humanDuration($this->seconds, false) : '';
        }
    }
    public function __toString(): string
    {
        return $this->human();
    }
}
