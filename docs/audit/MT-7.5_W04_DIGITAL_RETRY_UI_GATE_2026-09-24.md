# MT-7.5 W04 digital milestone retry UI gate (24-Sep-2026)

- Found a customer-order UI mismatch: shared order detail offered commerce-only `orders/{id}/payments/retry` for failed digital milestone orders when a checkout channel was marked available, although the Laravel retry controller and service permit commerce only. Existing digital pending-order continuation remains available and unchanged.
- Restricted retry buttons to noncancelled `commerce` orders; retained existing failed-payment and channel-availability guards. No payment or provider/merchant authority changed; genuine external-provider H-02 remains HOLD.
- Added a browser-only failed-digital-order fixture with a synthetically available gateway to assert no misleading retry or pending-payment continuation; no external provider activation or payment call.
- Local isolated `mobisttech_test` Edge focused 1/1 PASS; joined client-project browser suite 3/3 PASS with `CI_DISPOSABLE_RESIDUAL_TABLES=none`; Website TypeScript typecheck PASS. Full production and authentic-provider acceptance not claimed.
- LOCAL mode routine change; no CI request, workflow_dispatch or hosted verification authorized or performed. W04/MT-7.5 remain IN PROGRESS 15/27 DONE, 12 OPEN.
