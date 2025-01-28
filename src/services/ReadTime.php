<?php

namespace zaengle\readtime\services;

use Craft;
use yii\base\Component;

use craft\elements\Entry;
use craft\events\ModelEvent;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\StringHelper;

use zaengle\readtime\ReadtimeField;
use zaengle\readtime\fields\ReadTimeFieldType;
use zaengle\readtime\models\Settings;

/**
 * Read Time service
 */
class ReadTime extends Component
{
    private array $subEntryIds = [];
    private float $totalSeconds = 0;
    private string $fieldHandle = '';

    public function saveReadTime(ModelEvent $event)
    {
        if (
            !ElementHelper::isDraft($event->sender) &&
            !($event->sender->duplicateOf && $event->sender->getIsCanonical() && !$event->sender->updatingFromDerivative) &&
            ($event->sender->enabled && $event->sender->getEnabledForSite()) &&
            !ElementHelper::rootElement($event->sender)->isProvisionalDraft &&
            !ElementHelper::isRevision($event->sender)
        ) {
            $element = $event->sender;

            $this->subEntryIds = [];
            $this->totalSeconds = 0;
            $this->fieldHandle = '';
            $allFieldHandles = [];

            foreach ($element->getFieldLayout()->getCustomFields() as $field) {
                $allFieldHandles[] = $field->handle;

                try {
                    $this->processField($element, $field);
                } catch (ErrorException $e) {
                    continue;
                }
            }

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
                        foreach ($subEntry->getFieldLayout()->getCustomFields() as $entryField) {
                            try {
                                $this->processField($subEntry, $entryField);
                            } catch (ErrorException $e) {
                                continue;
                            }
                        }
                    }
                }
            }

            if (!empty($this->fieldHandle) && !empty($this->totalSeconds)) {
                $element->setFieldValue($this->fieldHandle, $this->totalSeconds);
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
        $settings = ReadTimeField::getInstance()->getSettings();
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
        return get_class($field) === 'verbb\supertable\fields\SuperTableField';
    }
}
