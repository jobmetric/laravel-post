<?php

namespace JobMetric\Post\Tasks;

use JobMetric\Form\FormBuilder;
use JobMetric\Post\Models\PostPublicationStream;

/** Supply the shared subject and empty configuration contract for post tasks. */
trait PostTaskConfiguration
{
    /**
     * Identify the independent language stream consumed by the flow runner.
     *
     * @return string
     */
    public static function subject(): string
    {
        return PostPublicationStream::class;
    }

    /**
     * Build the package form for tasks without configurable fields.
     *
     * @return FormBuilder
     */
    public function form(): FormBuilder
    {
        return new FormBuilder;
    }

    /**
     * Reject unregistered configuration by retaining no fields.
     *
     * @return array<string, mixed>
     */
    public function configurationRules(): array
    {
        return [];
    }

    /**
     * Expose no inspector fields for a configuration-free task.
     *
     * @return array<string, array<string, mixed>>
     */
    public function configurationFields(): array
    {
        return [];
    }
}
