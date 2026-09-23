# MT-7.5 / W04: Easypaisa sandbox MA configuration and container binding

Date: 2026-09-23 (PKT). Scope: existing uncommitted Easypaisa REST v4 MA transport configuration, application container injection, and synthetic tests only. The remote registry-only commit `a28e1cd7db8a9ab663b30198324fdfb374bcde74` was fast-forwarded before this checkpoint; SHA-256 hashes verified preservation of all four local work files.

- `backend/config/easypaisa.php` loads a separately named MA transport configuration. It is disabled by default, pinned to sandbox REST v4 without RSA, and is not a checkout/hosted payment adapter.
- `backend/.env.example` documents only disabled placeholders. No merchant credentials were introduced or activated.
- `backend/app/Providers/AppServiceProvider.php` binds `EasypaisaRestClient` to its separate config without adding it to the checkout provider registry.
- `backend/tests/Feature/W04EasypaisaConfigurationTest.php` tests default-OFF unavailability, synthetic sandbox injection, absence of credential exposure in channel status, and nonavailability of public checkout.

Verification: PHP syntax on the three relevant PHP files PASS; scoped Laravel Pint --test PASS (3 files). `php artisan test tests/Feature/W04EasypaisaRestClientTest.php tests/Feature/W04EasypaisaConfigurationTest.php --no-ansi`: 9/9 PASS (33 assertions). A preliminary isolated configuration test also passed 2/2 (9 assertions). Http::fake() supplied synthetic provider responses; no real provider call or live payment was authorized.

Remaining boundary: W04 and MT-7.5 remain In Progress (15/27 DONE, 12 OPEN in the live acceptance matrix). H-02 official merchant-specific REST/RSA variant, credentials, signed IPN/callback, checkout UX, refund and settlement contracts and verified provider sandbox lifecycle are still OPEN. Do not treat this configuration as gateway activation, payment acceptance, or settlement proof.
