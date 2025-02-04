<?php

namespace zaengle\readtime;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use yii\base\Event;
use zaengle\readtime\fields\ReadtimeField;
use zaengle\readtime\models\Settings;
use zaengle\readtime\services\ReadtimeService;

/**
 * readtime plugin
 *
 * @method static Readtime getInstance()
 * @method Settings getSettings()
 * @property-read ReadtimeService $readTime
 */
class Readtime extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => ['readTime' => ReadtimeService::class],
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
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = ReadtimeField::class;
        });

        Event::on(
            Entry::class,
            Entry::EVENT_BEFORE_SAVE,
            function(ModelEvent $event) {
                /* @var \craft\base\ElementInterface $element */
                $element = $event->sender;
                if ($this->readTime->shouldUpdate($element)) {
                    $this->readTime->update($element);
                }
            }
        );
    }
}
