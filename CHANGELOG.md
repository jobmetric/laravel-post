# Changelog

## 1.3.1

- Validate graph and legacy task configuration before normalizing role restrictions.
- Keep malformed legacy data safe to inspect without weakening graph validation.

## 1.3.0

- Add explicit Kanban post-type capability and direct transition role settings.
- Normalize unambiguous legacy role tasks without removing ambiguous restrictions.
- Add read-only transition condition previews with an opt-in custom-driver contract.

## 1.2.5

- Validate and preserve manual workflow transition route offsets in graph anchors.

## 1.2.4

- Configure workflow transition restrictions with system roles instead of permission keys.
- Preserve existing permission-based workflow definitions until they are replaced with role assignments.

## 1.2.3

- Preserve and validate optional system role IDs for workflow state administration visibility.

## 1.2.2

- Preserve and validate optional administration view permission keys on workflow states.

## 1.2.1

- Cover direct status transitions and orphaned schedule cancellation with minimal committed outcomes.

## 1.2.0

- Add registered immutable business outcome events with English and Persian discovery metadata.
- Publish minimal allowlisted identifiers after the owning transaction commits; preserve all legacy event signatures and behavior.
- Suppress outcomes for validation failures, rollback and unchanged business state. Listener failures cannot invalidate committed writes. Delivery is synchronous without an outbox.
- Release the existing versioned publication streams, content revisions, approvals, schedules and workflow definitions used by Jobic, alongside their committed outcome contracts.


## 1.1.1

- Add equivalent `hasPassword()` and `password()` post type capabilities and reject passwords for types without that capability.

## 1.1.0

- Split post type capabilities into documented, reusable builder traits.
- Add a package-owned post persistence service and facade with transactional validation, workflow handling and named relation synchronization.
- Preserve associations when the same media or taxonomy appears in multiple collections.
- Capture one pre-edit revision for aggregate writes, including relation-only edits.
- Add registry lifecycle methods and package-owned SEO translations.
- Make the package test bootstrap usable independently of Jobic.
