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
    ->permission('admin.articles')
    ->translationFields(['title', 'excerpt', 'content'])
    ->taxonomy('category', 'categories', true)
    ->seo()
    ->mediaCollection('base', false, ['image'])
    ->mediaCollection('gallery', true, ['image'])
    ->metadataFields(['subtitle', 'featured'])
    ->url('articles')
    ->comments()
    ->workflow('article');
```

Taxonomy attachments are checked against both the registered taxonomy type and the collection configured for the post type. Translated fields, metadata keys, media collections, URL prefix, comments and workflow are all type capabilities. `Post::scopeOfType()` and `Post::scopePublished()` are available for queries.

Post administration permissions use the same explicit `viewPermission()` and `managePermission()` chains as taxonomy types. Their `getViewPermission()` and `getManagePermission()` accessors are used by application middleware and navigation. The `url($prefix = '')` chain enables URL support and configures the public path prefix together; `hasUrl()` and `getUrlPrefix()` expose the resulting configuration.

The package does not depend on an editor or administration panel. Applications validate their editor documents and can synchronize referenced images through the reserved `editor` media collection.

Any Eloquent model can attach posts through named collections with `JobMetric\Post\HasPost`. Declare its allowed collections by overriding `$postCollections`, then use `postsIn()`, `attachPost()` or `syncPosts()`; the default collection is `default` and accepts multiple posts.

Updates create a JSON revision containing the post's own attributes, translations, metadata and relationship identifiers. Restore with `$post->restoreRevision($revisionId)`. Restoring a revision does not revert workflow state or delete shared taxonomy and media records.

## Persisting content outside Jobic

The package service validates registered fields and collection policies before changing the aggregate. Each write is transactional. Omitted collections are preserved; an empty collection explicitly removes its associations. Media and taxonomy records themselves are never deleted by collection synchronization.

```php
use JobMetric\Post\Facades\Post;

$result = Post::store('article', [
    'status' => 'draft',
    'translation' => ['en' => ['title' => 'First article', 'content' => 'Content']],
    'metadata' => ['featured' => true],
    'media' => ['base' => [12], 'gallery' => [12, 13]],
    'taxonomies' => ['categories' => [4]],
], $actor);

$post = $result->data['post'];
Post::update('article', $post->id, ['media' => ['gallery' => []]], $actor);
```

Services return `JobMetric\PackageCore\Output\Response`; invalid payloads throw Laravel's field-keyed `ValidationException`. Missing records use Eloquent's normal model-not-found exception. Authentication, authorization, HTTP responses, audit policy and editor-specific validation belong to the host application.

`seo()` and `hasSeo()` enable the same translated meta fields, global `seo_index` metadata and `seo_image` collection. `seo(false)` disables them. Builder configuration is grouped into content, media, taxonomy, filters and administration traits; existing fluent method names remain available. `unregister()` and `clear()` reset registry definitions without deleting stored content.

## Running tests

Use `phpunit -c phpunit.xml` with the package dependencies installed. In the shared JobMetric workspace, the bootstrap also supports the workspace Composer autoloader. Set `POST_TEST_AUTOLOAD` to an explicit autoloader path when verifying against another supported Laravel installation.

## Post workflows

`JobMetric\\Post\\Services\\PostWorkflow` provides reusable status discovery and transition execution for any registered post type. `availableStatuses()` returns the states reachable from the current or start state, while `availableStatusOptions()` and `allStatusOptions()` return localized workflow labels. `transition()` executes the matching Flow transition and throws `PostStatusTransitionNotAllowedException` when the configured flow does not permit the change. Applications can adapt that domain exception to their own validation response and provide fallback labels for untranslated states.
