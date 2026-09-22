# MT-7.5 / W04 — verified provider event type boundary (22-Sep-2026)

**Status: IN PROGRESS, hosted terminal acceptance pending.** W04 and MT-7.5 remain open; 15/27 family milestones DONE, 12 OPEN. No external merchant/provider activation is authorized by this checkpoint.

## Scoped source delta

- Production commit `d5d3ceef22dd30a2b8b54dbf967d2152804eb0f3` updates only `backend/app/Commerce/PaymentProviders.php`. After an adapter returns the exact seven-key verified-event shape, every required field must be a nonempty string before the code evaluates `status`, `currency`, or `payload_hash` or passes the event to receipt processing. This prevents malformed nested/null adapter outputs from reaching the receipt path as a PHP type error or ambiguous reference. Existing status/currency/hash checks and closed provider registry remain in place.
- Test commit `c717bc547e9b7d28b60b10ea67fc64790731d60c` adds `backend/tests/Feature/W04MalformedVerifiedProviderEventTest.php`: a synthetic JazzCash adapter returns a valid event and then 28 malformed required-field cases (null, array, empty and whitespace-only values for each of seven fields). Each malformed case must fail as `LogicException` with the existing controlled invalid-event message. Merchant configuration and mode remain unchanged. There is no database fixture, authentic gateway call or credential write.
- Exact-parent CI request commit `8db0522c583e46c0d2898a7c925ae7ab68f5efb3` pins `source_commit=c717bc547e9b7d28b60b10ea67fc64790731d60c`, `stage_id=MT-7.5`, `reason=necessary`, `gate=full`. [CI run 35751789253](https://github.com/lawangin00/mobisttech/actions/runs/35751789253) and [Website-only run 35751789515](https://github.com/lawangin00/mobisttech/actions/runs/35751789515) were queued when first observed; **no passing verdict is claimed yet**. The prior W04 read-only status endpoint gate passed separately in run `35738247964` and need not be repeated by a new request.

## First genuinely pending action

Read the terminal jobs and source SHA for existing run `35751789253`. On failure capture the exact failed step/test signature, classify and correct only the material defect before any new CI request; do not blindly rerun. On success record its terminal evidence, then resume the remaining independent W04 owner/amount/reference/refund/mode acceptance and nonsecret Admin settings/merchant-owner contract gates. Real provider contracts, signed external callbacks, external refunds/settlement and owner enrollment remain H-02 HOLD. W04 must not be marked complete from this synthetic gate alone.
