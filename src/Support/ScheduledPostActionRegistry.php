<?php

namespace JobMetric\Post\Support;

use InvalidArgumentException;
use JobMetric\Post\Contracts\ScheduledPostAction;

/** Keep scheduled action executors explicit and extension-safe. */
class ScheduledPostActionRegistry
{
    /** @var array<string, ScheduledPostAction> */
    private array $actions = [];

    /**
     * Register one trusted handler without replacing a different handler.
     *
     * @param ScheduledPostAction $action
     *
     * @return static
     */
    public function register(ScheduledPostAction $action): static
    {
        $key = $action->key();
        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $key)) {
            throw new InvalidArgumentException('Scheduled action keys must be lowercase identifiers.');
        }
        if (isset($this->actions[$key]) && get_class($this->actions[$key]) !== get_class($action)) {
            throw new InvalidArgumentException("Scheduled action [{$key}] is already registered.");
        }
        $this->actions[$key] = $action;

        return $this;
    }

    /**
     * Resolve a handler for a persisted action key.
     *
     * @param string $key
     *
     * @return ScheduledPostAction
     */
    public function get(string $key): ScheduledPostAction
    {
        return $this->actions[$key] ?? throw new InvalidArgumentException("Scheduled action [{$key}] is not registered.");
    }
}
