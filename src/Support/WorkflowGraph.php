<?php

namespace JobMetric\Post\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JobMetric\Flow\Support\FlowTaskRegistry;
use JobMetric\Post\Contracts\WorkflowTaskConfiguration;
use JobMetric\Post\Models\PostPublicationStream;

/** Validate workflow structure for drafts and full reachability for activation. */
class WorkflowGraph
{
    /**
     * Validate references and allowlisted task configuration; optionally require reachability.
     *
     * @param array<string, mixed> $graph
     * @param bool $requireReachability
     *
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validate(array $graph, bool $requireReachability = true): array
    {
        $graph = Validator::make($graph, [
            'start_position' => 'sometimes|array:x,y',
            'start_position.x' => 'required_with:start_position|numeric|between:-100000,100000',
            'start_position.y' => 'required_with:start_position|numeric|between:-100000,100000',
            'start_connection' => 'sometimes|array:to_side',
            'start_connection.to_side' => 'required_with:start_connection|in:top,right,bottom,left',
            'states' => 'required|array|min:1|max:100',
            'states.*.id' => 'required|string|max:80|distinct',
            'states.*.status' => ['required', 'string', 'max:80', 'distinct', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'states.*.content_status' => ['sometimes', 'string', 'in:editable,publish,future,archive,disabled'],
            'states.*.name' => 'required|string|max:160',
            'states.*.description' => 'sometimes|nullable|string|max:2000',
            'states.*.is_start' => 'required|boolean',
            'states.*.is_terminal' => 'required|boolean',
            'states.*.allows_approval' => 'sometimes|boolean',
            'states.*.position.x' => 'sometimes|numeric|between:-100000,100000',
            'states.*.position.y' => 'sometimes|numeric|between:-100000,100000',
            'states.*.translations' => 'sometimes|array',
            'states.*.translations.*.name' => 'required|string|max:160',
            'states.*.translations.*.description' => 'sometimes|nullable|string|max:2000',
            'transitions' => 'present|array|max:500',
            'transitions.*.id' => 'required|string|max:80|distinct',
            'transitions.*.from' => 'required|string|max:80',
            'transitions.*.to' => 'required|string|max:80',
            'transitions.*.slug' => ['required', 'string', 'max:80', 'distinct', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'transitions.*.label' => 'required|string|max:160',
            'transitions.*.anchors' => 'sometimes|array:from_side,to_side',
            'transitions.*.anchors.from_side' => 'sometimes|in:top,right,bottom,left',
            'transitions.*.anchors.to_side' => 'sometimes|in:top,right,bottom,left',
            'transitions.*.translations' => 'sometimes|array',
            'transitions.*.translations.*.name' => 'required|string|max:160',
            'transitions.*.tasks' => 'present|array|max:50',
            'transitions.*.tasks.*.driver' => 'required|string|max:255',
            'transitions.*.tasks.*.config' => 'present|array',
            'transitions.*.tasks.*.status' => 'sometimes|boolean',
        ])->validate();
        $states = collect($graph['states'])->keyBy('id');
        $start = $states->where('is_start', true);
        if ($start->count() !== 1) {
            $this->invalid('states');
        }
        foreach ($graph['states'] as $index => &$node) {
            $node['content_status'] ??= 'editable';
            if ($node['is_start'] && $node['content_status'] !== 'editable') {
                $this->invalid('states.'.$index.'.content_status');
            }
        }
        unset($node);
        $connections = [];
        foreach ($graph['transitions'] as $i => &$edge) {
            if (! $states->has($edge['from']) || ! $states->has($edge['to']) || $states[$edge['from']]['is_terminal']) {
                $this->invalid('transitions.'.$i.'.to');
            }
            if ($edge['from'] === $edge['to']) {
                $fromSide = $edge['anchors']['from_side'] ?? null;
                $toSide = $edge['anchors']['to_side'] ?? null;
                if ($fromSide !== null && $toSide !== null && $fromSide !== $toSide) {
                    $this->invalid('transitions.'.$i.'.anchors.to_side');
                }
                $side = $fromSide ?? $toSide ?? 'right';
                $edge['anchors'] = ['from_side' => $side, 'to_side' => $side];
            }
            $connection = $edge['from'].'>'.$edge['to'];
            if ($edge['slug'] === '__start__' || isset($connections[$connection])) {
                $this->invalid('transitions.'.$i.'.slug');
            }
            $connections[$connection] = true;
            foreach ($edge['tasks'] as $j => &$task) {
                /** @var FlowTaskRegistry $registry */
                $registry = app('FlowTaskRegistry');
                $driver = $registry->get($task['driver']);
                if (! $driver instanceof WorkflowTaskConfiguration || $driver::subject() !== PostPublicationStream::class) {
                    $this->invalid('transitions.'.$i.'.tasks.'.$j.'.driver');
                }
                $task['config'] = Validator::make($task['config'], $driver->configurationRules())->validate();
            }
            unset($task);
        }
        unset($edge);
        $visited = [(string) $start->first()['id']];
        do {
            $before = count($visited);
            foreach ($graph['transitions'] as $edge) {
                if (in_array($edge['from'], $visited, true) && ! in_array($edge['to'], $visited, true)) {
                    $visited[] = $edge['to'];
                }
            }
        } while (count($visited) !== $before);
        if ($requireReachability && count($visited) !== $states->count()) {
            $this->invalid('states');
        }

        return $graph;
    }

    /**
     * Reject a graph at the field that prevents safe activation.
     *
     * @param string $field
     *
     * @return never
     */
    private function invalid(string $field): never
    {
        throw ValidationException::withMessages([$field => [trans('post::lifecycle.invalid_graph')]]);
    }
}
