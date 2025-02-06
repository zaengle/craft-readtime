<?php

namespace zaengle\readtime\models;

use craft\base\Model;
use craft\helpers\DateTimeHelper;

/**
 * Custom Id model
 */
class ReadTime extends Model
{
    public int $seconds = 0;
    public const ONE_MINUTE = 60;
    public const ONE_HOUR = 3600;
    public const FIVE_MINUTES = 300;

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
        if ($this->seconds === 0) {
            return '';
        }

        if ($this->seconds < self::ONE_MINUTE) {
            return "Less than a minute";
        }

        if ($this->seconds > self::ONE_HOUR) {
            $rounded = round($this->seconds / self::FIVE_MINUTES) * self::FIVE_MINUTES;

            return DateTimeHelper::humanDuration($rounded, false);
        }

        return DateTimeHelper::humanDuration($this->seconds, false);
    }
    public function __toString(): string
    {
        return $this->human();
    }
}
