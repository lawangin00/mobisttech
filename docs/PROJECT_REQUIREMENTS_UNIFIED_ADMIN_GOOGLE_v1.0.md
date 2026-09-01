NEW APPROVED REQUIREMENT — UNIFIED ADMIN IDENTITY, CANONICAL BUSINESS
IDENTITY, DYNAMIC GMAIL EMAIL INTEGRATION, AND DYNAMIC GOOGLE DRIVE
BACKUP INTEGRATION

This instruction is being provided directly inside the already-active
mobiST Tech whole-step implementation project.

Do NOT require a separate RESUME / VERIFY command before processing this
instruction.

Before implementing any change:

1. Read docs/PROJECT_IMPLEMENTATION_STATUS.md.
2. Identify and read the active canonical Source of Truth and roadmap.
3. Verify current Git HEAD/history and actual repository/code state.
4. Reconcile this approved requirement structurally before continuing the
   currently pending implementation point.
5. Follow the global project-control rules.
6. Do not rely on conversation memory where repository evidence differs.

This requirement supersedes the previously approved separate
admin / superadmin / website-admin credential architecture and also
supersedes any previous plan that treats Gmail App Password / SMTP as the
primary supported email integration.

======================================================================
A. UNIFIED ADMIN IDENTITY — FINAL APPROVED MODEL
======================================================================

Use one administrative identity type only:

Admin

Do not present or require separate administrative credential identities
named:

- Superadmin
- Owner
- Business Admin
- Website Admin

The same Admin credential/account must be usable across:

- POS / operational portal
- Website administration / CMS

There must be one administrative credential identity per human.

A person must not require separate Website and POS credentials.

Different Admin accounts may have different explicit permissions,
capabilities and outlet assignments, but they remain the same identity type:

Admin

Examples:

- Primary administrator:
  Admin with all approved capabilities.

- Manager:
  Admin with management capabilities required for that person.

- Staff/operator:
  Admin with explicitly assigned least-privilege operational capabilities.

Do not create shared generic credentials for multiple employees.

Customer/public accounts remain a completely separate authentication realm
and must never inherit Admin access.

Historical POS admin, superadmin and website-admin source identities must
NOT be automatically privilege-merged merely because email addresses match.

Legacy administrative identities must be reconciled only through explicit,
verified identity mapping so migration cannot accidentally escalate
privileges.

User-facing terminology must simply be:

Admin

Internally, explicit permission domains, capabilities, policies and outlet
assignments may remain where technically appropriate, but they are
authorization attributes of one Admin identity and not separate credential
identities.

======================================================================
B. ADMIN AUTHENTICATION / SECURITY
======================================================================

The final Admin architecture must provide:

- One credential identity per Admin.
- One password for that identity.
- One password-reset identity.
- Same Admin credentials accepted by Website administration and POS.
- Explicit Website/CMS permissions.
- Explicit POS permissions.
- Explicit outlet assignments where applicable.
- Least-privilege authorization.
- No privilege union merely because legacy records share an email.
- Consistent session revocation after password changes/resets.
- Existing device/session security preserved or improved.
- Customer authentication isolated from Admin authentication.
- Auditable migration/provenance mapping for legacy administrative records.

Existing MT-2.2 verification evidence must not be falsified or rewritten as
if the previous architecture never existed.

Treat this as an approved superseding remediation.

======================================================================
C. CANONICAL BUSINESS IDENTITY
======================================================================

Establish one authoritative current business identity:

Business Name:
mobiST Technologies

Business Email:
mobisttech@gmail.com

Public Website:
https://mobisttech.com

Do not scatter these values through unrelated hard-coded application files.

Create/use one canonical business-profile/configuration authority and make
relevant runtime surfaces consume it.

Where applicable it must drive:

- Website contact information
- Website footer/business information
- invoices
- warranty documents
- transactional notifications
- outgoing email identity
- password-recovery communication
- public business/profile endpoints
- canonical Website URL / SEO configuration

Historical protected source snapshots/evidence may retain previous business
email/domain values for provenance.

Do not rewrite protected source history merely to replace historical
contact information.

After remediation, no active mobiST Tech runtime should use the previous
business Gmail/domain as its current business identity.

======================================================================
D. EMAIL INTEGRATION — FINAL APPROVED APPROACH
======================================================================

The primary supported email integration must be:

Google OAuth 2.0 + Gmail API

Do NOT make Gmail username + App Password SMTP the normal supported setup.

Approved Gmail account:

mobisttech@gmail.com

Desired Admin workflow:

Admin
→ Settings
→ Integrations
→ Gmail

When Gmail has not been connected:

Gmail
Status: Not Connected

[ Connect Gmail ]

Clicking Connect Gmail must initiate a secure backend-controlled Google
OAuth flow.

Expected flow:

1. Admin clicks Connect Gmail.
2. Backend creates/initiates the Google OAuth authorization request.
3. Google authorization screen opens.
4. Admin selects/signs into:

   mobisttech@gmail.com

5. Google displays the requested Gmail permission.
6. Admin approves it.
7. OAuth callback returns to the backend.
8. Backend exchanges the authorization code securely.
9. Backend stores the required token/refresh-token state securely.
10. Backend sends a controlled test email.
11. Only after successful verification does the UI show:

    Gmail
    Status: Connected

Normal supported setup must NOT require the Admin to:

- open a terminal
- manually edit .env
- manually paste MAIL_PASSWORD
- manually configure SMTP
- manually re-enter a Gmail App Password on every new server

======================================================================
E. GMAIL API PERMISSION MODEL
======================================================================

Request the narrowest Gmail scope required for transactional sending.

Primary required scope:

https://www.googleapis.com/auth/gmail.send

Do not request Gmail read/modify/full-mailbox access merely to send
transactional application emails.

The integration is for sending approved application messages such as:

- Forgot Password / Reset Password
- account/security notifications
- order notifications where approved
- warranty/service notifications where approved
- other explicit mobiST Tech transactional messages

Do not build mailbox-reading functionality unless separately approved.

The Gmail API authorization must represent the approved connected account:

mobisttech@gmail.com

======================================================================
F. EMAIL PRESENTATION / SENDER IDENTITY
======================================================================

Outgoing messages must be constructed so recipients see the normal business
sender identity:

mobiST Technologies <mobisttech@gmail.com>

The Google OAuth application identity belongs to the authorization/consent
flow and must not replace the business sender identity in outgoing email.

Replies should go to the approved business mailbox unless a later explicit
business requirement introduces a different verified Reply-To address.

Email templates must use proper MIME/HTML/text structure and remain
compatible with normal email clients.

The application must not send or expose account passwords by email.

======================================================================
G. FORGOT PASSWORD / RESET PASSWORD
======================================================================

Forgot Password must NEVER send the user's existing password.

Required behavior:

1. User/Admin requests password reset.
2. Application generates a secure, single-use reset token.
3. Token has an appropriate expiry.
4. Gmail integration sends a branded reset email from:

   mobiST Technologies <mobisttech@gmail.com>

5. Email contains a secure HTTPS Reset Password link.
6. Existing password is never disclosed.
7. Reset token must not be logged.
8. Successful reset invalidates the token.
9. Relevant existing sessions are revoked according to the security model.
10. Responses must avoid account-enumeration leakage.

Production reset links must use the approved production application
host/domain architecture rather than localhost URLs.

======================================================================
H. GOOGLE OAUTH APPLICATION BOOTSTRAP FOR GMAIL
======================================================================

Dynamic Connect Gmail still requires application-level Google OAuth client
configuration.

Treat this separately from per-server Gmail account connection.

The project/deployment architecture must support appropriate Google OAuth
client configuration for development and production.

OAuth client ID/client secret are application/deployment secrets.

They must not be:

- hard-coded into frontend source
- committed to Git
- exposed in API responses
- displayed to normal Admin users
- written into logs/evidence

Register only approved redirect/callback URIs.

Development and production OAuth configuration should be separated where
required by Google policy and environment architecture.

A fresh machine/server should not require creation of a new Google OAuth
application every time.

Once the application OAuth client is provisioned, the normal per-environment
user experience is:

Connect Gmail
→ Google Allow
→ Connected

======================================================================
I. GOOGLE OAUTH PRODUCTION READINESS
======================================================================

gmail.send is a Google sensitive scope.

Therefore the implementation/deployment plan must account for Google's
current OAuth consent-screen, publishing and verification requirements as
applicable to the final usage model.

Do not treat an OAuth app left permanently in Testing mode as the final
production architecture if that causes limited/expiring authorization.

Before production go-live:

- configure accurate OAuth app branding
- use the approved business/application identity
- verify required domains where applicable
- declare only the required scope(s)
- satisfy Google's applicable production/verification requirements
- ensure durable authorized operation for the approved Gmail account

Do not broaden permissions merely to simplify verification.

======================================================================
J. GMAIL TOKEN / SECRET STORAGE
======================================================================

OAuth access tokens, refresh tokens and OAuth client secrets are
server-side secrets.

Never expose them to frontend JavaScript.

Never commit them to Git.

Never put real values in .env.example.

Never print them in application logs or generated evidence.

Store authorization state using an appropriate private encrypted/server-side
secret mechanism.

If stored in the application database, sensitive token values must be
appropriately encrypted and access-restricted.

The frontend may receive safe state such as:

Provider: Gmail
Account: mobisttech@gmail.com
Status: Connected

but never raw credentials.

======================================================================
K. GMAIL TEST / RECONNECT / DISCONNECT
======================================================================

Admin Gmail integration should safely support:

- Connect
- Test Email
- Reconnect
- Disconnect
- status display
- last successful send/test
- safe last-error summary

Test Email must send through the actual backend Gmail integration.

Reconnect must allow OAuth authorization to be recreated/refreshed safely.

Disconnect must:

- stop mobiST Tech from using the Gmail integration
- revoke/delete application-side authorization state as appropriate
- leave the integration as Not Connected
- not reveal credentials

After disconnect:

Gmail
Status: Not Connected

======================================================================
L. EXISTING GOOGLE APP PASSWORD
======================================================================

A Google App Password has already been generated manually for
mobisttech@gmail.com with the label:

mobiST Technologies Mail

It has been saved privately by the administrator.

This App Password is NOT the primary supported architecture.

Do not require it for normal:

- development setup
- production deployment
- Forgot Password operation
- server migration
- Admin Connect Gmail flow

Do not request that it be pasted into source code, Git, documentation or
normal Admin settings.

It may remain temporarily available as an emergency/fallback credential only
until the OAuth/Gmail API integration has been fully implemented and
verified.

Do not automatically revoke/delete it during implementation.

Its later revocation may be performed explicitly after the Gmail API path is
proven and approved.

======================================================================
M. GOOGLE DRIVE / RCLONE — EXISTING STATE
======================================================================

On the current Windows development machine, the approved Google Drive rclone
remote has already been configured and successfully read/write/delete
verified:

mobisttech-drive:

The old previous-project remote also exists:

mobist-drive:

Do NOT:

- migrate old backup files
- copy them
- re-upload them
- import previous-project backup history merely to reproduce old backups

Those archives belong to the previous deployable project and are not
required by mobiST Tech.

Leave previous-project backup files and old remote untouched unless a later
explicit instruction authorizes otherwise.

Only NEW mobiST Tech backups belong to the new approved backup integration.

======================================================================
N. DYNAMIC GOOGLE DRIVE CONNECTION
======================================================================

Google Drive configuration must be dynamically manageable through Admin.

Desired workflow:

Admin
→ Settings
→ Backups / Integrations
→ Google Drive

If no valid integration exists:

Google Drive
Status: Not Connected

[ Connect Google Drive ]

When Admin clicks Connect Google Drive:

1. Backend checks whether a usable approved remote already exists.
2. If valid mobisttech-drive: exists, verify it.
3. Otherwise initiate secure Google Drive/rclone OAuth setup.
4. Google authorization opens.
5. Admin authorizes using the approved Google account.
6. OAuth/config completion occurs server-side.
7. Backend creates/updates:

   mobisttech-drive:

8. Private rclone config is stored server-side outside Git/public web root.
9. Backend automatically performs validation.
10. Validation must prove required:
    - connect/list/read
    - write
    - delete/cleanup
11. Temporary validation object is removed.
12. Only then display:

    Google Drive
    Status: Connected

Normal supported setup must not require manually opening an SSH/terminal
session and running rclone config.

======================================================================
O. LOCAL / FRESH SERVER GOOGLE DRIVE BEHAVIOR
======================================================================

CURRENT WINDOWS DEVELOPMENT MACHINE

The application may detect and verify the already configured:

mobisttech-drive:

If safely resolvable and valid:

Status: Connected

Otherwise use the same Connect Google Drive workflow.

Do not bind the application to the current Windows machine.

Do NOT hard-code:

C:\Users\...\rclone\rclone.conf

FRESH DEVELOPMENT MACHINE

Initially:

Google Drive
Status: Not Connected

Admin:
Connect Google Drive
→ Google Allow
→ backend configuration/test
→ Connected

FRESH PRODUCTION LINUX SERVER

Initially:

Google Drive
Status: Not Connected

Admin:
Connect Google Drive
→ Google Allow
→ backend creates secure server-side rclone config
→ verifies connection
→ Connected

FUTURE SERVER MIGRATION

If an authorized secure config has not been restored:

Status: Not Connected

Use the same Connect Google Drive flow.

No application-code change is required.

======================================================================
P. RCLONE RUNTIME PROVISIONING
======================================================================

Production deployment must ensure an approved supported rclone runtime is
available.

Deployment/setup should:

- detect/provision rclone
- prepare a private configuration location
- apply secure permissions
- avoid Git/public storage for OAuth state
- allow Admin Connect Google Drive to complete authorization

Keep separate:

1. application code
2. rclone executable/runtime
3. secret rclone/OAuth configuration

Windows development and Linux production must use the same application
architecture without code changes for machine-specific config paths.

======================================================================
Q. GOOGLE DRIVE ADMIN OPERATIONS
======================================================================

Admin may safely see/manage:

- Provider: Google Drive
- Connected / Not Connected / Error
- logical remote name
- backup destination
- backup schedule
- retention policy
- last successful backup
- last failed backup
- last backup size
- safe last-error summary
- Test Connection
- Backup Now
- Connect
- Reconnect
- Disconnect

Never expose:

- OAuth access/refresh tokens
- raw rclone.conf
- Google client secret
- Gmail OAuth secrets
- Gmail App Password
- unrelated environment/server secrets

======================================================================
R. BACKUP TRUST BOUNDARY
======================================================================

Frontend must never directly execute/access rclone.

Required flow:

Website / Admin frontend
        ↓
Laravel backend
        ↓
Backup service / queued job / scheduler
        ↓
server-side rclone integration
        ↓
mobisttech-drive:
        ↓
Google Drive

Backup Now must invoke a backend-controlled action/job.

Scheduled backups use the same server-side integration.

Use a dedicated mobiST Tech backup namespace/folder, for example:

mobiST Tech/
└── Backups/

The implementation may refine environment/date/scope hierarchy if required,
but it must be canonical and documented.

Do not mix previous-project backup migration into this new backup stream.

======================================================================
S. DRIVE DISCONNECT / RECONNECT SECURITY
======================================================================

Reconnect must:

- allow authorization to be safely restored/recreated
- revalidate connectivity before reporting Connected

Disconnect must:

- disable the integration in mobiST Tech
- leave state clearly Not Connected
- not expose credentials
- not automatically delete Google Drive backup archives

Deleting backup archives must remain a separate explicit authorized backup
management operation.

If rclone RC/API or another management interface is used internally, it
must remain private/server-side and must not become an unauthenticated
public endpoint.

======================================================================
T. CONSISTENT ADMIN INTEGRATIONS EXPERIENCE
======================================================================

The desired integration experience should be consistent:

Admin
→ Settings
→ Integrations

Gmail
Status: Not Connected / Connected
[ Connect Gmail ]

Google Drive
Status: Not Connected / Connected
[ Connect Google Drive ]

For Gmail:

Connect
→ Google login/consent
→ OAuth callback
→ test email
→ Connected

For Google Drive:

Connect
→ Google login/consent
→ backend rclone configuration
→ read/write/delete test
→ Connected

The administrator should not normally need to manually edit application
configuration files for either integration.

======================================================================
U. PROJECT-CONTROL RECONCILIATION
======================================================================

This requirement materially changes already-completed identity architecture
and integration assumptions.

Do not silently rewrite completed project history.

Create an explicit superseding remediation/reconciliation implementation
point before later work that depends on the old architecture.

It must cover at minimum:

- unified Admin credential architecture
- legacy administrative identity mapping
- POS permission reconciliation
- Website permission reconciliation
- outlet authorization
- Admin session/reset/security remediation
- canonical business identity
- canonical email/domain
- dynamic Gmail OAuth/Gmail API integration
- Gmail reset/transactional email delivery
- Google OAuth secret/token security
- Google OAuth production readiness
- dynamic Google Drive/rclone integration
- development/production portability
- deployment provisioning
- backup security boundaries
- regression/security/integration testing

Update the canonical:

- Source of Truth
- roadmap
- implementation-status ledger

according to the global project-control rules.

Do not falsify previous checkpoint evidence.

The previous checkpoint remains historical evidence of the architecture that
was implemented at that time.

The new remediation supersedes relevant target behavior prospectively.

If roadmap structure changes materially:

- update canonical roadmap Markdown
- regenerate the same-basename DOCX
- verify Markdown/DOCX content parity
- render the final DOCX
- visually inspect every rendered page
- fix any issue
- only then treat documentation reconciliation as verified

======================================================================
V. RECOMMENDED IMPLEMENTATION ORDER
======================================================================

1. Verify current repository/project state.
2. Read current SoT, roadmap and ledger.
3. Reconcile this requirement structurally.
4. Add the required superseding remediation point.
5. Implement unified Admin identity.
6. Reconcile target administrative records safely.
7. Update permissions/outlet authorization.
8. Update Admin session/reset/security behavior.
9. Update identity/security tests.
10. Establish canonical business identity.
11. Replace active business email/domain consumers.
12. Implement Gmail integration abstraction.
13. Implement Google OAuth Connect Gmail flow.
14. Implement Gmail API send using minimum gmail.send scope.
15. Implement branded transactional/reset email.
16. Implement Test Email / Reconnect / Disconnect.
17. Implement secure token storage.
18. Account for Google OAuth production-readiness requirements.
19. Implement server-side backup integration abstraction.
20. Implement Admin Connect Google Drive workflow.
21. Implement backend-controlled rclone authorization/configuration.
22. Implement/provision rclone runtime for deployment.
23. Detect/verify existing local mobisttech-drive: where appropriate.
24. Support fresh-server Google Drive connection.
25. Implement Drive Test Connection.
26. Implement Backup Now through backend service/job.
27. Point NEW mobiST Tech backups to approved integration.
28. Do not migrate historical backup archives.
29. Verify Windows-development / Linux-production portability.
30. Run full regression/security/integration tests.
31. Update implementation ledger with evidence.
32. Commit/push only after required checks pass.

======================================================================
W. FINAL ACCEPTANCE CRITERIA
======================================================================

ADMIN

- One human needs one Admin credential for Website + POS administration.
- No separate target Website Admin credential required.
- No separate target Superadmin credential required.
- No Owner credential identity required.
- User-facing administrative terminology is simply Admin.
- POS permissions are explicit.
- Website permissions are explicit.
- Outlet assignments are explicit.
- Same-email legacy records cannot silently combine privileges.
- Customer accounts remain isolated.
- Password/reset session behavior is correct.

BUSINESS IDENTITY

- Current business name is mobiST Technologies.
- Current business email is mobisttech@gmail.com.
- Current public domain is https://mobisttech.com.
- Active runtime no longer uses previous business Gmail/domain.

GMAIL

- Fresh environment without Gmail authorization shows Not Connected.
- Admin can click Connect Gmail.
- Google authorization uses mobisttech@gmail.com.
- Minimum gmail.send scope is used for send-only functionality.
- Backend securely stores required OAuth state.
- Test Email succeeds before Connected is reported.
- Normal production/development setup does not require manual Gmail App
  Password entry.
- Normal server migration does not require manual SMTP setup.
- Outgoing email displays the approved business sender.
- Forgot Password sends a reset link, never the existing password.
- Gmail OAuth tokens/secrets never reach frontend/Git/logs.
- Reconnect/Disconnect operate safely.
- Production OAuth publishing/verification requirements are addressed as
  applicable.
- The previously generated App Password is not required by the normal
  supported architecture.

GOOGLE DRIVE

- Existing valid local mobisttech-drive: may be detected/verified.
- Fresh environment shows Not Connected.
- Admin can click Connect Google Drive.
- Backend creates/updates private server-side rclone configuration.
- Read/write/delete validation succeeds before Connected.
- Normal setup requires no manual rclone config terminal workflow.
- Fresh production server supports the same Admin connection process.
- Server migration requires no application-code change.
- Production deployment provisions rclone.
- No Windows-specific config path is hard-coded.
- Drive OAuth secrets never reach frontend/Git/logs.
- Test Connection works.
- Backup Now works through backend execution.
- Scheduled backups use the secure integration.
- NEW backups use the approved mobisttech-drive: integration.
- Previous-project backups are not migrated.
- Disconnect does not silently delete archives.

PROJECT GOVERNANCE

- Previous evidence remains historically accurate.
- New architecture is recorded as a superseding remediation.
- SoT, roadmap and ledger are reconciled.
- Required roadmap DOCX parity/render/visual checks are performed if its
  structure changes.
- Required automated tests pass.
- Git/project evidence accurately represents final verified behavior.