<?php

namespace JobMetric\Post\Tasks;

use JobMetric\Flow\Support\FlowTaskDefinition;

/** Reopen all localized fields of a selected language. */
class UnlockPostLanguage extends SetLocaleLock
{
    public static function definition(): FlowTaskDefinition
    {
        return new FlowTaskDefinition('post::lifecycle.tasks.unlock_language', 'post::lifecycle.tasks.unlock_language_description', 'lock-open', ['action']);
    }

    protected function locked(): bool
    {
        return false;
    }
}
