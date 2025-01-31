<?php

namespace zaengle\readtime\services;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Entry;

use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\StringHelper;
use craft\fields\Matrix;
use craft\ckeditor\Field;
use craft\fields\Table;
use craft\fields\PlainText;

use ErrorException;
use yii\base\Component;

use zaengle\readtime\fields\ReadTimeFieldType;
use zaengle\readtime\models\Settings;
use zaengle\readtime\Readtime;

/**
 * Read Time service
 */
class ReadTimeService extends Component
{
    private array $subEntryIds = [];
    private int $totalSeconds = 0;
    private string $fieldHandle = '';

    public function update(ElementInterface $element): void
    {
        /**
         * Check the status of the Entry before evaluating the content and updating the Read Time value.
         * To update the Read Time value, the Entry must:
         * - Not be a Draft, Duplicate or Revision
         * - Be enabled on the site
         */

        $this->subEntryIds = [];
        $this->totalSeconds = 0;
        $this->fieldHandle = '';

        $this->loopFields($element);

        if ($this->isCkEditor4Installed()) {
            // Find entries that are owned by the CKEditor fields.
            $subEntries = Entry::find()
                ->ownerId($this->subEntryIds)
                ->status('live')
                ->all();

            foreach ($subEntries as $subEntry) {
                $this->loopFields($subEntry);
            }
        }

        if (!empty($this->fieldHandle) && !empty($this->totalSeconds)) {
            $element->setFieldValue($this->fieldHandle, $this->totalSeconds);
        }
    }

    /**
     * Only process the Read Time value if the Entry is not a Draft, Duplicate or Revision
     *
     * @param ElementInterface $element
     * @return bool
     */
    public function shouldUpdate(ElementInterface $element): bool
    {
        return !ElementHelper::isDraft($element) &&
            !($element->duplicateOf && $element->getIsCanonical() && !$element->updatingFromDerivative) &&
            ($element->enabled && $element->getEnabledForSite()) &&
            !$element->getRootOwner()->isProvisionalDraft &&
            !ElementHelper::isRevision($element);
    }

    public function loopFields(ElementInterface $element): void
    {
        foreach ($element->getFieldLayout()?->getCustomFields() as $field) {
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
            $this->totalSeconds += $seconds;

            // Collect editor IDs to query for entry block content
            $this->subEntryIds[] = $element->id;
        } elseif ($this->isTable($field)) {
            $value = $element->getFieldValue($field->handle);

            foreach ($value as $rowIndex => $row) {
                foreach ($row as $colId => $cellContent) {
                    if (is_string($cellContent)) {
                        $seconds = $this->valToSeconds($cellContent);
                        $this->totalSeconds += $seconds;
                    }
                }
            }
        } elseif ($this->isPlainText($field)) {
            $value = $element->getFieldValue($field->handle);
            $seconds = $this->valToSeconds($value);
            $this->totalSeconds += $seconds;
        }
        if ($field instanceof ReadTimeFieldType) {
            $this->fieldHandle = $field->handle;
        }
    }

    private function valToSeconds(string $value): int
    {
        /** @var Settings $settings */
        $settings = ReadTime::getInstance()->getSettings();
        $wpm = $settings->wordsPerMinute;

        $string = StringHelper::toString($value);
        $wordCount = StringHelper::countWords($string);
        return (int) floor($wordCount / $wpm * 60);
    }

    public function formatTime($seconds): string
    {
        return DateTimeHelper::humanDuration($seconds, true);
    }

    private function isCkEditor4Installed(): bool
    {
        // Check that CKEditor is installed and can include longform content
        $ckeditor = Craft::$app->plugins->getPlugin('ckeditor');

        if ($ckeditor && $ckeditor->isInstalled) {
            $ckeditorMajorVersion = explode('.', $ckeditor->version)[0];

            return ((int) $ckeditorMajorVersion >= 4);
        }
        return false;
    }

    private function isMatrix($field): bool
    {
        return $field instanceof Matrix;
    }
    private function isCKEditor($field): bool
    {
        // @phpstan-ignore-next-line
        return class_exists('craft\ckeditor\Field') && $field instanceof craft\ckeditor\Field;
    }
    private function isTable($field): bool
    {
        return $field instanceof Table;
    }
    private function isPlainText($field): bool
    {
        return $field instanceof PlainText;
    }
}
