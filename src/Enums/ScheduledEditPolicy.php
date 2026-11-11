<?php

namespace JobMetric\Post\Enums;

/** Define how saved content changes affect an approved scheduled action. */
enum ScheduledEditPolicy: string
{
    case Preserve = 'preserve';
    case Pause = 'pause';
}
