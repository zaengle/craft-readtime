<?php

namespace zaengle\readtime\twigextensions;

use Craft;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use craft\helpers\DateTimeHelper;

class ReadTimeTwigExtension extends AbstractExtension
{

    public function getName(): string
    {
        return 'readTime';
    }
    public function getFilters(): array
    {
        return [
            new TwigFilter('inSeconds', [$this, 'seconds']),
            new TwigFilter('inMinutes', [$this, 'minutes']),
            new TwigFilter('inHours', [$this, 'hours']),
            new TwigFilter('human', [$this, 'human']),
            new TwigFilter('simple', [$this, 'simple']),
        ];
    }

    public function seconds($value = null): int
    {
      return $value;
    }
    public function minutes($value = null): int
    {
      return floor($value / 60);
    }
    public function hours($value = null): int
    {
      return floor(($value /  60) / 60);
    }
    public function human($value = null): string
    {
      return DateTimeHelper::humanDuration($value, true);
    }
    public function simple($value = null): string
    {
      return DateTimeHelper::humanDuration($value, false);
    }
}
