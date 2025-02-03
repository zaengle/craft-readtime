<?php

namespace zaengle\readtime\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\StringHelper;
use yii\db\ExpressionInterface;
use yii\db\Schema;

use zaengle\readtime\models\ReadTime as ReadTimeModel;
use zaengle\readtime\Readtime;

/**
 * Read Time field type
 */
class ReadTimeFieldType extends Field
{
    public static function displayName(): string
    {
        return Craft::t('readtime', 'Read Time');
    }

    public static function icon(): string
    {
        return 'timer';
    }

    public static function phpType(): string
    {
        return ReadTimeModel::class;
    }

    public static function dbType(): array|string|null
    {
        // Replace with the appropriate data type this field will store in the database,
        // or `null` if the field is managing its own data storage.
        return [
            'seconds' => Schema::TYPE_INTEGER,
        ];
    }

    public function getContentColumnType(): array|string
    {
        return [
            'seconds' => Schema::TYPE_INTEGER,
        ];
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {

        if ($value instanceof ReadTimeModel) {
            return $value;
        }
        if (is_array($value)) {
            return new ReadTimeModel($value);
        }
        if (is_int($value)) {
            return new ReadTimeModel(['seconds' => $value]);
        }
        if (empty($value)) {
            return new ReadTimeModel(['seconds' => 0]);
        }
        return $value;
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element = null, bool $inline = true): string
    {
        /* @var ReadTimeModel $value */
        return Craft::$app->getView()->renderTemplate(
            'readtime/_input',
            [
                'readTime' => $value,
                'wpm' => Readtime::getInstance()->getSettings()->wordsPerMinute,
            ]
        );
    }

    public function getElementValidationRules(): array
    {
        return [];
    }

    protected function searchKeywords(mixed $value, ElementInterface $element): string
    {
        return StringHelper::toString($value, ' ');
    }

    public function getElementConditionRuleType(): array|string|null
    {
        return null;
    }

    public static function queryCondition(
        array $instances,
        mixed $value,
        array &$params,
    ): ExpressionInterface|array|string|false|null {
        return parent::queryCondition($instances, $value, $params);
    }
}
