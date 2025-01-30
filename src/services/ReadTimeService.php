<?php

namespace zaengle\readtime\services;

use Craft;
use yii\base\Component;

use craft\elements\Entry;
use craft\events\ModelEvent;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\StringHelper;

use zaengle\readtime\Readtime;
use zaengle\readtime\fields\ReadTimeFieldType;
use zaengle\readtime\models\Settings;
use zaengle\readtime\models\ReadTime as ReadTimeModel;

/**
 * Read Time service
 */
class ReadTimeService extends Component
{
    private array $subEntryIds = [];
    private float $totalSeconds = 0;
    private string $fieldHandle = '';

    public function saveReadTime(Entry $element): void
    {
        /**
         * Check the status of the Entry before evaluating the content and updating the Read Time value.
         * To update the Read Time value, the Entry must:
         * - Not be a Draft, Duplicate or Revision
         * - Be enabled on the site
         */
        if (
            !ElementHelper::isDraft($element) &&
            !($element->duplicateOf && $element->getIsCanonical() && !$element->updatingFromDerivative) &&
            ($element->enabled && $element->getEnabledForSite()) &&
            !ElementHelper::rootElement($element)->isProvisionalDraft &&
            !ElementHelper::isRevision($element)
        ) {
            $this->subEntryIds = [];
            $this->totalSeconds = 0;
            $this->fieldHandle = '';

            $this->loopFields($element);

            // Check that CKEditor is installed and can include longform content
            $ckeditor = Craft::$app->plugins->getPlugin('ckeditor', false);
            if ($ckeditor->isInstalled) {
                $ckeditorMajorVersion = explode('.', $ckeditor->version)[0];

                if ((int)$ckeditorMajorVersion >= 4) {

                    // Find entries that are owned by the CKEditor fields. 
                    $subEntries = Entry::find()
                        ->ownerId($this->subEntryIds)
                        ->status('live')
                        ->all();

                    foreach ($subEntries as $subEntry) {
                        $this->loopFields($subEntry);
                    }
                }
            }

            if (!empty($this->fieldHandle) && !empty($this->totalSeconds)) {
                $element->setFieldValue($this->fieldHandle, $this->totalSeconds);
            }

        }
    }

    public function loopFields(Entry $element): void
    {
        foreach ($element->getFieldLayout()->getCustomFields() as $field) {
            try {
                $this->processField($element, $field);
            } catch (ErrorException $e) {
                Craft::error("Could not process field: {$field->handle} on element: {$element->id}", 'readtime');
                continue;
            }
        }
    }

    public function processField($element, $field): void
    {
        if ($this->isMatrix($field)) {
            foreach ($element->getFieldValue($field->handle)->all() as $block) {
                $blockFields = $block->getFieldLayout()->getCustomFields();

                foreach ($blockFields as $blockField) {
                    $this->processField($block, $blockField);
                }
            }
        } elseif ($this->isCKEditor($field)) {
            $value = $field->serializeValue($element->getFieldValue($field->handle), $element);
            $seconds = $this->valToSeconds($value);
            $this->totalSeconds = $this->totalSeconds + $seconds;

            // Collect editor IDs to query for entry block content
            $this->subEntryIds[] = $element->id;
        } elseif ($this->isTable($field)) {
            $value = $element->getFieldValue($field->handle);

            foreach ($value as $rowIndex => $row) {
                foreach ($row as $colId => $cellContent) {
                    if (is_string($cellContent)) {
                        $seconds = $this->valToSeconds($cellContent);
                        $this->totalSeconds = $this->totalSeconds + $seconds;
                    }
                }
            }
        } elseif ($this->isPlainText($field)) {
            $value = $element->getFieldValue($field->handle);
            $seconds = $this->valToSeconds($value);
            $this->totalSeconds = $this->totalSeconds + $seconds;
        }
        if ($field instanceof ReadTimeFieldType) {
            $this->fieldHandle = $field->handle;
        }
    }

    private function valToSeconds($value): float
    {
        /** @var Settings $settings */
        $settings = ReadTime::getInstance()->getSettings();
        $wpm = $settings->wordsPerMinute;

        $string = StringHelper::toString($value);
        $wordCount = StringHelper::countWords($string);
        $seconds = floor($wordCount / $wpm * 60);

        return $seconds;
    }

    public function formatTime($seconds): string
    {
        return DateTimeHelper::humanDuration($seconds, true);
    }

    private function isMatrix($field): bool
    {
        return $field instanceof craft\fields\Matrix;
    }
    private function isCKEditor($field): bool
    {
        return $field instanceof craft\ckeditor\Field;
    }
    private function isTable($field): bool
    {
        return $field instanceof craft\fields\Table;
    }
    private function isPlainText($field): bool
    {
        return $field instanceof craft\fields\PlainText;
    }
    private function isSuperTable($field): bool
    {
        return $field instanceof verbb\supertable\fields\SuperTableField;
    }
}
