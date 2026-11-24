<?php

namespace JobMetric\Post\Tasks;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use JobMetric\Flow\Contracts\AbstractRestrictionTask;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskDefinition;
use JobMetric\Flow\Support\RestrictionResult;
use JobMetric\CustomField\CustomFieldBuilder;
use JobMetric\Form\FormBuilder;
use JobMetric\Form\Tab\TabBuilder;
use JobMetric\Post\Contracts\WorkflowTaskConfiguration;
use JobMetric\Rolix\Models\Role;
use JobMetric\Rolix\Models\RolePath;

/** Restrict a transition to users with one of the selected system roles. */
class RequirePermission extends AbstractRestrictionTask implements WorkflowTaskConfiguration
{
    use PostTaskConfiguration;

    /**
     * Describe the role restriction for the driver library.
     *
     * @return FlowTaskDefinition
     */
    public static function definition(): FlowTaskDefinition
    {
        return new FlowTaskDefinition('post::lifecycle.tasks.roles', 'post::lifecycle.tasks.roles_description', 'shield-tick', ['restriction']);
    }

    /**
     * Build the role field using the existing form package.
     *
     * @return FormBuilder
     */
    public function form(): FormBuilder
    {
        return (new FormBuilder)->tab(static function (TabBuilder $tab): void {
            $tab->id('roles')->label('post::lifecycle.tasks.roles')
                ->customField(static function (CustomFieldBuilder $field): void {
                    $field::select()->name('roles')->label('post::lifecycle.tasks.roles_key')->multiple()->validation('required|array|min:1');
                });
        });
    }

    /**
     * Validate the selected system role identifiers.
     *
     * @return array<string, mixed>
     */
    public function configurationRules(): array
    {
        return [
            'roles' => ['required_without:permission', 'array', 'min:1', 'max:100'],
            'roles.*' => ['required', 'integer', 'min:1', 'distinct', Rule::exists((new Role)->getTable(), 'id')->where('type', 'system')],
            'permission' => ['required_without:roles', 'string', 'max:160', 'regex:/^[a-z][a-z0-9_.-]*$/'],
        ];
    }

    /**
     * Expose the role selector to the task inspector.
     *
     * @return array<string, array<string, mixed>>
     */
    public function configurationFields(): array
    {
        return ['roles' => [
            'type' => 'multiselect',
            'label' => 'post::lifecycle.tasks.roles_key',
            'required' => true,
            'default' => [],
        ]];
    }

    /**
     * Check whether the transition actor has one of the selected system roles.
     *
     * @param FlowTaskContext $context
     *
     * @return RestrictionResult
     */
    public function restriction(FlowTaskContext $context): RestrictionResult
    {
        $config = $context->config();
        $configuredRoles = $config['roles'] ?? [];
        $roleIds = is_array($configuredRoles) ? array_values(array_unique(array_map('intval', $configuredRoles))) : [];
        $actor = $context->user();

        if ($actor && $roleIds !== [] && array_intersect($roleIds, $this->effectiveSystemRoleIds($actor)) !== []) {
            return RestrictionResult::allow();
        }

        // Keep already-published graphs working until an administrator replaces their legacy key with roles.
        if ($actor && $roleIds === [] && is_string($config['permission'] ?? null) && $config['permission'] !== '') {
            return Gate::forUser($actor)->allows($config['permission'], $context->subject())
                ? RestrictionResult::allow()
                : RestrictionResult::deny('permission_denied', trans('post::lifecycle.permission_denied'));
        }

        return RestrictionResult::deny('role_denied', trans('post::lifecycle.role_denied'));
    }

    /**
     * Resolve the actor's active system roles and inherited parent roles.
     *
     * @param Authenticatable $actor
     *
     * @return list<int>
     */
    private function effectiveSystemRoleIds(Authenticatable $actor): array
    {
        if (! method_exists($actor, 'memberships')) {
            return [];
        }

        $assignedIds = $actor->memberships()
            ->whereNull('memberable_type')
            ->whereNull('memberable_id')
            ->where(fn ($query) => $query->whereNull('expired_at')->orWhere('expired_at', '>', now()))
            ->pluck('role_id');
        $roleIds = Role::query()->where('type', 'system')->whereIn('id', $assignedIds)
            ->pluck('id')->map(static fn ($id): int => (int) $id);
        $parentIds = RolePath::query()->whereIn('role_id', $roleIds)->where('level', '>', 0)
            ->pluck('path_id')->map(static fn ($id): int => (int) $id);

        return $roleIds->merge($parentIds)->unique()->values()->all();
    }
}
