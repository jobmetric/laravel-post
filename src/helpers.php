<?php

use JobMetric\Post\Exceptions\PostTypeNotMatchException;

if (!function_exists('getPostTypes')) {
    /**
     * Get the post type
     *
     * @param string|null $mode
     * @param string|null $name $type = null
     *
     * @return array
     */
    function getPostTypes(string $mode = null, string $type = null): array
    {
        $postTypes = collect(app('postType'));

        if ($mode === 'key') {
            return $postTypes->keys()->toArray();
        }

        if ($type) {
            return $taxonomyTypes[$type] ?? [];
        }

        return $postTypes->toArray();
    }
}


if (!function_exists('checkTypeInPostTypes')) {
    /**
     * Check type in post type
     *
     * @param string $type
     *
     * @return void
     * @throws Throwable
     */
    function checkTypeInPostTypes(string $type): void
    {
        $postTypes = getPostTypes();

        if (!array_key_exists($type, $postTypes)) {
            throw new PostTypeNotMatchException($type);
        }
    }
}
