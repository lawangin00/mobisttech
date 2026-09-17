# MT-2.13 Trade-in and Buyback Services

MT-2.13 implements the backend-owned individual-seller trade-in/buyback workflow. It reuses canonical inventory receipt, active IMEI, permission, audit and immutable monetary-adjustment authorities instead of creating parallel stock or money engines.

## Workflow

1. `create` records seller/source identity, device serial/IMEIs, condition, diagnostics, exact PKR valuation and settlement mode while reserving active trade-in identifiers.
2. `approve` revalidates outlet/product/permission state and, for sale credit, locks the target invoice and proves the credit cannot exceed its payable amount.
3. `receive` atomically creates the stock acquisition, physical unit/IMEI claims, typed `acquisition_source_references` link and optional immutable `trade_in_credit` tender snapshot.
4. `cancel` is allowed before receipt and releases reserved identifiers without inventory or financial effects.

Seller CNIC/phone remain in the private trade-in record. The acquisition snapshot stores masked source contact evidence; approval snapshots retain a CNIC digest rather than exposing the source value. Active identifier uniqueness is enforced both while a trade-in is pending/approved and again by the canonical active-IMEI registry during stock intake.

Purchase treatment records the exact acquisition value without inventing a payment processor. Sale-credit treatment uses the existing `monetary_adjustments` contract as tender, not discount, and its source reference is globally one-use. Receipt, credit and acquisition linkage are one transaction, so a duplicate IMEI or any later failure rolls back every effect.

HTTP publication remains MT-3.4 and operator UI remains MT-4.6. No provider activation, external message, production mutation or protected-source write is part of this point.
