# MT-3.3 Operational Recovery

MT-3.3 hardens the existing shared backup and integration foundations instead of introducing parallel engines.

Implemented authority:
- recursive audit payload redaction and query-free audit paths;
- configuration recovery snapshots with key, schema and code/lock identity checks;
- encrypted logical backup v2 manifests with table row counts/hashes;
- target-only verify-only restore rehearsals on `mobisttech_test:13306`;
- durable domain-event leases, bounded retries and terminal failure evidence;
- backup history/read/delete guards and fixed private backup paths;
- Google/rclone adapters constrained to approved providers, `rclone` binary and backup namespace;
- external integrations and scheduled external backups disabled by default.

Recovery snapshots never store plaintext provider credentials. Secret state is represented by ciphertext digests/version metadata, so configuration verification cannot resurrect a rotated or revoked secret.

No generic SQL, shell, command, URL-fetch or arbitrary provider execution endpoint is added by this point. Destructive production restore remains under the roadmap HOLD gates and is not implemented here.
