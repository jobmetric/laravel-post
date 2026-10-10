# Post for laravel

This is a post management package for Laravel that you can use in your projects.

## Install via composer

Run the following command to pull in the latest version:

```bash
composer require jobmetric/laravel-post
```

## Documentation

## Registering a post type

Post types share one `Post` model and are configured through `PostTypeRegistry`. Register them in a service provider after the taxonomy types they use are registered:

```php
use JobMetric\Post\Facades\PostTypeRegistry;

PostTypeRegistry::register('article')
    ->label('articles.title')
    ->translationFields(['title', 'excerpt', 'content'])
    ->allowTaxonomy('category', 'categories', true)
    ->mediaCollection('base', false, ['image'])
    ->mediaCollection('gallery', true, ['image'])
    ->metadataFields(['subtitle', 'featured'])
    ->urlPrefix('articles')
    ->comments()
    ->workflow('article');
```

Taxonomy attachments are checked against both the registered taxonomy type and the collection configured for the post type. Translated fields, metadata keys, media collections, URL prefix, comments and workflow are all type capabilities. `Post::scopeOfType()` and `Post::scopePublished()` are available for queries.

Editor.js image blocks validate their media identifiers and keep those files attached in the internal `editor` media collection, so file usage and revision restore remain accurate.

Any Eloquent model can attach posts through named collections with `JobMetric\Post\HasPost`. Declare its allowed collections by overriding `$postCollections`, then use `postsIn()`, `attachPost()` or `syncPosts()`; the default collection is `default` and accepts multiple posts.

Updates create a JSON revision containing the post's own attributes, translations, metadata and relationship identifiers. Restore with `$post->restoreRevision($revisionId)`. Restoring a revision does not revert workflow state or delete shared taxonomy and media records.

## Post workflows

`JobMetric\\Post\\Services\\PostWorkflow` provides reusable status discovery and transition execution for any registered post type. `availableStatuses()` returns the states reachable from the current or start state, while `availableStatusOptions()` and `allStatusOptions()` return localized workflow labels. `transition()` executes the matching Flow transition and throws `PostStatusTransitionNotAllowedException` when the configured flow does not permit the change. Applications can adapt that domain exception to their own validation response and provide fallback labels for untranslated states.
