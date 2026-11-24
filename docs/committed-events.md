# Committed business outcomes

Classes are registered by the package provider alongside legacy events. Keys ending in `.committed` are safe automation outcomes; earlier event signatures, payloads and timing are preserved. Only scalar identifiers are exposed.

| Event class | Key | Payload properties |
| --- | --- | --- |
| `JobMetric\Post\Events\Post\PostStoreCommittedEvent` | `post.post.stored.committed` | `id: int|string` |
| `JobMetric\Post\Events\Post\PostUpdateCommittedEvent` | `post.post.updated.committed` | `id: int|string` |
| `JobMetric\Post\Events\Post\PostDeleteCommittedEvent` | `post.post.deleted.committed` | `id: int|string` |
| `JobMetric\Post\Events\Post\PostRestoreCommittedEvent` | `post.post.restored.committed` | `id: int|string` |
| `JobMetric\Post\Events\Post\PostForceDeleteCommittedEvent` | `post.post.force_deleted.committed` | `id: int|string` |
| `JobMetric\Post\Events\PostMedia\PostMediaSyncCommittedEvent` | `post.post_media.synced.committed` | `id: int|string` |
| `JobMetric\Post\Events\PostTaxonomy\PostTaxonomySyncCommittedEvent` | `post.post_taxonomy.synced.committed` | `id: int|string` |
| `JobMetric\Post\Events\PostRevision\PostRevisionStoreCommittedEvent` | `post.post_revision.stored.committed` | `postId: int`, `streamId: int`, `revisionId: ?int`, `actorId: int|string|null`, `scheduleId: ?int` |
| `JobMetric\Post\Events\Publication\PublicationSubmitCommittedEvent` | `post.publication.submitted.committed` | `postId: int`, `streamId: int`, `revisionId: ?int`, `actorId: int|string|null`, `scheduleId: ?int` |
| `JobMetric\Post\Events\Publication\PublicationApproveCommittedEvent` | `post.publication.approved.committed` | `postId: int`, `streamId: int`, `revisionId: ?int`, `actorId: int|string|null`, `scheduleId: ?int` |
| `JobMetric\Post\Events\Publication\PublicationRejectCommittedEvent` | `post.publication.rejected.committed` | `postId: int`, `streamId: int`, `revisionId: ?int`, `actorId: int|string|null`, `scheduleId: ?int` |
| `JobMetric\Post\Events\Publication\PublicationPublishCommittedEvent` | `post.publication.published.committed` | `postId: int`, `streamId: int`, `revisionId: ?int`, `actorId: int|string|null`, `scheduleId: ?int` |
| `JobMetric\Post\Events\Publication\PublicationRestoreCommittedEvent` | `post.publication.restored.committed` | `postId: int`, `streamId: int`, `revisionId: ?int`, `actorId: int|string|null`, `scheduleId: ?int` |
| `JobMetric\Post\Events\Schedule\SchedulePauseCommittedEvent` | `post.schedule.paused.committed` | `postId: int`, `streamId: int`, `revisionId: ?int`, `actorId: int|string|null`, `scheduleId: ?int` |
| `JobMetric\Post\Events\Schedule\ScheduleStoreCommittedEvent` | `post.schedule.stored.committed` | `postId: int`, `streamId: int`, `revisionId: ?int`, `actorId: int|string|null`, `scheduleId: ?int` |
| `JobMetric\Post\Events\Schedule\ScheduleResumeCommittedEvent` | `post.schedule.resumed.committed` | `postId: int`, `streamId: int`, `revisionId: ?int`, `actorId: int|string|null`, `scheduleId: ?int` |
| `JobMetric\Post\Events\Schedule\ScheduleExecuteCommittedEvent` | `post.schedule.executed.committed` | `postId: int`, `streamId: int`, `revisionId: ?int`, `actorId: int|string|null`, `scheduleId: ?int` |
| `JobMetric\Post\Events\Schedule\ScheduleCancelCommittedEvent` | `post.schedule.cancelled.committed` | `postId: int`, `streamId: int`, `revisionId: ?int`, `actorId: int|string|null`, `scheduleId: ?int` |
| `JobMetric\Post\Events\Publication\PublicationWithdrawCommittedEvent` | `post.publication.withdrawn.committed` | `postId: int`, `streamId: int`, `revisionId: ?int`, `actorId: int|string|null`, `scheduleId: ?int` |
| `JobMetric\Post\Events\Publication\PublicationTransitionCommittedEvent` | `post.publication.transitioned.committed` | `postId: int`, `streamId: int`, `revisionId: ?int`, `actorId: int|string|null`, `scheduleId: ?int` |
| `JobMetric\Post\Events\Publication\PublicationUpdateCommittedEvent` | `post.publication.updated.committed` | `postId: int`, `streamId: int`, `revisionId: ?int`, `actorId: int|string|null`, `scheduleId: ?int` |
| `JobMetric\Post\Events\Workflow\WorkflowStoreCommittedEvent` | `post.workflow.stored.committed` | `id: int|string` |
| `JobMetric\Post\Events\Workflow\WorkflowActivateCommittedEvent` | `post.workflow.activated.committed` | `id: int|string` |

Schedule from the owning operation after successful persistence. Delivery waits for the owning connection outer commit and is discarded on rollback. No-op operations emit nothing. Native Laravel listeners are isolated individually; failures are logged with event key and exception class only. No models, raw inputs, credentials or translated content are included. This is synchronous delivery without durable outbox guarantees.
