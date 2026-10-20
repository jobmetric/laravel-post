<?php

namespace JobMetric\Post\Tasks;

use JobMetric\Flow\Support\FlowTaskDefinition;

/** Lock all localized fields of a selected language. */
class LockPostLanguage extends SetLocaleLock
{
    public static function definition(): FlowTaskDefinition
    {
        return new FlowTaskDefinition('post::lifecycle.tasks.lock_language', 'post::lifecycle.tasks.lock_language_description', 'lock', ['action']);
    }

    protected function locked(): bool
    {
        return true;
    }
}
