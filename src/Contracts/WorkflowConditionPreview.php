<?php

namespace JobMetric\Post\Contracts;

use JobMetric\Flow\Support\FlowTaskContext;

/** Opt in to read-only condition inspection; implementations must never persist or dispatch actions. */
interface WorkflowConditionPreview
{
    /** Inspect the exact runtime context without producing side effects.
     *
     * @param FlowTaskContext $context
     *
     * @return array{allowed: bool, message: string}
     */
    public function preview(FlowTaskContext $context): array;
}
