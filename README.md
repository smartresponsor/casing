# Casing

Casing is the Smart Responsor Symfony bundle for support-case intake and lifecycle management. It owns case drafts, submitted cases, lifecycle transitions, contextual subject resolution, information requests, and the transactional outbox used to publish case events.

## Runtime contract

- PHP: `^8.4`
- Symfony: `^8.1`
- Bundle: `App\Casing\CasingBundle`
- Namespace: `App\Casing\`
- Package: `casing/case`

The bundle loads services from `config/services.yaml` and attribute routes from `config/routes.yaml`.

## Responsibility boundary

Casing owns case-specific entities, repositories, intake services, lifecycle rules, forms, events, and support workflows.

Shared platform responsibilities remain in their owning components:

- Objecting supplies reusable identity, audit, and state field packs.
- Cruding supplies generic CRUD routing and controller mechanics.
- Viewing supplies the shared presentation boundary.
- Interfacing supplies shared interface and shell contracts.

The component consumes these packages through Composer dependencies. Local development resolves them through sibling path repositories with symlinks; production must install the declared package surfaces rather than depend on sibling directory presence.

## Lifecycle

A submitted case starts in `submitted` and can move through the transitions enforced by `CaseEntity::transitionToAllowed()`. Resolution writes a `CaseResolvedEvent` to the outbox in the same persistence operation; the outbox processor dispatches supported events and marks messages as dispatched.

## Verification

Install the declared dependencies, then run:

```bash
composer validate
composer cs:check
composer test
```

The architecture test guards the `App\Casing\` PSR-4 layout, canonical DTO naming, and forbidden legacy source paths.

## Development notes

Keep Doctrine entities inside Casing operations and exchange scalar identifiers, DTOs, or result values across external and asynchronous boundaries. Add business actions here only when they express case behavior; reusable system fields, generic CRUD mechanics, presentation infrastructure, and shell integration belong to their respective helper components.

## Current acceptance constraints

The component-level checks can be run independently, but full-suite acceptance also loads sibling packages from the local path-repository composition. A failure originating in a sibling package must be repaired in that package rather than bypassed from Casing.

The current administrative controller extends EasyAdmin's generic CRUD controller. This is an identified boundary exception pending migration to the shared Cruding surface; new generic CRUD controllers or generic CRUD route declarations must not be added inside Casing.
