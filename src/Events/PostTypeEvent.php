<?php

namespace JobMetric\Post\Events;

class PostTypeEvent
{
    /**
     * The category type to be filled by the listener.
     *
     * @var array
     */
    public array $postType = [];

    /**
     * Add a type.
     *
     * @param array $params
     *
     * @return static
     */
    public function addType(array $params): static
    {
        $type = $params['type'];
        $label = $params['args']['label'] ?? null;
        $excerpt = $params['args']['translation']['excerpt'] ?? null;
        $excerpt = $params['args']['translation']['content'] ?? null;
        $translation = $params['args']['translation'] ?? [];
        $metadata = $params['args']['metadata'] ?? [];
        $has_url = $params['args']['has_url'] ?? false;
        $has_base_media = $params['args']['has_base_media'] ?? false;
        $media = $params['args']['media'] ?? [];

        if (!array_key_exists($type, $this->postType)) {
            $this->postType = array_merge($this->postType, [
                $type => [
                    'label' => $label,
                    'translation' => $translation,
                    'metadata' => $metadata,
                    'has_url' => $has_url,
                    'has_base_media' => $has_base_media,
                    'media' => $media,
                ],
            ]);
        }

        return $this;
    }
}
