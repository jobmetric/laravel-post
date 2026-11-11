<?php

namespace JobMetric\Post\Contracts;

/** Expose trusted task configuration validation and its editor field schema. */
interface WorkflowTaskConfiguration
{
    /**
     * Return validation rules for persisted driver configuration.
     *
     * @return array<string, mixed>
     */
    public function configurationRules(): array;

    /**
     * Return translated form field descriptors for the task inspector.
     *
     * @return array<string, array<string, mixed>>
     */
    public function configurationFields(): array;
}
