<?php

namespace JobMetric\Post\Support;

use InvalidArgumentException;

/**
 * Configure labels, permissions and optional administration capabilities.
 */
trait HasPostAdministrationOptions
{
    /**
     * Set the translation key or literal label for this post type.
     *
     * @param string $label
     *
     * @return static
     */
    public function label(string $label): static
    {
        return $this->option('label', $label);
    }

    /**
     * Resolve the post type label using the current application locale.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return trans($this->getTypeParam('label', ''));
    }

    /**
     * Set the translation key or literal description for this post type.
     *
     * @param string $description
     *
     * @return static
     */
    public function description(string $description): static
    {
        return $this->option('description', $description);
    }

    /**
     * Resolve the description using the current application locale.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return trans($this->getTypeParam('description', ''));
    }

    /**
     * Set the shared permission prefix used by view and management permissions.
     *
     * @param string $permission
     *
     * @return static
     * @throws InvalidArgumentException
     */
    public function permission(string $permission): static
    {
        $this->assertPermission($permission);

        return $this->option('permission', $permission);
    }

    /**
     * Return the shared permission prefix when one is configured.
     *
     * @return string|null
     */
    public function getPermission(): ?string
    {
        return $this->getTypeParam('permission');
    }

    /**
     * Override the permission required to view this post type.
     *
     * @param string $permission
     *
     * @return static
     * @throws InvalidArgumentException
     */
    public function viewPermission(string $permission): static
    {
        $this->assertPermission($permission);

        return $this->option('view-permission', $permission);
    }

    /**
     * Resolve the explicit view permission or derive it from the shared prefix.
     *
     * @return string|null
     */
    public function getViewPermission(): ?string
    {
        return $this->registry->getOption(
            $this->type,
            'view-permission',
            $this->getPermission() === null ? null : $this->getPermission() . '.view'
        );
    }

    /**
     * Override the permission required to manage this post type.
     *
     * @param string $permission
     *
     * @return static
     * @throws InvalidArgumentException
     */
    public function managePermission(string $permission): static
    {
        $this->assertPermission($permission);

        return $this->option('manage-permission', $permission);
    }

    /**
     * Resolve the explicit management permission or derive it from the shared prefix.
     *
     * @return string|null
     */
    public function getManagePermission(): ?string
    {
        return $this->registry->getOption(
            $this->type,
            'manage-permission',
            $this->getPermission() === null ? null : $this->getPermission() . '.manage'
        );
    }

    /**
     * Configure whether administration clients must reject stale edits.
     *
     * @param bool $enabled
     *
     * @return static
     */
    public function optimisticLocking(bool $enabled = true): static
    {
        return $this->option('optimistic-locking', $enabled);
    }

    /**
     * Determine whether administration clients should require edit versions.
     *
     * @return bool
     */
    public function hasOptimisticLocking(): bool
    {
        return (bool) $this->getTypeParam('optimistic-locking', false);
    }

    /**
     * Enable contextual help in administration clients.
     *
     * @return static
     */
    public function help(): static
    {
        return $this->option('has-help', true);
    }

    /**
     * Determine whether contextual help is enabled.
     *
     * @return bool
     */
    public function hasHelp(): bool
    {
        return (bool) $this->getTypeParam('has-help', false);
    }

    /**
     * Set the destination used to return from administration pages.
     *
     * @param string $url
     *
     * @return static
     * @throws InvalidArgumentException
     */
    public function backUrl(string $url): static
    {
        if (trim($url) === '') {
            throw new InvalidArgumentException('A non-empty post back URL is required.');
        }
        return $this->option('back-url', $url);
    }

    /**
     * Return the configured administration return URL.
     *
     * @return string|null
     */
    public function getBackUrl(): ?string
    {
        return $this->getTypeParam('back-url');
    }

    /**
     * Enable bulk status changes in administration lists.
     *
     * @return static
     */
    public function changeStatusInList(): static
    {
        return $this->option('change-status-in-list', true);
    }

    /**
     * Determine whether administration lists may offer status changes.
     *
     * @return bool
     */
    public function hasChangeStatusInList(): bool
    {
        return (bool) $this->getTypeParam('change-status-in-list', false);
    }

    /**
     * Reject permission identifiers that do not follow the dotted key convention.
     *
     * @param string $permission
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertPermission(string $permission): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/', $permission)) {
            throw new InvalidArgumentException('Invalid post administration permission.');
        }
    }
}
