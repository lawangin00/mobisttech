# MT-2.19 Team Member Roles and Session Security

The approved requirement is `docs/PROJECT_REQUIREMENTS_TEAM_MEMBERS_SESSION_POLICY_v1.0.md`. `RECONCILIATION.md` maps it into the canonical architecture and roadmap. `MT_2_19_VERIFICATION.json` records fresh implementation, schema, security, regression, document and Git evidence.

The Laravel backend remains the only human identity/session/authorization authority. POS and Website interfaces consume its Admin or Customer contracts; they must not create employee credential realms or frontend-only authorization.
