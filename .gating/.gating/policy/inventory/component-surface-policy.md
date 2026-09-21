# Component surface inventory policy

Every component should be observable through a stable inventory report.

The inventory does not decide business correctness. It lists the component surface so Gating, Commanding, Administering, release reports, and future audits can speak the same language.

The executable rule `inventory.component_surface` collects read-only counts for PHP files, typed layers, route configuration, composer files, local tools, and documentation files.
