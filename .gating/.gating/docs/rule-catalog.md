# Gating Rule Catalog

This catalog explains the first platform-wide Gating rules in human language.

Gating rules are not copied component scripts. A rule is promoted here only when it is fair to apply across the ecosystem or can be applied through a component profile.

| Rule | Human meaning | Scope |
|---|---|---|
| `profile.contract_validity` | Component profile must follow the common Gating profile contract. | Component profile |
| `structure.forbidden_architecture` | Do not allow `/src/Domain`, `Port`, `Adapter`, or `Adaptor` folders. | Platform hard-canon |
| `namespace.profile_match` | PHP namespaces must match the component namespace declared by profile. | Component profile |
| `layer.typed_class_location` | Class type must match the Symfony typed folder where the class lives. | Platform hard-canon |
| `mirror.service_interface` | Service classes must have mirrored ServiceInterface contracts when enabled. | Component profile |
| `composer.platform_constraint` | Composer must match the platform PHP/Symfony baseline. | Platform hard-canon |
| `database.table_prefix` | Doctrine table names must use the component database prefix. | Component profile |
| `route.owner_token` | Routes must expose a stable owner token and canonical token grammar. | Component profile |
| `mutation.safety_firewall` | Broad destructive cleanup/apply/fix scripts are blocked by default. | Platform safety-canon |
| `security.secret_leak` | Secret material must not be present in code/archive files. | Platform security-canon |
| `release.evidence_manifest` | Release evidence must exist when the selected profile requires it. | Component/release profile |
| `documentation.docblock_preservation` | Formatting/tooling must preserve descriptive docblocks. | Platform hard-canon |
| `inventory.component_surface` | Every target can produce a machine-readable component surface inventory. | Platform reporting-canon |

Rule set profiles are intentionally small overlays. They select which rules run for a given context: local development, architecture audit, strict gate, or release gate.
