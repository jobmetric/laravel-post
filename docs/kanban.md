# Multiple boards capability

`kanban()` enables a simple board. `multipleKanban()` also enables Kanban; `hasMultipleKanban()` reads the capability. Both default to false. `kanban(false)` disables both; `multipleKanban(false)` retains the simple board. Chain calls apply in order. In declarative `apply()` configuration, `multiple_kanban: true` implies `kanban: true` regardless of key order; both values must be booleans. Applications own board definitions, filters, access and ordering.
