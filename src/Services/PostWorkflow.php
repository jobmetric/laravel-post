<?php

namespace JobMetric\Post\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use JobMetric\Flow\Facades\FlowTransition;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Post\Exceptions\PostStatusTransitionNotAllowedException;
use JobMetric\Post\Models\Post;

/** Resolve post workflow states and execute allowed status transitions. */
class PostWorkflow
{
    /**
     * Return statuses reachable from the current state or the start state.
     *
     * @param Post $post
     *
     * @return array<int, string>
     */
    public function availableStatuses(Post $post): array
    {
        $flow = $post->exists ? $post->boundFlow() : $post->pickFlow();
        if (!$flow) return [];

        $current = $this->state($flow, (string) $post->status)
            ?? $flow->states()->start()->first();
        if (!$current) return [];

        $ids = $flow->transitions()->where('from', $current->id)->whereNotNull('to')->pluck('to');

        return $flow->states()->whereIn('id', $ids->push($current->id))->whereNotNull('status')
            ->pluck('status')->unique()->values()->all();
    }

    /**
     * Return reachable workflow states keyed by status.
     *
     * @param Post $post
     * @param string $locale
     *
     * @return array<string, string>
     */
    public function availableStatusOptions(Post $post, string $locale): array
    {
        $flow = $post->exists ? $post->boundFlow() : $post->pickFlow();
        if (!$flow) return [];

        return $this->labelStates($flow->states()->whereIn('status', $this->availableStatuses($post))->get(), $locale);
    }

    /**
     * Return all workflow states keyed by status.
     *
     * @param Post $post
     * @param string $locale
     *
     * @return array<string, string>
     */
    public function allStatusOptions(Post $post, string $locale): array
    {
        $flow = $post->exists ? $post->boundFlow() : $post->pickFlow();
        if (!$flow) return [];

        return $this->labelStates($flow->states()->whereNotNull('status')->get(), $locale);
    }

    /**
     * Start a new post workflow or move a post to a reachable state.
     *
     * @param Post $post
     * @param string $target
     * @param Authenticatable|null $actor
     * @param bool $isNew
     *
     * @return void
     * @throws PostStatusTransitionNotAllowedException
     */
    public function transition(Post $post, string $target, ?Authenticatable $actor, bool $isNew = false): void
    {
        $flow = $post->boundFlow();
        if (!$flow) {
            if (Post::typeRegistry()->getOption((string) $post->type, 'workflow') !== null) $this->invalidTransition();
            if ((string) $post->status !== $target) {
                $post->status = $target;
                $post->save();
            }
            return;
        }

        if ($isNew) {
            $start = $flow->states()->start()->first();
            if (!$start) $this->invalidTransition();
            $startTransition = $flow->transitions()->whereNull('from')->where('to', $start->id)->first();
            if (!$startTransition) $this->invalidTransition();
            FlowTransition::runner((int) $startTransition->id, $post, [], $actor);
        }

        if ((string) $post->status === $target) return;

        $from = $this->state($flow, (string) $post->status);
        $to = $this->state($flow, $target);
        if (!$from || !$to) $this->invalidTransition();
        $transition = $flow->transitions()->where('from', $from->id)->where('to', $to->id)->first();
        if (!$transition) $this->invalidTransition();

        FlowTransition::runner((int) $transition->id, $post, [], $actor);
    }

    /**
     * Find a state by its domain status within one workflow.
     *
     * @param Flow $flow
     * @param string $status
     *
     * @return FlowState|null
     */
    private function state(Flow $flow, string $status): ?FlowState
    {
        return $flow->states()->where('status', $status)->first();
    }

    /**
     * Convert workflow state models to status labels using their translations.
     *
     * @param iterable<FlowState> $states
     * @param string $locale
     *
     * @return array<string, string>
     */
    private function labelStates(iterable $states, string $locale): array
    {
        $options = [];
        foreach ($states as $state) {
            if ($state->status !== null) {
                $options[(string) $state->status] = (string) ($state->getTranslation('name', $locale) ?: $state->status);
            }
        }

        return $options;
    }

    /**
     * Reject a status transition that the configured workflow does not allow.
     *
     * @return never
     * @throws PostStatusTransitionNotAllowedException
     */
    private function invalidTransition(): never
    {
        throw new PostStatusTransitionNotAllowedException('The configured workflow does not allow this post status transition.');
    }
}
