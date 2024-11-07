<?php

if (!function_exists('getPostTypes')) {
    /**
     * Get the post type
     *
     * @param string|null $mode
     *
     * @return array
     */
    function getPostTypes(string $mode = null): array
    {
        $postTypes = collect(app('postType'));

        if ($mode === 'key') {
            return $postTypes->keys()->toArray();
        }

        return $postTypes->toArray();
    }
}