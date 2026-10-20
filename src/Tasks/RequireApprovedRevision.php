<?php

namespace JobMetric\Post\Tasks;

use Illuminate\Support\Facades\DB;
use JobMetric\Flow\Contracts\AbstractRestrictionTask;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskDefinition;
use JobMetric\Flow\Support\RestrictionResult;
use JobMetric\Post\Contracts\WorkflowTaskConfiguration;

/** Permit a transition only for the exact approved content revision. */
class RequireApprovedRevision extends AbstractRestrictionTask implements WorkflowTaskConfiguration
{
    use PostTaskConfiguration;

    public static function definition(): FlowTaskDefinition
    {
        return new FlowTaskDefinition('post::lifecycle.tasks.approved_revision', 'post::lifecycle.tasks.approved_revision_description', 'shield-tick', ['restriction']);
    }

    public function restriction(FlowTaskContext $context): RestrictionResult
    {
        $revisionId = (int) ($context->payload()['revision_id'] ?? 0);
        $approved = $revisionId > 0 && DB::table(config('post.tables.approval', 'post_revision_approvals'))
            ->where('revision_id', $revisionId)->exists();

        return $approved
            ? RestrictionResult::allow()
            : RestrictionResult::deny('approval_required', trans('post::lifecycle.approval_required'));
    }
}
