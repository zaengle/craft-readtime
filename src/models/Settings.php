<?php

namespace zaengle\readtime\models;

use Craft;
use craft\base\Model;

/**
 * Readtime Field settings
 */
class Settings extends Model
{
  public int $wordsPerMinute = 200;

    // Public Methods
    // =========================================================================

    public function rules(): array
    {
        return [
            [['wordsPerMinute'], 'required'],
            [['wordsPerMinute'], 'number', 'integerOnly' => true]
        ];
    }
}
