# MT-4.1 Verification

- Point: MT-4.1 - POS shell, authentication and navigation
- Playwright: 2/2 desktop/mobile role journeys PASS, including direct-route permission denial.
- Team Member/session regression: 8 tests / 54 assertions PASS.
- Full backend regression: 225 tests / 7014 assertions PASS.
- Changed PHP syntax/Pint: 8/8 files PASS.
- API schema verifier: PASS at 170 tables / 2104 columns / 364 foreign keys / 817 indexes; SHA-256 e092af61d6a36c10df55e04782bf59c101f0beae11029d5842c531f6adee70a3.
- Composer validate/check-platform, backend TypeScript/Vite build, Website lint/typecheck/Next build: PASS.
- Full-repository Pint has only the pre-existing unrelated tests/Feature/IdentitySecurityTest.php line-ending baseline difference; no MT-4.1 changed PHP file fails Pint.
