<?php

namespace zaengle\readtime\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use yii\db\ExpressionInterface;
use yii\db\Schema;

use zaengle\readtime\Readtime;
use zaengle\readtime\services\ReadTimeService;
use zaengle\readtime\models\ReadTime as ReadTimeModel;

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
        return 'mixed';
    }

    public static function dbType(): array|string|null
    {
        // Replace with the appropriate data type this field will store in the database,
        // or `null` if the field is managing its own data storage.
        return Schema::TYPE_INTEGER;
    }

    public function getContentColumnType(): array|string
    {
        return Schema::TYPE_INTEGER;
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            // ...
        ]);
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return null;
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        // Craft::dd($value);
        // Already normalized?
        // if ($value instanceof ReadTimeModel) {
        //     return $value;
        // }

        // // Not set?
        // if ($value === null) {
        //     return null;
        // }

        // // Misconfigured in some other way?
        // if (!is_float($value) || !is_int($value)) {
        //     return null;
        // }
        
        // return new ReadTimeModel([ 'seconds' => $value ]);

        if ($value instanceof ReadTimeModel) {
            return $value->seconds;
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        return null;
    }
    public function populateValue($value)
    {
        // If the value is not already an instance of IntegerFieldModel, create one
        return new ReadTimeModel(['seconds' => $value]);
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element = null, bool $inline = true): string
    {
        if ($value === null) {
            $value = new ReadTimeModel(['seconds' => 0]); // Default to 0 if no value
        } elseif (!$value instanceof IntegerFieldModel) {
            $value = new ReadTimeModel(['seconds' => $value]);
        }

        $id = Craft::$app->getView()->formatInputId($this->handle);
        $namespacedId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate(
            'readtime/_input',
            [
                'name' => $this->handle,
                'value' => $value->seconds,
                'field' => $this,
                'id' => $id,
                'namespacedId' => $namespacedId
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
