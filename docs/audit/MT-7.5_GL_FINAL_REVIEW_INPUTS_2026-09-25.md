# MT-7.5 26/G-L Final Owner / Legal Review Inputs

Date: 25-Sep-2026 PKT  
Stable gate: `26/G-L`  
State: **PRIVATE REVIEW INPUTS READY — NO LEGAL APPROVAL IMPLIED**

The technical/privacy/product facts needed for final legal review are now reconciled. G-R independently validates the desktop mobiST POS facts; G-C validates protected policy drafting/publication controls; dependency/NOTICE posture has no lockfile drift from the audited snapshot.

## Already fixed / not a decision

- D09=A: Mobisttech application is proprietary / all rights reserved.
- Third-party notices remain mandatory and independent of the root application license.
- Root `LICENSE` stays absent until the exact registered legal holder and final wording are verified.
- Browser/card handling forbids raw PAN/CVV/PIN storage; Website payment/provider distinctions are already technically reconciled.
- Cookie Policy remains conditional because no non-essential analytics/tracker integration is currently identified.
- Desktop mobiST POS Overview/Privacy/Terms/FAQ facts are independently validated; D08=B still prohibits public real-product publication.
- Policy records remain private/unapproved until explicit factual review, owner approval, effective date/version and zero unresolved decisions are recorded.

## Exact human/legal inputs still required

1. **Registered legal holder**
   - Exact registered sole-proprietor/legal business name to use for copyright/ownership.
   - Exact public business/trading name if different.
   - Do not infer this from branding.

2. **Website commercial policy terms**
   - A previously owner-approved POS invoice clause is available as a factual review input and must be preserved verbatim until the owner decides whether/how it applies to the Website/public policy: `????? ??? ??? ???? ???? ????? ???????? ?? ????/?????? ?? ???? ?? 20% ?? 40% ?? ????? ????? ?? ?????? ?? ????? ??? ???? ?? ???? ?????? ?????` This historical clause resolves neither Website scope nor legal publication approval by itself.
   - Still required for public Returns/Exchanges: exact applicability (which products/channels), exchange eligibility/condition rules, how the 20%-40% deduction is selected, exclusions, refund/exchange method and any delivery/return-cost rule.
   - Still required for public Warranty/Support: covered products/services, duration/source of warranty beyond the stated touch-screen/camera exclusion, claim requirements, other exclusions and support-channel commitments.
   - Delivery/order commitments: scope, timing language, COD/payment handling commitments and any cancellation boundary that should be contractual rather than operational guidance.
   - Digital-services/project commitments that must appear in Terms, including milestone/payment/cancellation/refund treatment where applicable.

3. **Legal publication metadata**
   - Governing jurisdiction / applicable-law wording approved for release.
   - Effective date and final version for each public Privacy / Terms / Warranty / Returns policy and any Software-specific policy revision.
   - Explicit owner/legal approval of the exact final text. D10=A does not count as that approval; it only authorizes private drafts for review.

## Final technical action after inputs

After the exact inputs above are supplied/approved, update only the affected private policy revisions, record truthful approval/review/effective metadata through the existing protected policy workflow, verify release-distribution NOTICE/dependency contents on the exact candidate, and then close G-L. Public publication remains a separate explicit release action; do not infer it from approval.

**Result: no further implementation gap is currently identified inside 26/G-L. The gate is blocked only on the exact human/legal inputs above plus the final exact-candidate notice check.**
## Repeatable current-candidate technical preflight

`python tools/docs/gl_release_notice_check.py` is the deterministic G-L technical preflight. At `cb08698d28aeef9b81222bf91ebede6f6867c5d6` it PASSed: dependency lockfiles have no drift from MT-7.2 audit anchor `bf5adbc4fd5e6afaeccf50b6cbbdd8b843d2412c`; root LICENSE is absent pending exact holder; Composer root application license field remains absent; NOTICE contains current D09 proprietary/third-party posture; and current backend/Website TypeScript/JavaScript source contains no identified non-essential analytics/tracker marker. This preflight must be rerun on the exact final candidate after legal inputs are applied.
