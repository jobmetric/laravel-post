# Kanban integration

Enable the administration capability with `->kanban()` or `apply(['kanban' => true])`; `hasKanban()` defaults to false.

Workflow transition `roles` is an optional list of existing system role IDs. Any assigned or inherited role permits the transition; an empty list adds no restriction. The runtime enforces this before any transition task or action. `WorkflowTransitionAccess` also exposes the shared role decision and converts single active legacy role tasks. Ambiguous legacy tasks remain enforced and must be reviewed by an administrator.

`WorkflowTransitionPreview::inspect` checks the exact draft revision, direct roles and known read-only restrictions/validations. It never runs action tasks. Unknown conditions return `unknown`; custom conditions may implement `WorkflowConditionPreview::preview` to return `{allowed, message}`. That contract forbids writes, event dispatch and other side effects. Execution must always repeat all conditions under the stream version lock; previews are advisory and can become stale.
