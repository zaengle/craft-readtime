<?php

namespace zaengle\readtime;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use yii\base\Event;
use zaengle\readtime\fields\ReadTimeFieldType;
use zaengle\readtime\models\Settings;

use craft\elements\Entry;
use craft\events\ModelEvent;
use craft\helpers\ElementHelper;
use craft\helpers\StringHelper;
use craft\helpers\DateTimeHelper;

/**
 * readtime plugin
 *
 * @method static ReadtimeField getInstance()
 * @method Settings getSettings()
 */
class ReadtimeField extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    private array $subEntryIds = [];
    private float $totalSeconds = 0;
    private string $fieldHandle = '';

    public static function config(): array
    {
        return [
            'components' => [
                // Define component configs here...
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('readtime/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/5.x/extend/events.html to get started)
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = ReadTimeFieldType::class;
        });

        Event::on(
            Entry::class,
            Entry::EVENT_BEFORE_SAVE,
            function (ModelEvent $event) {
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
                        $ckeditorMajorVersion = explode('.',$ckeditor->version)[0];

                        if ((int)$ckeditorMajorVersion >= 4) {

                            // Find entries that are owned by the CKEditor fields. 
                            $subEntries = Entry::find()
                                ->ownerId($this->subEntryIds)
                                ->status('live')
                                ->all();

                            foreach($subEntries as $subEntry) {
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

                    $formattedTime = $this->formatTime($this->totalSeconds);

                    if (!empty($formattedTime) && !empty($this->fieldHandle)) {
                        $element->setFieldValue($this->fieldHandle, $formattedTime);
                    }
                }
            }
        );
    }

    private function processField($element, $field): void
    {
        if ($this->isMatrix($field)) {
            foreach($element->getFieldValue($field->handle)->all() as $block) {
                $blockFields = $block->getFieldLayout()->getCustomFields();

                foreach ($blockFields as $blockField) {
                    $this->processField($block, $blockField);
                }
            }
        } elseif($this->isCKEditor($field)) {
            $value = $field->serializeValue($element->getFieldValue($field->handle), $element);
            $seconds = $this->valToSeconds($value);
            $this->totalSeconds = $this->totalSeconds + $seconds;

            // Collect editor IDs to query for entry block content
            $this->subEntryIds[] = $element->id;
        } else {
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

    private function formatTime($seconds): string 
    {
        return DateTimeHelper::humanDuration($seconds, true);
    }
    
    private function isMatrix($field): bool
    {
        return get_class( $field ) === 'craft\fields\Matrix';
    }
    private function isCKEditor($field): bool
    {
        return get_class( $field ) === 'craft\ckeditor\Field';
    }
    private function isSuperTable($field): bool
    {
        return get_class( $field ) === 'verbb\supertable\fields\SuperTableField';
    }
}
