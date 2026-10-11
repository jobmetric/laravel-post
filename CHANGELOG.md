# Changelog

## 1.1.0

- Split post type capabilities into documented, reusable builder traits.
- Add a package-owned post persistence service and facade with transactional validation, workflow handling and named relation synchronization.
- Preserve associations when the same media or taxonomy appears in multiple collections.
- Capture one pre-edit revision for aggregate writes, including relation-only edits.
- Add registry lifecycle methods and package-owned SEO translations.
- Make the package test bootstrap usable independently of Jobic.
