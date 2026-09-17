# MT-3.1 Reports, Documents and Communication Services

MT-3.1 establishes one Laravel-owned canonical Invoice/Warranty document boundary over persisted transaction-time snapshots. Preview, Print, explicit Save PDF, Gmail attachment delivery and assisted WhatsApp all derive from the same canonical document values and hash.

Invoice finalization never downloads or sends a document. Historical documents regenerate from persisted invoice, sale, business, warranty and claim snapshots; later catalogue/customer changes do not rewrite historical evidence. A nullable invoice-time customer email snapshot was added without invalidating older invoices.

Six separately versioned Email/WhatsApp templates are initialized and managed through `config.documents.manage`. Placeholders are allowlisted, subject/header injection is rejected and each change appends a new immutable template revision.

Document sending uses the separate `shop.documents.send` permission. Gmail reuses the approved OAuth `gmail.send` integration and approved business sender, attaches the canonical PDF, records provider success only after the API succeeds and records bounded truthful failures without rolling back a finalized sale or claim.

Delivery attempts are auditable and idempotent. Same-key retries return the same attempt; an intentional resend requires a distinct request identity and is recorded explicitly. WhatsApp remains assisted: states are `prepared` and `opened`, never falsely `sent` or `delivered`, and the operator is instructed to attach the generated PDF when required.

Operational reporting counts each invoice once while exposing tender method/destination mix, Website payment channels, provider fees, settlements and variance as separate measures. Procurement, stocktake/transfer, cash/expense, trade-in, promotion, loyalty and optional repair activity are outlet/date scoped; private customer/seller/payment secrets are not exported.

Retail-label projections are authorization/outlet scoped and expose product/unit identifiers plus IMEI values where applicable. Scanner and UI/physical print workflows remain owned by their later interface checkpoints.

Verification covers exact schema rollback/reapply, canonical historical parity, explicit resend/retry behavior, safe fake Gmail PDF attachment delivery, truthful WhatsApp states, failure/authorization cases, split-tender reporting separation, retail labels, affected regressions, full backend regression and backend/Website production build gates.
