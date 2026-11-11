<?php

namespace JobMetric\Post\Tasks;

use JobMetric\Flow\Contracts\AbstractValidationTask;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskDefinition;
use JobMetric\Post\Contracts\WorkflowTaskConfiguration;

/** Require meaningful text in the exact content revision supplied to a transition. */
class RequireContent extends AbstractValidationTask implements WorkflowTaskConfiguration
{
    use PostTaskConfiguration;

    public function configurationRules(): array
    {
        return [
            'min_title_characters' => ['sometimes', 'integer', 'between:1,300'],
            'min_body_characters' => ['sometimes', 'integer', 'between:0,10000'],
        ];
    }

    public function configurationFields(): array
    {
        return [
            'min_title_characters' => ['type' => 'number', 'label' => 'post::lifecycle.tasks.min_title_characters', 'min' => 1, 'max' => 300, 'default' => 1],
            'min_body_characters' => ['type' => 'number', 'label' => 'post::lifecycle.tasks.min_body_characters', 'min' => 0, 'max' => 10000, 'default' => 0],
        ];
    }

    /**
     * Describe this validation task for the translated driver library.
     *
     * @return FlowTaskDefinition
     */
    public static function definition(): FlowTaskDefinition
    {
        return new FlowTaskDefinition('post::lifecycle.tasks.content', 'post::lifecycle.tasks.content_description', 'file-text', ['validation']);
    }

    /**
     * Validate the title and content projected by the owning application.
     *
     * @param FlowTaskContext $context
     *
     * @return array<string, mixed>
     */
    public function rules(FlowTaskContext $context): array
    {
        $minTitle = (int) ($context->config()['min_title_characters'] ?? 1);
        $minBody = (int) ($context->config()['min_body_characters'] ?? 0);

        return [
            'content.title' => ['required', 'string', function ($attribute, $value, $fail) use ($minTitle): void {
                if (mb_strlen(trim(html_entity_decode(strip_tags($value)))) < $minTitle) {
                    $fail(trans('post::lifecycle.content_required'));
                }
            }],
            'content.content' => ['required', function ($attribute, $value, $fail) use ($minBody): void {
                if (! $this->meaningful($value) || ($minBody > 0 && mb_strlen($this->plainText($value)) < $minBody)) {
                    $fail(trans('post::lifecycle.content_required'));
                }
            }],
        ];
    }

    /**
     * Inspect application-neutral text or structured content without treating IDs as prose.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function meaningful(mixed $value): bool
    {
        if (is_string($value)) {
            return trim(html_entity_decode(strip_tags($value))) !== '';
        }
        if (! is_array($value)) {
            return false;
        }
        foreach ($value as $key => $item) {
            if (in_array($key, ['id', 'type', 'time', 'version', 'level'], true)) {
                continue;
            }
            if (($key === 'media_id' && is_numeric($item) && $item > 0) || $this->meaningful($item)) {
                return true;
            }
        }

        return false;
    }

    private function plainText(mixed $value): string
    {
        if (is_string($value)) {
            return trim(html_entity_decode(strip_tags($value)));
        }
        if (! is_array($value)) {
            return '';
        }

        return implode(' ', array_map(fn ($item): string => $this->plainText($item), $value));
    }
}
