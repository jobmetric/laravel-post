<?php

namespace JobMetric\Post\Tasks;

use Illuminate\Support\Facades\Gate;
use JobMetric\Flow\Contracts\AbstractRestrictionTask;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskDefinition;
use JobMetric\Flow\Support\RestrictionResult;
use JobMetric\CustomField\CustomFieldBuilder;
use JobMetric\Form\FormBuilder;
use JobMetric\Form\Tab\TabBuilder;
use JobMetric\Post\Contracts\WorkflowTaskConfiguration;

/** Restrict a transition through the host application's authorization gate. */
class RequirePermission extends AbstractRestrictionTask implements WorkflowTaskConfiguration
{
    use PostTaskConfiguration;

    /**
     * Describe the permission restriction for the driver library.
     *
     * @return FlowTaskDefinition
     */
    public static function definition(): FlowTaskDefinition
    {
        return new FlowTaskDefinition('post::lifecycle.tasks.permission', 'post::lifecycle.tasks.permission_description', 'lock', ['restriction']);
    }

    /**
     * Build the permission field using the existing form package.
     *
     * @return FormBuilder
     */
    public function form(): FormBuilder
    {
        return (new FormBuilder)->tab(static function (TabBuilder $tab): void {
            $tab->id('permission')->label('post::lifecycle.tasks.permission')
                ->customField(static function (CustomFieldBuilder $field): void {
                    $field::text()->name('permission')->label('post::lifecycle.tasks.permission_key')->validation('required|string|max:160');
                });
        });
    }

    /**
     * Validate the explicitly selected permission key.
     *
     * @return array<string, mixed>
     */
    public function configurationRules(): array
    {
        return ['permission' => ['required', 'string', 'max:160', 'regex:/^[a-z][a-z0-9_.-]*$/']];
    }

    /**
     * Expose the permission control to the task inspector.
     *
     * @return array<string, array<string, mixed>>
     */
    public function configurationFields(): array
    {
        return ['permission' => ['type' => 'text', 'label' => 'post::lifecycle.tasks.permission_key', 'required' => true]];
    }

    /**
     * Evaluate the permission for the actual transition actor.
     *
     * @param FlowTaskContext $context
     *
     * @return RestrictionResult
     */
    public function restriction(FlowTaskContext $context): RestrictionResult
    {
        $permission = $context->config()['permission'] ?? '';
        return $context->user() && $permission !== '' && Gate::forUser($context->user())->allows($permission, $context->subject())
            ? RestrictionResult::allow()
            : RestrictionResult::deny('permission_denied', trans('post::lifecycle.permission_denied'));
    }
}
