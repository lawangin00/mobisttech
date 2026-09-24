# MT-7.5 W04 new digital project order payment terms (24-Sep-2026 PKT)

- Found a concrete gap: commerce checkout persisted the approved nonsecret payment presentation on new orders, while approved project milestone payment created a new digital order without the order-bound terms.
- Added a transactional snapshot immediately after project order creation, using the same published presentation and fixed gateway. No COD limits are attached to an external project payment; existing orders and provider adapters remain untouched.
- Extended the existing project milestone test with synthetic JazzCash terms v1, owner-only detail, published v2, immutable old snapshot and the existing paid/ownership checks. Focused MySQL test 1/1 PASS (14 assertions); joined payment/API/reset/Admin presentation suite 71/71 PASS (1,304 assertions); scoped Pint, syntax and diff checks PASS.
- Synthetic provider fixture only; real provider H-02 HOLD. W04 / MT-7.5 IN PROGRESS 15/27 DONE / 12 OPEN. Hosted acceptance pending.
