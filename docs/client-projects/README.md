# MT-3.6 Client Projects, Proposals and Milestones

MT-3.6 adds the backend authority that evolves a Digital Service enquiry into an explicitly owned client project. It does not expose the later REST/Admin/Website interface work.

## Implemented boundaries

- A `client_project` is created from one existing service request and may link only to an explicitly selected Customer account. Contact details never infer portal ownership.
- Project lifecycle is versioned and audited across Request, Discussion, Proposal, Approved, In Progress, Review, Delivered, Completed and Closed states.
- Proposal revisions retain immutable scope, deliverables, exact PKR amount, validity and deposit/milestone/final schedule snapshots.
- Approval reuses canonical `project_quotes`, `project_milestone_identities`, `MoneySnapshot`, `FinancialReferences` and `OrderTransactions`; no second payment engine exists.
- A paid or active-payment proposal cannot be silently superseded, repriced or reopened. Project completion requires every approved milestone to be paid.
- Website mode changes do not remove authorized historical project, proposal, milestone-payment or delivery access.

## Private client files

Reference and delivery objects use the private `client-files/<uuid>.bin` namespace. The service derives MIME type and SHA-256 from bytes, limits each file to 10 MiB, validates image bytes, stores immutable metadata and retention, and never returns a storage object key to the client portal.

Customer upload/download requires the explicit project account owner. Admin delivery upload requires `website.client-files.manage`. Downloads verify the stored digest and append a project history event.

## Reporting and exclusions

Conversion reporting is permission-scoped and aggregate-only: lead, project, approved-proposal, paid-milestone and completed-project counts plus service/source groupings. It does not return client names, email addresses, mobile numbers or file contents.

No authentic payment provider, private source-data migration, external message, public endpoint, deployment or production mutation is part of this checkpoint.