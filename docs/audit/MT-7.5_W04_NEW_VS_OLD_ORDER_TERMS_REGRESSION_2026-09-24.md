# MT-7.5 W04 old-vs-new order terms regression (24-Sep-2026 PKT)

- Extended isolated MySQL checkout test to create one COD order under presentation v1, publish v2 with different label/instructions/COD lower bound, and create a second order after publication. Assert new order owns v2 saved terms, while prior order retains v1 and its authorized old-amount collection still succeeds. No provider simulation or real money involved.
- Focused 1/1 PASS (15 assertions); joined OrderPaymentTransactions/ApiContract 62/62 PASS (1,193 assertions); scoped Pint and git diff check PASS. Separate full hosted build not requested for test-only change; last full hosted source run 35947528646 was SUCCESS for preceding source 5a89a0c, not this change.
- W04 / MT-7.5 remain IN PROGRESS 15/27 DONE / 12 OPEN; external provider H-02 HOLD.
