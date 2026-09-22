# MT-7.5 / W04 — verified provider event type boundary (22-Sep-2026)

**Status: W04 IN PROGRESS; scoped synthetic event/configuration verification PASS.** MT-7.5 remains open, 15/27 family milestones DONE and 12 OPEN. No merchant/provider activation authorized.

## Source and initial failure

- Production commit `d5d3ceef22dd30a2b8b54dbf967d2152804eb0f3` validates the seven verified adapter event fields as nonempty strings before receipt processing. Test commit `c717bc547e9b7d28b60b10ea67fc64790731d60c` adds 28 synthetic malformed-field cases. No authentic gateway, secret write or provider activation.
- Initial exact-source [CI run 35751789253](https://github.com/lawangin00/mobisttech/actions/runs/35751789253) **FAILED** in the unrelated P02 inventory navigation browser test (`net::ERR_ABORTED`). It separately passed 353 backend tests/10,190 assertions, including the W04 event test, 23 MySQL race/reset cases, static/build/budget/secret gates and first-outlet browser. The latter Website/final gates were skipped; do not relabel this failed run as passing.

## Material correction and terminal re-verification

- Browser correction `70ccc51acfdbf6dda4849fbd703992238e7b7ee9` uses the actual visible Inventory navigation after outlet selection, keeping product/variant/edit/persistence assertions. Additional route-allowlist test `e4d1c9574336e9a2a8c56f8d31a857a92e81c205`, merchant/mode fail-closed code `ad5c5f6abbc5c77aa7ec87fa998554f074b52f40`, and corresponding configuration-shape test `499c5bd1fd689f97406b4cc5b57004b4994a0eff` are in the later candidate.
- Exact-source request-only commit `afb012fb20ea369d813f79be9781b24d7d5b5137` pins source `34bfee0a5dd38b32df342d849877d6ac8c886103`. [Full CI run 35753196003](https://github.com/lawangin00/mobisttech/actions/runs/35753196003) **terminal SUCCESS**: request validation, PHP/backend regression, MySQL race/reset, both builds and safety budgets, first-outlet browser, all POS/Admin Playwright including P02, Website checkout/customer/project/public/performance, final schema/cleanup and tracked-artifact gates all succeeded. [Website-only run 35753196204](https://github.com/lawangin00/mobisttech/actions/runs/35753196204) separately finished SUCCESS for static/build gates. Detailed source and CI evidence: `MT-7.5_W04_CALLBACK_CONFIG_CURRENT_CI_2026-09-22.md`.

## Remaining W04 scope

The 52 historical W04 source-file / 16 source-route dispositions and independent owner/reference/replay/refund/mode/recovery parity remain open. Genuine provider contracts, owner enrollment, real signed callbacks, external refunds and settlement remain H-02 HOLD. COD is the only configured live Website payment channel; do not claim W04/family closure or run duplicate equivalent CI from this checkpoint.
