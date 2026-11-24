<?php

namespace JobMetric\Post\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use JobMetric\Rolix\Models\Role;
use JobMetric\Rolix\Models\RolePath;
use JobMetric\Post\Tasks\RequirePermission;

/** Resolve system role restrictions and upgrade unambiguous legacy transition tasks. */
class WorkflowTransitionAccess
{
    /** Resolve assigned and inherited system roles without a super-user bypass.
     *
     * @param Authenticatable|null $actor
     *
     * @return list<int>
     */
    public function roleIds(?Authenticatable $actor): array
    {
        if (! $actor || ! method_exists($actor, 'memberships')) return [];
        $assigned = $actor->memberships()->whereNull('memberable_type')->whereNull('memberable_id')
            ->where(fn ($q) => $q->whereNull('expired_at')->orWhere('expired_at', '>', now()))->pluck('role_id');
        $roles = Role::where('type', 'system')->whereIn('id', $assigned)->pluck('id');
        return $roles->merge(RolePath::whereIn('role_id', $roles)->where('level', '>', 0)->pluck('path_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** Decide direct transition access using any selected role.
     *
     * @param array<string, mixed> $edge
     * @param Authenticatable|null $actor
     *
     * @return bool
     */
    public function allows(array $edge, ?Authenticatable $actor): bool
    {
        $roles = array_map('intval', $edge['roles'] ?? []);
        return $roles === [] || array_intersect($roles, $this->roleIds($actor)) !== [];
    }

    /** Convert one role task while preserving every ambiguous legacy restriction.
     *
     * @param array<string, mixed> $graph
     *
     * @return array<string, mixed>
     */
    public function normalize(array $graph): array
    {
        if (! is_array($graph['transitions'] ?? null)) return $graph;
        foreach ($graph['transitions'] as $i => $edge) {
            if (! is_array($edge) || ! is_array($edge['tasks'] ?? null)) continue;
            $legacy = array_filter($edge['tasks'] ?? [], fn ($task) => is_array($task) && ($task['driver'] ?? '') === RequirePermission::class
                && ($task['status'] ?? true));
            if (count($legacy) !== 1 || ! empty($edge['roles'])) continue;
            $key = array_key_first($legacy);
            $config = $legacy[$key]['config'] ?? [];
            if (! is_array($config) || ! is_array($config['roles'] ?? null) || $config['roles'] === [] || ! empty($config['permission'])) continue;
            $graph['transitions'][$i]['roles'] = array_values($config['roles']);
            unset($graph['transitions'][$i]['tasks'][$key]);
            $graph['transitions'][$i]['tasks'] = array_values($graph['transitions'][$i]['tasks']);
        }
        return $graph;
    }
}
