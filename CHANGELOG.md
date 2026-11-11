# Changelog

## Unreleased

- Add optional, versioned workflows per post type with validated graphs, draft definitions, impact previews, and explicit status mapping on activation.
- Add independent language publication streams, shared-field revisions, exact-version approvals, live snapshots, and auditable transitions.
- Add scheduled publication policies that either preserve an approved revision or pause until a manager explicitly resumes it; provide a registry for transactional scheduled actions.
- Keep legacy global status columns for compatibility while exposing locale-specific live queries and publication reads.

## 1.1.1

- Add equivalent `hasPassword()` and `password()` post type capabilities and reject passwords for types without that capability.

## 1.1.0

- Split post type capabilities into documented, reusable builder traits.
- Add a package-owned post persistence service and facade with transactional validation, workflow handling and named relation synchronization.
- Preserve associations when the same media or taxonomy appears in multiple collections.
- Capture one pre-edit revision for aggregate writes, including relation-only edits.
- Add registry lifecycle methods and package-owned SEO translations.
- Make the package test bootstrap usable independently of Jobic.
