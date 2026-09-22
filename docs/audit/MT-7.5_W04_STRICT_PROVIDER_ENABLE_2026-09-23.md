# MT-7.5 W04 — strict payment-provider enablement (23-Sep-2026)

Status: bounded local fail-closed activation correction PASS. W04 IN PROGRESS; finite checklist 15/27 DONE, 12 OPEN. No provider approval or genuine merchant activation.

## Finding and correction

`PaymentProviders::assertAvailable` previously accepted any PHP-truthy `enabled` value, including string `false`, when an external adapter and merchant were present. It now requires literal boolean `true` for every channel. Existing provider registry, merchant/mode checks, COD policy, and external default-OFF configuration remain intact.

Expanded synthetic W04 provider-configuration test verifies all three external gateway slugs reject string `false`, numeric `1`, and string `true` without invoking their adapter. The neighboring valid boolean-true sandbox configuration continues to advertise the synthetic adapter as available; no vendor adapter or authentic credentials were registered.

## Evidence boundary

Focused W04ProviderConfigurationShapeTest: 1/1 PASS, 57 assertions. Joined W04-prefixed PHP suite: 21/21 PASS, 360 assertions. PHP syntax, scoped Pint and diff whitespace checks PASS. Tests use only isolated `mobisttech_test`; restore initially stopped MySQL after verification. No actual provider transactions, credential writes, refund or settlement verified. Genuine vendor contracts and H-02 remain HOLD; remaining payment parity and W04 family closure remain OPEN.
