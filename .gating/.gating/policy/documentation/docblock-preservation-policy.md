# Docblock preservation policy

Descriptive docblocks are part of the Smart Responsor documentation surface.

Formatters and automated refactoring tools may adjust signature/type-related docblock details, but they must not remove human explanatory comments that describe behavior, intent, invariants, or operational context.

The executable rule `documentation.docblock_preservation` scans tool configuration for known risky docblock-stripping markers.
