# MT-1.2 - Design checkpoint

This checkpoint completes the unified data, API and security **design**. It does not implement applications, apply migrations, access business data or activate integrations. MT-1.3 is the next pending point; the current stage is not complete.

## Artifacts

- [Unified data design](UNIFIED_DATA_DESIGN.md): schema ownership, collisions, customer linking, historical snapshots, precision, import reconciliation and encrypted recovery.
- [Transaction, API and security contracts](TRANSACTIONS_API_SECURITY.md): stock/payment/return boundaries, replay, authorization, browser sessions, cache and recovery.
- [Source mappings](SCHEMA_MAPPING.json): all 58 source-qualified tables, 42 models, 79 migration files and 316 source routes; source fields are retained by default, with explicit destination changes. This is not an executed schema dump.
- [OpenAPI contract](openapi.json): 36 design operations; every implementation status remains Pending. Provider-specific payloads require authentic contracts before activation.
- [Acceptance cases](DESIGN_ACCEPTANCE_CASES.json): 34 pending implementation cases assigned to existing roadmap points.
- [Design validation](DESIGN_VALIDATION.json): specification checks and artifact hashes.
- [Checkpoint verification](CHECKPOINT_VERIFICATION.json): control-document, Word and source-boundary evidence. Resolve the containing checkpoint commit through Git history; it cannot embed its own commit hash.

## Review findings and decisions

POS `User` is an outlet; Website `User` is a customer or an administrator. Keep the existing credential providers separate and map POS users to outlets. Link customer history only with verified ownership, never by a phone/email match. A Website product maps to canonical inventory plus a public listing; namespace the source identity and destination table to prevent ID collisions.

Source IMEI history permits re-entry after sale. Enforce uniqueness of active ownership while preserving past occurrences. Keep invoices, warranty clauses, money and customer snapshots immutable. Source Website refund/return status flags do not themselves perform a financial refund or physical stock mutation; required complete return acceptance remains MT-2.5/MT-2.7.

Online reservations retain the source expiry contract. COD uses an explicit non-expiring stock hold until collection or authorized cancellation, preventing expired reservations from making dispatched goods available. Late verified payment remains a recorded receipt requiring reconciliation if stock is no longer held. Refund intents reserve the refundable balance while pending or uncertain. MySQL locks and unique keys, not Redis locks or two-way synchronization, protect business state.

Preserve the source cart limit of 99 and the stricter checkout limit of 20 per line/50 lines/one outlet. Preserve optional checkout email, catalogue filters/sorts/published page size, comparisons, public content and payment-option selection. Harden guessable project-reference/mobile lookup into verified access recovery rather than returning a payment capability directly.

Redis starts with derived public cache and throttling only. MySQL-backed sessions, queue/event records and publication versions support deterministic recovery. Full-schema/object/key recovery replaces the insufficient POS-only backup scope. Real exports, provider acceptance and production actions remain H-01/H-02/H-03.

## Reproduction and limits

From the new repository root, use the bundled Python runtime. Install `openapi-spec-validator==0.7.2` only into ignored `.local/mt12/python-packages`, then run `tools/design/build_design_contracts.py` and `tools/design/verify_design.py`. The latter imports its isolated validator dependency directory; on this Windows host its install permissions required elevated access for the verification process. No application or source dependencies were changed.

Validation passes OpenAPI 3.1.1 structural checks, exact inventory coverage, security metadata, 18 positive/negative JSON schema examples and 486 reservation event traces. The traces explore the declared transition graph, not concurrent database transactions. All 34 actual implementation cases remain pending. Fresh MySQL locking/race/recovery tests, Laravel authorization/session/CSRF tests, provider authenticity, frontend builds and Playwright journeys remain required at their assigned points. Historical source tests were not rerun or relabeled as target tests.

MT-2.1 must produce the column-by-column migration manifest, implement the schema and run apply/rollback/reapply on disposable target MySQL. MT-2.2 through MT-3.4 implement and verify the designed services/contracts; later migration and release gates prove data and operational recovery. Unknown source fields or unresolved active financial/stock obligations block promotion.
