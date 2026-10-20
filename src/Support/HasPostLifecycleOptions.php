<?php

namespace JobMetric\Post\Support;

use InvalidArgumentException;
use JobMetric\Post\Enums\ScheduledEditPolicy;

/** Configure publication ownership and scheduled-edit behavior for a post type. */
trait HasPostLifecycleOptions
{
    /**
     * Choose whether a saved revision preserves or pauses its pending action.
     *
     * @param ScheduledEditPolicy $policy
     *
     * @return static
     */
    public function scheduledEditPolicy(ScheduledEditPolicy $policy): static
    {
        return $this->option('scheduled-edit-policy', $policy->value);
    }

    /**
     * Read the policy without coupling consumers to registry storage.
     *
     * @return ScheduledEditPolicy
     */
    public function getScheduledEditPolicy(): ScheduledEditPolicy
    {
        return ScheduledEditPolicy::from($this->getTypeParam('scheduled-edit-policy', 'preserve'));
    }

    /**
     * Exclude internal metadata from public snapshots and approval requirements.
     *
     * @param list<string> $fields
     *
     * @return static
     */
    public function internalMetadata(array $fields): static
    {
        foreach ($fields as $field) {
            $this->assertFieldName($field);
        }

        return $this->option('internal-metadata', array_values(array_unique($fields)));
    }

    /**
     * Declare which application-owned fields need shared publication approval.
     *
     * @param array<string, string> $fields
     *
     * @return static
     */
    public function fieldOwnership(array $fields): static
    {
        foreach ($fields as $path => $owner) {
            if (! is_string($path) || ! preg_match('/^(slug|comment_status|metadata|media|taxonomies)(\.[a-z][a-z0-9_-]*|\.\*)?$/', $path)
                || ! in_array($owner, ['shared', 'internal'], true)) {
                throw new InvalidArgumentException('A field owner must be shared or internal for a registered public field path.');
            }
        }

        return $this->option('field-ownership', $fields);
    }

    /**
     * Resolve an exact field before its group wildcard or default ownership.
     *
     * @param string $path
     *
     * @return string
     */
    public function getFieldOwner(string $path): string
    {
        $fields = (array) $this->getTypeParam('field-ownership', []);
        if (str_starts_with($path, 'metadata.')
            && in_array(substr($path, strlen('metadata.')), (array) $this->getTypeParam('internal-metadata', []), true)) {
            return 'internal';
        }
        if (isset($fields[$path])) {
            return $fields[$path];
        }
        $group = explode('.', $path, 2)[0];

        return $fields[$group.'.*'] ?? $fields[$group] ?? 'shared';
    }
}
