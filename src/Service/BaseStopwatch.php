<?php

namespace App\Service;

use Symfony\Component\Stopwatch\Stopwatch;

class BaseStopwatch extends Stopwatch
{
    public function getFormattedDuration($eventName)
    {
        $duration = $this->getEvent($eventName)->getDuration();

        $uSec = $duration % 1000;
        $duration = floor($duration / 1000);

        $seconds = $duration % 60;
        $duration = floor($duration / 60);

        $minutes = $duration % 60;
        $duration = floor($duration / 60);

        return sprintf('%02d:%02d:%02d.%03d', $duration, $minutes, $seconds, $uSec);
    }
}
