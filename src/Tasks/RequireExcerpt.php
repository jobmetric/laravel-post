<?php

namespace JobMetric\Post\Tasks;

use JobMetric\Flow\Contracts\AbstractValidationTask;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskDefinition;
use JobMetric\Post\Contracts\WorkflowTaskConfiguration;

/** Require a readable excerpt before the transition can continue. */
class RequireExcerpt extends AbstractValidationTask implements WorkflowTaskConfiguration
{
    use PostTaskConfiguration;

    public static function definition(): FlowTaskDefinition
    {
        return new FlowTaskDefinition('post::lifecycle.tasks.excerpt', 'post::lifecycle.tasks.excerpt_description', 'file-text', ['validation']);
    }

    public function configurationRules(): array
    {
        return ['min_characters' => ['required', 'integer', 'between:1,1000']];
    }

    public function configurationFields(): array
    {
        return ['min_characters' => ['type' => 'number', 'label' => 'post::lifecycle.tasks.min_excerpt_characters', 'required' => true, 'min' => 1, 'max' => 1000, 'default' => 30]];
    }

    public function rules(FlowTaskContext $context): array
    {
        $minimum = (int) ($context->config()['min_characters'] ?? 30);

        return ['content.excerpt' => ['required', 'string', function ($attribute, $value, $fail) use ($minimum): void {
            if (mb_strlen(trim(html_entity_decode(strip_tags($value)))) < $minimum) {
                $fail(trans('post::lifecycle.excerpt_required'));
            }
        }]];
    }
}
