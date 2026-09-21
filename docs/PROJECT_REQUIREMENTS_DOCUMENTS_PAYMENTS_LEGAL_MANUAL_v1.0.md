NEW APPROVED CONSOLIDATED REQUIREMENT —
CUSTOMER DOCUMENT DELIVERY, POS PAYMENT RECONCILIATION,
LEGAL/POLICY DOCUMENTATION, LICENSING REVIEW
AND COMPLETE PRODUCT USER MANUAL

This instruction is being provided directly inside the active
Mobisttech project execution chat.

======================================================================
EXECUTION AUTHORIZATION
======================================================================

This instruction authorizes DOCUMENTATION / STRUCTURAL RECONCILIATION ONLY.

It does NOT authorize runtime application implementation in this run.

Do NOT start:

MT-2.10 - Stocktake and cycle-count services

Do NOT reinitialize the project.

Do NOT reopen or rewrite completed checkpoints.

Do NOT modify the original protected POS or Website repositories.

Do NOT perform real Gmail sending, provider activation, production changes,
real payment settlement, source-data migration, or destructive actions.

Before making any structural change:

1. Fresh-read the current project command/control registry and resolve the
   exact Project ID.

2. Read the complete current:
   - `docs/PROJECT_IMPLEMENTATION_STATUS.md`
   - `docs/PROJECT_GOAL.md`
   - `docs/PROJECT_PREFERENCES.md`
   - `docs/PROJECT_SOURCE_OF_TRUTH.md`
   - `docs/PROJECT_IMPLEMENTATION_ROADMAP.md`

3. Read the approved existing binding requirements relevant to this change:
   - `docs/PROJECT_REQUIREMENTS_ADDENDUM_v1.1.md`
   - `docs/PROJECT_REQUIREMENTS_UNIFIED_ADMIN_GOOGLE_v1.0.md`
   - `docs/PROJECT_REQUIREMENTS_TEAM_MEMBERS_SESSION_POLICY_v1.0.md`

4. Inspect the actual current target implementation relevant to:
   - sales/invoices;
   - warranty/claims;
   - Website payments;
   - Gmail OAuth / Gmail API;
   - canonical business profile;
   - cash/payment primitives;
   - Team Member permissions;
   - audit;
   - document/report foundations.

5. Read-only inspect protected legacy source implementations where useful
   for migration/parity evidence, including:
   - Invoice Preview / PDF / Print;
   - Warranty Claim Receipt;
   - WhatsApp behavior;
   - Website fixed payment methods;
   - legal/policy managed-page infrastructure;
   - licensing/NOTICE evidence.

6. Verify current Git HEAD/history, local working tree and upstream equality.

7. Verify the exact live ledger position before reconciliation.

8. Preserve every already-completed point and historical evidence exactly.

9. Do not alter the project/global command registry merely because this
   business requirement has changed.

======================================================================
CRASH-SAFE REQUIREMENT PRESERVATION
======================================================================

After completing the minimum fresh authority, project-identity and Git-state
verification above, and BEFORE modifying the Source of Truth, roadmap,
roadmap DOCX, implementation ledger or runtime application files, persist
this ENTIRE approved consolidated requirement verbatim as the canonical
Git-tracked requirement file:

`docs/PROJECT_REQUIREMENTS_DOCUMENTS_PAYMENTS_LEGAL_MANUAL_v1.0.md`

The complete requirement text must be preserved, not summarized or reduced.

Verify immediately that:

- the canonical requirement file exists;
- the complete approved requirement was written successfully;
- the beginning and end of the file are present;
- no section was truncated;
- the file can be re-read successfully;
- its hash is recorded for later authority/verification use.

Only after that preservation check passes may structural reconciliation
continue.

This requirement-preservation step is specifically crash/session-limit safe.

If execution is interrupted by:

- Work/session usage limit;
- weekly usage limit;
- application/tool interruption;
- context/session loss;
- machine restart;
- switching from Work to normal Chat;
- any other non-business failure;

a later authorized project surface must recover from repository evidence
instead of asking the user to paste the requirement again, provided this
canonical requirement file was successfully persisted.

Recovery after interruption must:

1. fresh-read the current project registry and Project ID;
2. read the canonical requirement file above completely;
3. read the active Source of Truth, roadmap and implementation ledger;
4. inspect current Git status/diff/history;
5. identify which reconciliation actions actually completed;
6. resume from the first verified unfinished action;
7. not repeat already completed reconciliation work unnecessarily;
8. not discard valid dirty in-progress work merely because the previous
   surface ended;
9. not start MT-2.10;
10. not interpret interruption as authorization to advance the roadmap.

Do NOT mark the requirement itself implemented merely because its canonical
file has been persisted.

Persisting the approved requirement is an authority/recovery checkpoint,
not runtime feature completion.

======================================================================
A. AUTHORITY AND PRESERVATION MODEL
======================================================================

The original approved Goal and Preferences are protected source inputs.

Do NOT directly rewrite:

- `docs/PROJECT_GOAL.md`
- `docs/PROJECT_PREFERENCES.md`

Preserve them byte-for-byte.

Preserve this approved consolidated requirement in:

`docs/PROJECT_REQUIREMENTS_DOCUMENTS_PAYMENTS_LEGAL_MANUAL_v1.0.md`

This requirement becomes binding alongside:

- the original Goal;
- the approved Preferences;
- requirements addendum v1.1;
- Unified Admin / Google requirement;
- Team Member / delegated access / session-security requirement.

Create an appropriate reconciliation area, preferably:

`docs/consolidated-requirements/RECONCILIATION.md`

and suitable machine/human verification evidence.

Update:

- Source of Truth;
- structural roadmap;
- same-basename roadmap DOCX;
- implementation-status ledger;

to register and trace this requirement.

======================================================================
PART I — CUSTOMER DOCUMENT DELIVERY
======================================================================

B. FINAL INVOICE DOCUMENT UX
======================================================================

Invoice finalization must NOT automatically download a PDF merely because
the sale/invoice was successfully finalized.

Target POS invoice workflow:

Prepare Sale
→ Preview Invoice
→ choose applicable output, including Thermal 80mm / A4
→ Finalize Invoice
→ persist the authoritative transaction
→ present Document Actions

After successful finalization, present:

- Send via WhatsApp
- Send via Email
- Print
- Save PDF
- Done / New Invoice

Use consistent wording:

`Send via WhatsApp`
`Send via Email`

Do not use one channel as "via" and another as "by".

Invoice finalization and file download are separate actions.

A PDF must only be downloaded when:

- Save PDF is explicitly selected; or
- the assisted WhatsApp workflow requires a local file for manual attachment.

Historical Invoice View/Details must expose the same applicable document
actions without recreating the invoice.

======================================================================
C. WARRANTY CLAIM RECEIPT UX
======================================================================

Warranty Claim Receipt must use the same unified document-action model.

After a Warranty Claim / Job is successfully saved, provide as applicable:

- Preview
- Send via WhatsApp
- Send via Email
- Print
- Save PDF
- Open Warranty Job / Done

Historical Warranty Claim/Job records must expose the same applicable
document actions.

Invoice and Warranty Claim Receipt must reuse a common maintainable
document-delivery architecture rather than unrelated duplicate logic.

======================================================================
D. CANONICAL DOCUMENT GENERATION
======================================================================

Create one maintainable authoritative document-generation boundary for
Invoice and Warranty documents.

The same persisted business snapshots must drive:

- Preview;
- Thermal output where applicable;
- A4;
- Print;
- Save PDF;
- Email attachment;
- WhatsApp-assisted PDF sharing.

Email, print and downloaded versions must not silently contain different
prices, totals, customer values, warranty clauses, salesperson evidence,
business identity or historical data.

Historical documents must use persisted transaction-time snapshots where
historical accuracy requires them.

Prefer backend-owned canonical PDF/document generation where required for
reliable Email delivery and historical parity.

A browser-only DOM-to-PDF renderer must not become the sole authoritative
renderer if Laravel must independently send the same PDF through Gmail.

======================================================================
E. OPTIONAL POS CUSTOMER EMAIL
======================================================================

POS sale/customer capture must support:

- Customer Name
- CNIC where applicable
- Mobile
- Email (optional)

Email must NOT be mandatory for walk-in retail sales.

Validate email syntax server-side.

Where an owned/verified Customer or Website Order already has an
authoritative email, it may be safely prefilled according to existing
ownership rules.

Persist the relevant sale/invoice-time email snapshot where appropriate.

Later Customer account changes must not silently rewrite historical Invoice
contact evidence.

Historical invoices without an email remain valid.

Do not reopen completed MT-2.5 merely to retrofit its historical checkpoint.
Introduce only the minimum backward-compatible nullable extension at the
appropriate future implementation point.

======================================================================
F. SEND VIA EMAIL — REQUIRED MECHANISM
======================================================================

`Send via Email` must use the already-approved backend Gmail architecture:

Google OAuth 2.0
+
Gmail API
+
minimum `gmail.send` scope

Do NOT make "open Gmail in the browser" the primary supported workflow.

Required flow:

Authorized Admin / Team Member
→ opens Invoice or Warranty document
→ selects Send via Email
→ application opens controlled Email modal/dialog
→ recipient, subject and message are safely prefilled
→ permitted fields may be edited
→ user selects Send Email
→ Laravel validates authorization and recipient
→ canonical PDF is generated/resolved
→ Gmail API sends the message
→ result is recorded truthfully

Approved sender remains:

mobiST Technologies <mobisttech@gmail.com>

Do not silently switch to arbitrary SMTP or another sender account.

The operator must not normally need to:

- open Gmail manually;
- download the PDF first;
- find the file in Downloads;
- manually create the email;
- manually attach the invoice;
- copy/paste the subject/body.

If Gmail is genuinely not connected, fail safely and clearly.

Never report Sent when Gmail actually failed.

Integration-management permission remains separate from ordinary
customer-document sending.

Permission to send an Invoice must not imply permission to:

- reconnect Gmail;
- disconnect Gmail;
- inspect tokens;
- change OAuth credentials;
- manage unrelated integration secrets.

======================================================================
G. EMAIL COMPOSER
======================================================================

Email dialog must include at minimum:

To
Subject
Message
Attachment

Recommended Invoice subject template:

Sales Invoice {{invoice_number}} - {{business_name}}

Recommended Invoice body concept:

Dear {{customer_name}},

Thank you for your purchase from {{business_name}}.

Please find your Sales Invoice {{invoice_number}} attached.

Total Amount: PKR {{total_amount}}

Please keep this invoice for your records and warranty claims.

Regards,
{{business_name}}

Recommended Warranty Claim subject:

Warranty Claim Receipt {{claim_number}} - {{business_name}}

The operator may edit recipient/subject/body within safe validation rules.

A send-time recipient override must not silently mutate the historical
customer snapshot.

======================================================================
H. COMMUNICATION TEMPLATES
======================================================================

Support separately managed templates for at least:

1. Invoice WhatsApp Message
2. Warranty Claim WhatsApp Message
3. Invoice Email Subject
4. Invoice Email Body
5. Warranty Claim Email Subject
6. Warranty Claim Email Body

Templates must use authorized Admin configuration and must not be scattered
as unrelated hard-coded strings.

Support appropriate safe placeholders such as:

{{customer_name}}
{{invoice_number}}
{{claim_number}}
{{total_amount}}
{{business_name}}
{{outlet_name}}
{{document_date}}

Unknown/malformed placeholders must fail safely.

HTML/email template content must not introduce injection or secret leakage.

Email MIME delivery should include valid HTML/text representation where
appropriate.

======================================================================
I. WHATSAPP DELIVERY
======================================================================

Do not falsely claim that a normal browser/`whatsapp://` flow has
automatically attached a generated PDF.

Without separately approved and configured WhatsApp Business Platform/API,
use assisted delivery:

Send via WhatsApp
→ normalize customer number
→ prepare approved message
→ generate/download PDF only when required
→ open correct WhatsApp chat
→ prefill message
→ clearly tell operator to attach the generated PDF before sending

Do NOT download every Invoice merely because it was finalized.

Do NOT mark a WhatsApp document as Sent/Delivered merely because the chat
was opened.

Use truthful terminology such as:

Prepared
Opened in WhatsApp

where appropriate.

Architecture should permit a future separately approved WhatsApp Business
API integration without redesigning the canonical document model.

======================================================================
J. PRINT AND SAVE PDF
======================================================================

Print and Save PDF are separate actions.

`Print` invokes the supported printing flow.

The user may select a physical printer or OS PDF printer where available.

`Save PDF` explicitly saves/downloads the canonical generated document.

Do not force file download before Print.

Do not force file download before Email.

======================================================================
K. DOCUMENT DELIVERY SECURITY AND AUDIT
======================================================================

All document actions must be server-authorized.

UI visibility alone is not authorization.

Use/reuse conventional permissions consistent with Team Member Roles.

Sending permission must not automatically confer:

- configuration management;
- integration management;
- payment credential management;
- Team Member administration;
- unrelated customer access.

Email audit should preserve appropriate evidence:

- document type;
- stable document ID;
- recipient;
- acting Team Member;
- outlet;
- timestamp;
- template/revision;
- document/content hash or immutable version reference;
- send state;
- safe provider reference where available;
- bounded/redacted failure detail.

Double-click/browser retry/queue retry must not create uncontrolled
duplicate sends.

Permit an explicit intentional Resend.

Never expose Gmail OAuth tokens or provider secrets in frontend responses,
audit UI or normal logs.

======================================================================
L. DOCUMENT DELIVERY FAILURE RULES
======================================================================

Test at least:

- missing customer email;
- malformed email;
- Gmail Not Connected;
- expired/revoked authorization;
- PDF generation failure;
- Gmail API failure/timeout;
- unauthorized Team Member;
- wrong-outlet access;
- tampered document identifier;
- stale/deleted reference;
- duplicate click/retry;
- malformed template placeholder;
- email header injection attempt.

An already-finalized Invoice or Warranty intake must remain valid when a
later optional delivery attempt fails.

Delivery failure must not roll back a completed sale/claim transaction.

Do NOT automatically send every Invoice or Warranty receipt merely because
it was finalized.

Automatic delivery requires separate explicit approval/configuration.

======================================================================
PART II — POS PAYMENT CHANNELS, SPLIT TENDER
AND DAY-CLOSING RECONCILIATION
======================================================================

M. WEBSITE PAYMENT MODEL REMAINS FIXED
======================================================================

Do NOT convert the Website into a dynamic multi-account checkout.

The Website customer-facing payment methods remain exactly the approved
fixed four channels:

1. Cash on Delivery
2. JazzCash
3. Easypaisa
4. Credit / Debit Card

Do NOT add Website Bank Transfer through this requirement.

Do NOT add multiple Website JazzCash accounts.

Do NOT add multiple Website Easypaisa accounts.

Do NOT expose multiple settlement bank accounts to Website customers.

Do NOT add Website split tender.

Website provider behavior remains:

JazzCash
→ one configured JazzCash merchant/API integration

Easypaisa
→ one configured Easypaisa merchant/API integration

Credit / Debit Card
→ one approved hosted/tokenized card processor settlement path

Cash on Delivery
→ COD status/collection flow

Website customer payment processing remains governed by MT-2.7 / MT-5.3
and authentic provider HOLD requirements.

This requirement must not weaken or replace the existing Website provider
security model.

======================================================================
N. POS PAYMENT MODEL
======================================================================

POS walk-in retail sales require a distinct operational payment-recording
model.

Do not implement only one simplistic field such as:

payment_method = cash

Create an explicit POS tender/payment allocation model.

Top-level POS Payment Methods:

- Cash
- Card
- Mobile Wallet
- Bank Transfer

Each non-cash method may have one or more configurable internal Payment
Destinations.

Examples:

Cash
→ Quaidabad Outlet Cash Drawer

Mobile Wallet
→ Easypaisa Shop Wallet
→ JazzCash Business Wallet
→ future additional approved wallet destination

Card
→ HBL POS Terminal 01
→ Meezan POS Terminal 01
→ other approved merchant terminal

Bank Transfer
→ Meezan Current Account ****1234
→ UBL Current Account ****5678

`Payment Method` describes HOW the customer paid.

`Payment Destination` identifies WHERE the business money was received or
is expected to settle.

Keep these concepts separate.

======================================================================
O. POS PAYMENT DESTINATION ADMINISTRATION
======================================================================

Provide protected Admin configuration for POS Payment Destinations.

Suggested Admin concept:

Settings
→ Payments
→ Payment Channels / Payment Destinations

Each destination should support as applicable:

- display name;
- method type;
- provider/bank label;
- masked account/wallet identifier;
- outlet assignment;
- active/inactive state;
- effective/configuration metadata;
- internal notes where appropriate.

Do not store unnecessary secrets with ordinary Payment Destination metadata.

Provider credentials remain protected in their proper secret/integration
boundary.

Mask financial identifiers appropriately in normal UI.

A role that can use a payment destination during sale does not automatically
gain permission to create/edit/delete payment destinations.

======================================================================
P. POS SPLIT TENDER
======================================================================

A POS Invoice may be paid using one or multiple tender allocations.

Example:

Invoice Total: PKR 100,000

Cash
→ Cash Drawer
→ PKR 30,000

Mobile Wallet
→ Easypaisa Wallet
→ PKR 20,000

Card
→ HBL POS Terminal 01
→ PKR 50,000

Total tender allocation must equal the authoritative final Invoice payable
amount before a fully-paid normal retail sale is finalized, unless a future
separately approved credit/receivable model explicitly allows otherwise.

Support:

+ Add Payment

Each allocation may include:

- Payment Method
- Payment Destination
- Amount
- safe optional transaction/reference number
- provider/terminal reconciliation reference where applicable

Do not allow:

- negative tender amounts;
- over-allocation;
- silently missing remaining balance;
- destination from an unauthorized outlet;
- inactive destination use;
- client-supplied sale total overrides.

All exact money calculations remain server authoritative.

======================================================================
Q. CASH RECEIVED AND CHANGE
======================================================================

For Cash allocations, optionally support:

Amount Due to Cash: 4,500
Customer Tendered: 5,000
Change Returned: 500

Expected cash drawer movement is 4,500, not 5,000.

Do not count returned change as sales.

======================================================================
R. SALE, PAYMENT AND SETTLEMENT ARE DISTINCT
======================================================================

Preserve this accounting/operational distinction:

SALE
!=
PAYMENT / TENDER
!=
SETTLEMENT

Example:

Sale = 100,000

Customer tenders:
Cash       25,000
Easypaisa  25,000
Card       50,000

Later settlement:
Cash Drawer expected               25,000
Easypaisa gross receipt            25,000
Card gross customer payment        50,000
Card processor fee                  1,000
Net card bank settlement           49,000

Do not reduce the original Sale merely because a provider deducts a fee.

Provider/merchant charges are not customer discounts.

Represent settlement fees/adjustments separately from the original customer
payment.

Do not introduce a full general ledger/ERP merely to support this
reconciliation.

======================================================================
S. CARD SECURITY
======================================================================

POS card transactions refer to external physical payment terminals.

Do NOT store:

- PAN;
- CVV;
- PIN;
- magnetic-stripe data;
- unnecessary raw card details.

Only safe operational references may be stored where useful, for example:

- terminal identity;
- amount;
- approval/reference code;
- RRN or safe equivalent.

Keep such references optional/configurable and bounded.

======================================================================
T. REFUNDS / RETURNS AND TENDER TRACEABILITY
======================================================================

Returns/refunds must retain original payment-allocation evidence.

Example original Invoice:

Cash 30,000
Card 70,000

If PKR 20,000 refund becomes due, the system must know the original tender
history.

Refund destination/method must be recorded truthfully.

If authorized operations refund through a method different from the
original payment method, require:

- explicit override;
- reason;
- acting Team Member;
- approval where policy requires;
- audit evidence.

Do not falsely claim external/provider automatic refunds.

Website provider refunds remain under their existing provider/HOLD
contracts.

======================================================================
U. DAY CLOSING AND RECONCILIATION
======================================================================

Enhance the future cash/day-closing model so Cash and Non-Cash are not
conflated.

Cash reconciliation:

Opening Cash
+ Cash Sales
+ approved Cash In
- Cash Refunds
- Expenses/Payouts
= Expected Cash

Actual Cash Count
= Variance

Non-cash receipts remain separate, for example:

HBL POS Terminal 01
Meezan POS Terminal
Easypaisa Wallet
JazzCash Wallet
Meezan Bank Transfer
UBL Bank Transfer

Day Closing should show Expected receipt totals by internal Payment
Destination.

The business must not need to infer today's sales by looking at the total
balance of a bank/wallet account.

The reconciliation system should answer:

"How much should have been received through this destination for this
outlet/date/session?"

Manager/authorized user should be able to reconcile expected vs confirmed
settlement where appropriate, with safe:

- status;
- notes;
- adjustment/fee information;
- variance;
- actor;
- timestamp;
- audit.

Do not automatically treat old account balance as sales.

======================================================================
V. DASHBOARD PAYMENT MIX — AVOID CLUTTER
======================================================================

Do NOT create one home-dashboard card for every bank account, wallet,
terminal or destination.

Keep primary dashboard summary clean.

Provide one compact high-level widget/card such as:

Payment Mix — Today

Cash
Card
Mobile Wallet
Bank Transfer

with total/percentage representation.

A compact segmented bar, donut or similarly readable visualization may be
used if it fits the final design.

Provide drill-down such as:

View Breakdown

for actual destinations:

Card
  HBL POS Terminal 01
  Meezan POS Terminal

Mobile Wallet
  Easypaisa Wallet
  JazzCash Wallet

Bank Transfer
  Meezan Current ****1234
  UBL Current ****5678

Do not clutter the main dashboard with each individual destination.

Where useful, allow:

All
POS
Website

payment-source filtering.

Website reporting should classify its fixed channels:

- COD
- JazzCash
- Easypaisa
- Card

without changing the Website checkout model.

======================================================================
W. PAYMENT REPORTING
======================================================================

Reports should support at minimum:

- gross sales;
- net sales;
- discounts;
- returns/refunds;
- gross profit where already supported;
- tender/payment totals;
- method breakdown;
- destination breakdown;
- Website vs POS;
- outlet;
- date/session;
- expected settlement;
- provider/merchant fees where known;
- net settlement;
- unreconciled/reconciled status;
- cash variance.

Do not double-count split-tender invoices.

One PKR 100,000 Invoice with:

Cash 30,000
Card 70,000

is still:

Total Sale = 100,000

not 200,000.

======================================================================
PART III — LEGAL / POLICY DOCUMENTATION
AND PROJECT LICENSING
======================================================================

X. LEGAL/POLICY INFRASTRUCTURE
======================================================================

The project already includes/targets managed legal content infrastructure,
but infrastructure alone does NOT satisfy final project acceptance.

The completed customer-facing Website must contain actual reviewed,
business-specific legal/policy content appropriate to the final implemented
system.

Do not publish generic placeholder Lorem Ipsum or blindly copied legal
boilerplate.

Do not claim legal review that did not occur.

======================================================================
Y. REQUIRED CUSTOMER-FACING POLICY DRAFTS
======================================================================

Prepare accurate final drafts, as applicable, for at least:

1. Privacy Policy
2. Terms & Conditions / Terms of Use
3. Return & Refund Policy
4. Shipping / Delivery Policy
5. Warranty Policy

Also evaluate whether the final product requires:

6. Cookie Policy
7. Digital Services / Project Terms
8. Payment-related customer terms/disclosures
9. other genuinely necessary legal/policy pages based on the implemented
   business model.

Cookie Policy must not be added merely for appearance.

If the final Website uses only strictly necessary cookies and has no
non-essential analytics/marketing tracking requiring additional disclosure
or consent, document that determination instead of inventing unnecessary
tracking.

If analytics/marketing/non-essential cookies are introduced, implement
appropriate disclosure/consent behavior and policy coverage.

======================================================================
Z. PRIVACY POLICY MUST MATCH REAL DATA FLOWS
======================================================================

Privacy Policy content must be based on the final actual product.

Review all personal-data flows, including where applicable:

- Customer account registration;
- login/security/session data;
- names;
- phone/mobile;
- email;
- addresses;
- CNIC where legitimately collected for POS/business workflows;
- Orders;
- Invoices;
- Warranty Claims;
- Website reviews;
- Wishlists;
- notification preferences;
- service enquiries/leads;
- project/proposal/client portal;
- private client files;
- payment references;
- delivery information;
- support/contact communication;
- Gmail transactional messages;
- backups;
- audit/security logs;
- analytics/conversion events;
- uploaded documents/images;
- retention/reset behavior.

Do not state that data is collected, sold, shared, retained, encrypted,
deleted or transferred in a certain way unless the actual product supports
that factual statement.

Do not falsely claim zero third-party processing if legitimate providers
such as payment gateways, Google/Gmail, hosting/storage or delivery partners
are used.

======================================================================
AA. TERMS & CONDITIONS
======================================================================

Terms must reflect the actual final mobiST business model.

Cover appropriate subjects such as:

- customer use of Website;
- account responsibilities;
- product information;
- pricing;
- stock/availability;
- order acceptance;
- payment;
- COD;
- electronic payment providers;
- cancellations;
- delivery;
- returns/refunds;
- warranty;
- prohibited misuse;
- Website content/IP;
- limitation/disclaimer language appropriate to the business;
- contact/dispute process;
- policy updates/effective date.

Do not promise guarantees the application/business has not approved.

======================================================================
AB. RETURN & REFUND POLICY
======================================================================

The public Return & Refund Policy must reconcile with the actual
implemented POS/Website return/refund rules.

It must not contradict:

- Invoice history;
- stock restoration/quarantine rules;
- payment/provider refund limitations;
- Warranty workflow;
- product condition rules;
- immutable transaction history.

Where business decisions such as return window, eligible conditions,
restocking requirements or exclusions require owner input, present them as
explicit approval fields during final policy drafting rather than silently
inventing commercial terms.

======================================================================
AC. SHIPPING / DELIVERY POLICY
======================================================================

Shipping / Delivery Policy must reflect actual final Website fulfillment.

Where applicable cover:

- service area;
- delivery method;
- COD;
- estimated delivery handling;
- customer contact/verification;
- failed delivery;
- order status;
- shipping charges if any;
- address responsibility;
- delays/out-of-stock handling.

Do not invent courier/provider commitments that have not been approved.

======================================================================
AD. WARRANTY POLICY
======================================================================

Warranty Policy must reconcile with actual:

- sale-time warranty snapshot;
- warranty duration;
- warranty clause version;
- eligibility;
- expiry;
- claim lifecycle;
- exclusions;
- customer receipt/invoice requirements;
- device identifiers/IMEI where applicable.

Do not allow managed policy text to rewrite historical Invoice warranty
snapshots.

Current public policy revisions affect future/current public guidance, not
historical transaction evidence.

======================================================================
AE. DIGITAL SERVICES / SERVICE PRICING TERMS
======================================================================

Because mobiST Technologies also provides Digital Services, review whether
a dedicated Digital Services / Project Terms document is appropriate.

Where required, cover:

- enquiry/quotation distinction;
- fixed price / starting-from / package pricing;
- custom quotation authority;
- scope;
- add-ons;
- proposal validity;
- deposits;
- milestones;
- final payment;
- client approvals;
- revisions/change requests;
- deliverables;
- project status;
- client-provided materials;
- private files;
- cancellation/termination;
- delays/dependencies;
- ownership/licensing of final deliverables where approved;
- third-party costs;
- confidentiality/privacy where applicable.

Do not present a "Starting from" price as a guaranteed final project price.

Do not let Website content override an approved quote/proposal/milestone
snapshot.

Actual Digital Service public pricing must use the authoritative implemented
pricing model.

======================================================================
AF. PAYMENT POLICY/DISCLOSURE CONSISTENCY
======================================================================

Customer legal/policy content must describe Website payment options
consistently with the approved fixed Website model:

- Cash on Delivery
- JazzCash
- Easypaisa
- Credit / Debit Card

Do not publicly advertise Website Bank Transfer unless it is separately
approved later.

Do not claim a card is processed directly by mobiST if the final model uses
a hosted/tokenized third-party processor.

Do not expose internal POS payment destinations to Website customers.

======================================================================
AG. LEGAL CONTENT ADMINISTRATION
======================================================================

Use the existing/target managed legal-content model.

Legal pages must support appropriate:

- Draft
- Preview
- Publish
- Revision
- Rollback
- effective/update date
- safe rich content
- protected route/slug behavior
- footer policy links
- appropriate authorization/audit

A content editor must not be able to replace protected application routes
such as:

- account;
- cart;
- checkout;
- payment;
- order;
- protected customer/admin functions.

======================================================================
AH. LEGAL SIGN-OFF
======================================================================

The system/project may prepare factually grounded policy drafts.

Do NOT label AI-generated or developer-drafted policies as
"lawyer-approved" unless that actually occurred.

Before live production publication:

- business owner must review and approve the commercial/business terms;
- factual technical/data-flow claims must be checked against the final
  product;
- unresolved legal/jurisdiction questions must be explicitly surfaced;
- where professional legal review is required/desirable, keep it as a
  production sign-off requirement rather than inventing approval.

Record policy version/effective date and approval status.

======================================================================
AI. SOFTWARE PROJECT LICENSE / OWNERSHIP REVIEW
======================================================================

Customer Website policies and software repository licensing are separate
concerns.

Review the new monorepo's project-level licensing/ownership status.

Do NOT infer that the complete Mobisttech product is MIT-licensed merely
because:

- Laravel is MIT;
- framework skeleton metadata says MIT;
- individual npm/Composer dependencies use open-source licenses.

If the intended Mobisttech application is private/proprietary business
software, create an appropriate project-level ownership/license notice that
makes that clear.

Do not accidentally publish the full application under an unintended
open-source license.

======================================================================
AJ. THIRD-PARTY LICENSES AND NOTICES
======================================================================

Audit distributed/runtime third-party dependencies and assets for relevant:

- licenses;
- notices;
- attribution;
- redistribution requirements.

Create/update appropriate top-level files where justified, such as:

`NOTICE.md`

and a project-level `LICENSE` / proprietary license notice if approved and
appropriate.

Do not strip valid third-party copyright/license notices.

Do not copy dependency license text into a project-level license in a way
that falsely licenses mobiST-owned application code under that dependency's
license.

FINAL-AUDIT must confirm the licensing/notice position is deliberate rather
than leftover framework boilerplate.

======================================================================
PART IV — COMPLETE PRODUCT USER MANUAL
======================================================================

AK. USER MANUAL IS A REQUIRED FINAL DELIVERABLE
======================================================================

Technical README files, migration evidence, implementation ledgers,
Source-of-Truth documents and deployment runbooks do NOT substitute for a
user manual.

The completed Mobisttech product must include a complete operational
user-facing manual.

Recommended canonical source:

`docs/user-manual/USER_MANUAL.md`

Human-readable mirrors:

`docs/user-manual/USER_MANUAL.docx`
`docs/user-manual/USER_MANUAL.pdf`

Final screenshots:

`docs/user-manual/images/`

The canonical manual uses standard English under the existing repository
documentation-language policy unless localization is separately approved.

======================================================================
AL. USER MANUAL AUDIENCE
======================================================================

Cover applicable final audiences such as:

- Full Access Admin;
- Manager;
- Store Manager;
- Sales Associate;
- Cashier;
- Inventory Manager;
- Service & Warranty;
- Online Store Editor;
- Merchandiser;
- Customer Support;
- Digital Operations;
- applicable Custom Roles;
- Website Customer;
- local Windows operator.

Do not imply that every role has access to every screen.

Explain relevant permissions where useful.

======================================================================
AM. USER MANUAL COVERAGE
======================================================================

The final manual must cover every material supported user workflow.

At minimum review/include applicable sections for:

1. Getting Started
2. Login / Logout
3. Session Security
4. Dashboard / Navigation
5. Team Members
6. Roles / Permissions
7. Outlet Assignments
8. POS Sale
9. Payment Method / Destination
10. Split Tender
11. Card / Wallet / Bank Transfer POS recording
12. Invoice Preview
13. Invoice Finalization
14. Send via WhatsApp
15. Send via Email
16. Print
17. Save PDF
18. Invoice History / Resend
19. Customers
20. Products / Catalogue
21. Units / IMEI
22. Inventory
23. Suppliers
24. Purchase Orders
25. Receiving
26. Stocktake / Cycle Count
27. Inter-outlet Transfer
28. Cash Sessions
29. Day Closing
30. Payment Mix
31. Payment Destination Reconciliation
32. Expenses / Payouts
33. Trade-in / Buyback
34. Promotions / Coupons
35. Loyalty where enabled
36. Warranty Intake
37. Warranty Jobs / Claims
38. Warranty Claim Receipt delivery
39. Paid Repair where enabled
40. Reports / Exports
41. Website Administration
42. CMS / Pages
43. Legal Content
44. Navigation / Menus
45. Homepage / Content
46. Products / Merchandising
47. Media
48. Branding / Presentation
49. Website Modes
50. SEO / Metadata
51. Website Orders
52. Website fixed Payment Methods
53. Digital Services
54. Service Pricing / Packages
55. Leads / Enquiries
56. Projects
57. Proposals
58. Milestones
59. Client Files / Portal
60. Customer Website Registration / Login
61. Cart / Checkout
62. Customer Order History
63. Customer Invoices
64. Reviews
65. Wishlist / Notifications where enabled
66. Business Profile
67. Gmail Integration
68. Google Drive / Backups
69. Restore / Recovery boundaries
70. Data Reset
71. Privacy / legal policy administration
72. mobiST Control
73. Windows Start / Stop / Status
74. Common Errors
75. Troubleshooting
76. Security / Recommended Practices

The final table of contents may be refined to match actual completed
functionality.

No material supported workflow may be silently omitted.

======================================================================
AN. USER MANUAL SECTION STANDARD
======================================================================

Each substantial operational section should include as applicable:

- Purpose
- Who can access it
- Required role/permission
- Navigation path
- Step-by-step procedure
- Field/option explanations
- What Save/Submit does
- Expected success result
- Warnings
- Destructive/irreversible-action warnings
- Related workflows
- Common errors
- Troubleshooting
- final UI screenshots

Do not create a vague product brochure.

Do not document imaginary controls.

Instructions must match the final product.

======================================================================
AO. USER MANUAL SCREENSHOTS
======================================================================

Use screenshots from the FINAL implemented Mobisttech application.

Do not use legacy screenshots as final-product screenshots.

Do not use fabricated/placeholder screenshots as final acceptance.

Use safe demo/test data.

Do not expose:

- passwords;
- OAuth secrets;
- provider credentials;
- real unnecessary customer private data;
- sensitive account numbers.

Screenshots must remain readable in Markdown/DOCX/PDF.

Update affected screenshots whenever final UI changes.

======================================================================
AP. USER MANUAL RELEASE INTEGRITY
======================================================================

Record the product release/version/Git checkpoint documented by the manual.

Write/finalize the manual only after the relevant UI/workflows actually
exist.

Do not mark an assumption-based early manual as final.

Manual acceptance must test instructions against the actual application.

======================================================================
PART V — ROADMAP INTEGRATION
======================================================================

AQ. DO NOT REOPEN COMPLETED POINTS
======================================================================

Do NOT reopen or rewrite historical completed checkpoints including:

- MT-2.5 - Sales, invoices and returns migration
- MT-2.6 - Warranty and claim migration
- MT-2.18 - Unified Admin identity and Google integrations remediation
- MT-2.19 - Team member roles, delegated access and session security remediation
- MT-2.7 - Unified orders, reservations and payments
- MT-2.9 - Supplier and procurement services

Add prospective requirements to future work.

======================================================================
AR. NEW BACKEND PAYMENT POINT
======================================================================

Add one new dedicated roadmap point:

`MT-2.20 - POS payment channels, split tenders and settlement reconciliation`

Recommended placement:

after:

`MT-2.11 - Inter-outlet stock transfer services`

before:

`MT-2.12 - Cash sessions and operational expense services`

Dependencies:

`MT-2.11`

Scope:

Implement backend authority for POS Payment Methods, configurable
outlet-aware Payment Destinations, multiple tender allocations per Invoice,
exact-money split tender, safe payment references, Cash tender/change,
original-payment/refund traceability, settlement/merchant-fee evidence,
reconciliation state and audit. Preserve the Website's fixed four-channel
checkout unchanged. Do not create a general ledger/ERP.

Acceptance:

- exact tender sum matches authoritative Invoice payable amount;
- split tender persists atomically with the sale;
- inactive/wrong-outlet destinations fail;
- concurrent/double-submit/idempotency cases pass;
- cash/change math passes;
- card sensitive-data restrictions pass;
- merchant fee does not rewrite Sale/customer payment;
- refund tender traceability passes;
- destination reconciliation state/audit passes;
- Website checkout still exposes only COD/JazzCash/Easypaisa/Card;
- full/focused backend/schema/regression gates pass.

Then change:

`MT-2.12 - Cash sessions and operational expense services`

dependency to:

`MT-2.20`

and materially expand MT-2.12 acceptance to consume Cash tender allocations
and separate non-cash expected receipts during closing.

======================================================================
AS. EXPAND MT-3.1
======================================================================

Materially expand:

`MT-3.1 - Reports, documents and communication services`

to include:

- canonical Invoice/Warranty document generation;
- Thermal/A4 parity;
- explicit Save PDF;
- Print;
- optional POS customer-email snapshot support;
- Gmail API PDF attachment sending;
- Email subject/body templates;
- WhatsApp templates;
- assisted WhatsApp delivery;
- truthful delivery states;
- document send audit;
- send/retry idempotency;
- payment-method reporting;
- Payment Destination reporting;
- payment-mix reports;
- settlement/reconciliation reports;
- fee/variance reporting.

Normal automated acceptance must use safe fakes/mocks and must not send real
external messages.

======================================================================
AT. EXPAND MT-3.2
======================================================================

Materially expand:

`MT-3.2 - Dynamic CMS, media and presentation services`

so Legal Content explicitly supports actual policy documents and not merely
generic "legal" page infrastructure.

Cover:

- Privacy Policy;
- Terms & Conditions;
- Return & Refund Policy;
- Shipping / Delivery Policy;
- Warranty Policy;
- conditional Cookie Policy;
- Digital Services / Project Terms where appropriate;
- policy type/purpose;
- draft/preview/publish;
- version/effective date;
- revision/rollback;
- safe rich content;
- protected routes/slugs;
- footer policy destinations.

Do not auto-invent final commercial/legal terms without required owner
decisions.

======================================================================
AU. EXPAND MT-4.2
======================================================================

Materially expand:

`MT-4.2 - POS inventory and transaction interfaces`

to include the POS sale-payment UI:

Payment
→ method
→ destination
→ amount
→ optional safe reference
→ Add Payment

Support split tender.

Show:

Invoice Total
Payments
Remaining

Prevent finalization with invalid tender total.

Support cash tendered/change where approved.

Do not expose secret payment credentials.

======================================================================
AV. EXPAND MT-4.3
======================================================================

Materially expand:

`MT-4.3 - POS customer, warranty and reporting interfaces`

to include:

Invoice:
Preview
→ Finalize
→ Send via WhatsApp
→ Send via Email
→ Print
→ Save PDF
→ Done

Warranty:
Preview
→ Send via WhatsApp
→ Send via Email
→ Print
→ Save PDF

Also cover:

- optional customer email;
- historical resend/reprint/redownload;
- no forced download on Finalize;
- document error/retry UX;
- payment-mix/dashboard summary where this interface owns it;
- account-level detail via drill-down rather than dashboard card explosion.

======================================================================
AW. EXPAND MT-4.4
======================================================================

Materially expand:

`MT-4.4 - Website CMS and platform administration interfaces`

to cover protected administration of:

- Invoice WhatsApp Message;
- Warranty WhatsApp Message;
- Invoice Email Subject;
- Invoice Email Body;
- Warranty Email Subject;
- Warranty Email Body;
- POS Payment Destinations;
- legal/policy content;
- policy publish/revision/effective date;
- applicable document/output defaults;
- existing Gmail connection/status management entry point.

Ordinary document senders do not become integration/config administrators.

Payment Destination configuration does not reveal provider secrets.

======================================================================
AX. EXPAND MT-4.6
======================================================================

Materially expand:

`MT-4.6 - Cash, trade-in and repair interfaces`

to include Day Closing and payment reconciliation UI.

Cover:

- Opening Cash;
- Cash Sales;
- Cash Refunds;
- Expenses/Payouts;
- Expected Cash;
- Actual Count;
- Cash Variance;
- Non-Cash expected receipts;
- destination-level breakdown;
- settlement/fee/variance where applicable;
- reconciliation state;
- role/approval/audit.

Keep home dashboard compact.

Use drill-down for destination detail.

======================================================================
AY. WEBSITE MT-5.3 MUST REMAIN FIXED
======================================================================

`MT-5.3 - Checkout and customer payment flows`

must continue to implement only the approved Website channels:

- Cash on Delivery
- JazzCash
- Easypaisa
- Credit / Debit Card

Do not add Website Bank Transfer.

Do not add Website split tender.

Do not make Website customers choose among internal merchant destinations.

Website transactions must nevertheless classify correctly into the shared
reporting/reconciliation model.

======================================================================
AZ. EXPAND MT-5.4 LEGAL PUBLICATION
======================================================================

Materially expand:

`MT-5.4 - Dynamic public content and digital solutions`

to ensure actual published policy pages and footer/legal destinations are
available and responsive.

Verify public content for:

- Privacy Policy;
- Terms;
- Return/Refund;
- Shipping/Delivery;
- Warranty;
- applicable Cookie Policy;
- applicable Digital Services/Project Terms.

Digital Service pricing copy must distinguish actual fixed/package pricing,
starting-from pricing and custom quotations.

Do not allow mutable Website copy to rewrite approved proposal/milestone
financial snapshots.

======================================================================
BA. EXPAND MT-7.2
======================================================================

`MT-7.2 - Security, performance and resilience audit`

must additionally audit:

Document delivery:
- email recipient/header validation;
- Gmail token isolation;
- attachment integrity;
- authorization;
- wrong-outlet access;
- duplicate/retry behavior;
- template injection;
- audit privacy;
- truthful WhatsApp status.

POS payments:
- split tender arithmetic;
- idempotency/concurrency;
- destination authorization;
- sensitive card-data exclusion;
- fee/settlement separation;
- refund traceability;
- reconciliation tampering.

Legal/privacy:
- actual personal-data flows vs Privacy Policy claims;
- consent/notification behavior;
- tracking/cookie reality;
- public/private data boundaries;
- retention/reset statements;
- payment-provider claims;
- policy-route/content safety.

======================================================================
BB. EXPAND MT-7.5
======================================================================

`MT-7.5 - Full functional parity and acceptance`

must include fresh final journeys for:

Document delivery:
- Finalize Invoice with no automatic download;
- Save PDF;
- Print;
- Send via WhatsApp;
- Send via Email using safe transport/fake;
- historical Invoice resend;
- Warranty Receipt equivalents.

POS payments:
- single Cash sale;
- single Card sale;
- Wallet sale;
- Bank Transfer POS recording;
- multi-tender sale;
- cash tender/change;
- destination authorization;
- Day Closing;
- payment breakdown;
- fee/settlement case;
- refund traceability;
- Website fixed four-channel regression.

Legal/policy:
- policy coverage matches final application/business behavior;
- required owner decisions are resolved;
- effective/version dates are present;
- footer links work;
- legal pages are publishable/revisioned;
- Privacy claims match real data flow;
- licensing/NOTICE review is complete.

======================================================================
BC. NEW FINAL USER-MANUAL POINT
======================================================================

Add:

`MT-7.6 - Product user manual and administrator operations guide`

after:

`MT-7.5 - Full functional parity and acceptance`

and before FINAL-AUDIT.

Dependencies:

`MT-7.5`

Scope:

Create the complete role-aware Mobisttech operational user manual from the
final verified product, including final UI screenshots and coverage of POS,
payments/reconciliation, documents/email/WhatsApp, inventory/procurement,
warranty, cash closing, Website CMS, legal content, Digital Services,
integrations, backups/reset safety, mobiST Control and customer Website.

Produce:

- canonical Markdown;
- verified DOCX mirror;
- verified PDF mirror;
- controlled screenshot assets.

Acceptance:

- every applicable final user-facing feature is mapped;
- navigation/permissions match actual UI;
- instructions are executed/checked against the real product;
- screenshots are current and safe;
- no legacy/placeholder UI is presented as final;
- Markdown/DOCX/PDF content parity passes;
- DOCX and PDF render correctly;
- every page is visually inspected;
- TOC/references work;
- security/destructive warnings are accurate;
- documented release/Git checkpoint is recorded;
- no material supported workflow is undocumented.

Change FINAL-AUDIT dependency from:

MT-7.5

to:

MT-7.6

======================================================================
BD. FINAL-AUDIT EXPANSION
======================================================================

FINAL-AUDIT must independently verify this entire approved consolidated
requirement.

It must inspect:

- actual Invoice/Warranty document UX;
- Gmail attachment delivery architecture;
- WhatsApp truthfulness;
- payment channels/destinations;
- split tenders;
- Website fixed payment model;
- Day Closing/reconciliation;
- dashboard Payment Mix;
- legal policy completeness;
- Privacy claims;
- service pricing/terms consistency;
- project-level license/NOTICE posture;
- third-party attribution handling;
- complete final User Manual.

FINAL-AUDIT must reopen any material mismatch.

Do not report Project complete: 100% while a required policy/manual/payment
or document-delivery requirement is missing.

======================================================================
PART VI — STRUCTURAL RECONCILIATION RUN
======================================================================

BE. STRUCTURAL UPDATE REQUIRED
======================================================================

This approved requirement materially changes roadmap content and adds two
new roadmap points.

In this documentation-only reconciliation run:

1. Confirm that the complete approved requirement is already safely persisted
   in:
   `docs/PROJECT_REQUIREMENTS_DOCUMENTS_PAYMENTS_LEGAL_MANUAL_v1.0.md`

2. Verify its complete content/hash before continuing.

3. Create its reconciliation/traceability evidence.

4. Update:
   `docs/PROJECT_SOURCE_OF_TRUTH.md`

5. Update:
   `docs/PROJECT_IMPLEMENTATION_ROADMAP.md`

6. Add the new approved requirement to authority headers.

7. Increment Source of Truth / roadmap versions appropriately.

8. Add:
   `MT-2.20 - POS payment channels, split tenders and settlement reconciliation`

9. Place MT-2.20 after MT-2.11 and before MT-2.12.

10. Change MT-2.12 dependency to MT-2.20.

11. Add:
    `MT-7.6 - Product user manual and administrator operations guide`

12. Place MT-7.6 after MT-7.5 and before FINAL-AUDIT.

13. Change FINAL-AUDIT dependency to MT-7.6.

14. Materially reconcile the affected existing points including:
    - MT-2.12
    - MT-3.1
    - MT-3.2
    - MT-4.2
    - MT-4.3
    - MT-4.4
    - MT-4.6
    - MT-5.3
    - MT-5.4
    - MT-7.2
    - MT-7.5
    - FINAL-AUDIT

15. Preserve every completed point ID/title/state.

16. Do not renumber completed or existing points.

17. Regenerate the SAME-BASENAME:
    `docs/PROJECT_IMPLEMENTATION_ROADMAP.docx`

because this is a material structural roadmap change.

18. Verify Markdown/DOCX body parity.

19. Render the final DOCX.

20. Visually inspect every rendered page.

21. Fix any:
    - clipping;
    - overlap;
    - missing content;
    - broken headings;
    - bad pagination;
    - footer/header defects.

22. Update:
    `docs/PROJECT_IMPLEMENTATION_STATUS.md`

with:
    - new requirement authority pointer;
    - updated SoT/roadmap version;
    - updated structural point count;
    - unchanged execution position;
    - reconciliation evidence.

23. The live next implementation point must remain:

    `MT-2.10 - Stocktake and cycle-count services`

24. This reconciliation must NOT start MT-2.10.

25. Verify Goal SHA/bytes remain unchanged.

26. Verify Preferences SHA/bytes remain unchanged.

27. Verify protected source repositories remain unchanged.

28. Do not run unnecessary application implementation.

29. Documentation-specific validation/source-protection checks are allowed.

30. Commit/push only the intended Mobisttech documentation reconciliation.

31. Verify clean local main == upstream/origin main after checkpoint.

32. Stop after this documentation checkpoint.

======================================================================
BF. INTERRUPTION / SURFACE-HANDOFF RECOVERY CONTRACT
======================================================================

If this reconciliation is interrupted AFTER the canonical approved
requirement file has been safely persisted, do NOT require the user to paste
this complete long requirement again.

A later authorized Work/Chat surface must recover using repository evidence.

It must read at minimum:

- current project registry and Project ID;
- `docs/PROJECT_REQUIREMENTS_DOCUMENTS_PAYMENTS_LEGAL_MANUAL_v1.0.md`;
- `docs/PROJECT_SOURCE_OF_TRUTH.md`;
- `docs/PROJECT_IMPLEMENTATION_ROADMAP.md`;
- `docs/PROJECT_IMPLEMENTATION_STATUS.md`;
- reconciliation/verification evidence already created;
- current Git status;
- current Git diff;
- current HEAD/history/upstream state.

Then determine whether interruption happened:

- after requirement preservation only;
- during Source of Truth reconciliation;
- during roadmap edits;
- during DOCX generation;
- during Markdown/DOCX parity verification;
- during rendered-page visual QA;
- during ledger update;
- before/after Git commit;
- before/after push;
- before final clean synchronization.

Continue only from the first verified unfinished recovery action.

Do not:

- restart the entire approved requirement blindly;
- duplicate already-created roadmap points;
- create duplicate requirement files;
- regenerate already-verified artifacts unnecessarily;
- overwrite valid interrupted changes merely to obtain a clean working tree;
- start MT-2.10;
- use `Y`, `Proceed` or `Next` semantics as a substitute for recovery.

The live execution position remains frozen at MT-2.10 until this structural
reconciliation itself has been fully completed and checkpointed.

======================================================================
BG. EXPECTED STRUCTURAL COUNTS
======================================================================

Freshly verify counts rather than blindly trusting this expectation.

Current verified baseline before this reconciliation is conceptually:

15 completed
54 total
39 pending

Exactly two new roadmap points are approved by this requirement:

- MT-2.20
- MT-7.6

Therefore, if no independent structural reason requires another new point,
the expected post-reconciliation count is:

15 completed
56 total
41 pending

The next pending implementation point remains:

MT-2.10 - Stocktake and cycle-count services

Do not mark either newly inserted point Complete merely because its roadmap
definition was created.

======================================================================
BH. DO NOT MODIFY / DO NOT EXECUTE
======================================================================

Do NOT modify account-level Custom Instructions.

Do NOT modify global project-control documents unless an independently
verified control conflict exists.

Do NOT alter Goal/Preferences content.

Do NOT implement runtime feature code in this run.

Do NOT send real Gmail messages.

Do NOT connect/reconnect/disconnect Gmail.

Do NOT activate WhatsApp Business API.

Do NOT activate JazzCash/Easypaisa/Card providers.

Do NOT execute real POS settlement.

Do NOT migrate private production/source data.

Do NOT change hosting/domain/production systems.

Do NOT alter protected source repos/remotes.

Do NOT start MT-2.10 automatically.

======================================================================
BI. REQUIRED COMPLETION RESPONSE
======================================================================

After successful reconciliation, report the exact verified result concisely.

Expected conceptual result:

- Approved consolidated requirement safely persisted and registered.
- Canonical requirement hash recorded.
- Goal preserved byte-identically.
- Preferences preserved byte-identically.
- SoT and roadmap structurally updated.
- MT-2.20 added Pending.
- MT-7.6 added Pending.
- MT-2.12 dependency updated.
- MT-3.1 document/email/reporting scope updated.
- MT-3.2 legal-policy scope updated.
- MT-4.2 split-tender UI ownership updated.
- MT-4.3 Invoice/Warranty delivery UX updated.
- MT-4.4 templates/payment-destination/legal Admin scope updated.
- MT-4.6 Day Closing/reconciliation scope updated.
- Website remains fixed to COD/JazzCash/Easypaisa/Card.
- MT-5.4 legal/service-pricing publication scope updated.
- MT-7.2 / MT-7.5 / FINAL-AUDIT traceability updated.
- FINAL-AUDIT now depends on MT-7.6.
- Roadmap Markdown/DOCX parity verified.
- DOCX rendered and every page visually inspected.
- Ledger synchronized.
- Protected sources untouched.
- Repository clean and origin-aligned.
- Live next implementation point remains:
  MT-2.10 - Stocktake and cycle-count services.

STOP after this reconciliation.

Do not proceed into MT-2.10.