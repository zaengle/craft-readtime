<?php

namespace zaengle\readtime\services;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Entry;

use craft\fields\Matrix;
use craft\fields\PlainText;
use craft\fields\Table;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\StringHelper;

use ErrorException;
use yii\base\Component;

use zaengle\readtime\fields\ReadtimeField;
use zaengle\readtime\models\Settings;
use zaengle\readtime\Readtime;

/**
 * Read Time service
 */
class ReadtimeService extends Component
{
    private array $subEntryIds = [];
    private array $excludeIds = [];
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
        $this->excludeIds = [];
        $this->totalSeconds = 0;
        $this->fieldHandle = '';

        $this->loopFields($element);

        if ($this->isCkEditor4Installed() && !empty($this->subEntryIds)) {
            // Find entries that are owned by the CKEditor fields.
            $subEntries = Entry::find()
                ->id($this->subEntryIds)
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
        return !ElementHelper::isDraftOrRevision($element) &&
            $this->elementHasReadtimeField($element);
    }

    public function elementHasReadtimeField(ElementInterface $element): bool
    {
        return (bool) collect($element->getFieldLayout()?->getCustomFields())->firstWhere(
            fn($field) => $field instanceof ReadtimeField
        );
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
            // Make sure content has not already been counted (Longform)
            if (!in_array($element->id . "." . $field->handle, $this->excludeIds)) {
                $fieldHandle = $field->handle;
                $fieldContent = $element->$fieldHandle;

                collect($fieldContent)->each(function($chunk) {
                    $chunk->type == 'markup' ? $this->addSeconds($chunk->rawHtml) : $this->trackSubEntry($chunk->entry->id);
                });

                $this->excludeIds[] = $element->id . "." . $field->handle;
            }
        } elseif ($this->isRedactor($field)) {
            $value = $field->serializeValue($element->getFieldValue($field->handle), $element);
            $this->addSeconds($value);
        } elseif ($this->isTable($field)) {
            $value = $element->getFieldValue($field->handle);

            foreach ($value as $rowIndex => $row) {
                foreach ($row as $colId => $cellContent) {
                    if (is_string($cellContent)) {
                        $this->addSeconds($cellContent);
                    }
                }
            }
        } elseif ($this->isPlainText($field)) {
            $value = $element->getFieldValue($field->handle);
            $this->addSeconds($value);
        }
        if ($field instanceof ReadtimeField) {
            $this->fieldHandle = $field->handle;
        }
    }

    private function addSeconds($value): void
    {
        $seconds = $this->valToSeconds($value);
        $this->totalSeconds += $seconds;
    }

    private function trackSubEntry($entryId): void
    {
        $this->subEntryIds[] = $entryId;
    }

    private function valToSeconds(?string $value): int
    {
        if (!$value) {
            return 0;
        }
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
        return is_a($field, 'craft\ckeditor\Field');
    }
    private function isRedactor($field): bool
    {
        return is_a($field, 'craft\redactor\Field');
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
