<?php

namespace App\Commerce;

use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Identity\IdentityAudit;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Read-only, nonsecret payment-channel presentation policy; never an activation authority. */
final class WebsitePaymentPresentation
{
    private const DOMAIN = 'website.payments.presentation';

    private const DEFAULT_LABELS = [
        'cod' => 'Cash on Delivery',
        'jazzcash' => 'JazzCash',
        'easypaisa' => 'Easypaisa',
        'card' => 'Credit / Debit Card',
    ];

    public function defaults(): array
    {
        return [
            'channels' => array_map(fn (string $label): array => ['label' => $label, 'instructions' => ''], self::DEFAULT_LABELS),
            'cod_min_amount' => null,
            'cod_max_amount' => null,
        ];
    }

    /** Strict allowlisting prevents arbitrary HTML, provider fields, secret storage and mode changes. */
    public function validate(array $input): array
    {
        $defaults = $this->defaults();
        abort_unless(count($input) === count($defaults) && ! array_diff_key($input, $defaults), 422);
        abort_unless(is_array($input['channels']) && count($input['channels']) === count(self::DEFAULT_LABELS)
            && ! array_diff_key($input['channels'], self::DEFAULT_LABELS), 422);
        foreach ($input['channels'] as $code => $details) {
            abort_unless(is_array($details) && count($details) === 2
                && ! array_diff_key($details, ['label' => '', 'instructions' => '']), 422);
            foreach (['label' => 80, 'instructions' => 500] as $field => $max) {
                $value = $field === 'instructions' && $details[$field] === null ? '' : $details[$field];
                abort_unless(is_string($value) && mb_strlen($value) <= $max
                    && ! preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F]/u', $value), 422);
                abort_unless($field !== 'label' || trim($value) !== '', 422);
            }
        }
        foreach (['cod_min_amount', 'cod_max_amount'] as $key) {
            $value = $input[$key];
            abort_unless($value === null || (is_string($value)
                && preg_match('/\A(?:0|[1-9][0-9]{0,8})\.[0-9]{2}\z/D', $value) === 1), 422);
        }
        if ($input['cod_min_amount'] !== null && $input['cod_max_amount'] !== null) {
            abort_unless(bccomp($input['cod_min_amount'], $input['cod_max_amount'], 2) <= 0, 422);
        }

        $normalized = $this->defaults();
        foreach (array_keys(self::DEFAULT_LABELS) as $code) {
            $normalized['channels'][$code] = [
                'label' => $input['channels'][$code]['label'],
                'instructions' => $input['channels'][$code]['instructions'] ?? '',
            ];
        }
        $normalized['cod_min_amount'] = $input['cod_min_amount'];
        $normalized['cod_max_amount'] = $input['cod_max_amount'];

        return $normalized;
    }

    /** Separate nonsecret revision domain: COD enablement keeps its original revision contract. */
    public function revisions(): array
    {
        $published = DB::table('site_configuration_revisions')->where('domain', self::DOMAIN)
            ->where('state', 'published')->orderByDesc('version')->first();
        $draft = DB::table('site_configuration_revisions')->where('domain', self::DOMAIN)
            ->where('state', 'draft')->where('version', '>', (int) ($published->version ?? 0))
            ->orderByDesc('version')->first();
        $draftPolicy = $this->parse($draft?->snapshot);

        return [
            'published' => $this->published(),
            'published_version' => (int) ($published->version ?? 0),
            'published_invalid' => (bool) ($published && $this->parse($published->snapshot) === null),
            'draft_invalid' => (bool) ($draft && $draftPolicy === null),
            'draft' => $draftPolicy === null ? null : [
                'id' => (int) $draft->id, 'version' => (int) $draft->version, 'policy' => $draftPolicy,
            ],
        ];
    }

    private function parse(?string $snapshot): ?array
    {
        if ($snapshot === null) {
            return null;
        }
        try {
            $decoded = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $this->validate($decoded) : null;
        } catch (\JsonException|HttpException) {
            return null;
        }
    }

    /** Nonsecret policy drafts are permissioned; they never mutate COD enablement or adapters. */
    public function saveDraft(IdentityAccount $actor, array $input): array
    {
        $admin = $this->authorize($actor);
        $policy = $this->validate($input);

        return $this->serializedRevision(function () use ($admin, $policy): array {
            $version = (int) DB::table('site_configuration_revisions')->where('domain', self::DOMAIN)
                ->lockForUpdate()->max('version') + 1;
            $id = DB::table('site_configuration_revisions')->insertGetId([
                'domain' => self::DOMAIN, 'version' => $version, 'state' => 'draft',
                'snapshot' => json_encode($policy, JSON_THROW_ON_ERROR),
                'created_by_admin_id' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $admin->id, 'website_payment_presentation_draft_saved',
                'site_configuration_revision:'.$id);

            return ['id' => $id, 'version' => $version, 'policy' => $policy];
        });
    }

    public function publish(IdentityAccount $actor, int $draftId): array
    {
        $admin = $this->authorize($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.publish'), 403);

        return $this->serializedRevision(function () use ($admin, $draftId): array {
            $draft = DB::table('site_configuration_revisions')->where('domain', self::DOMAIN)
                ->where('id', $draftId)->lockForUpdate()->first();
            abort_unless($draft && $draft->state === 'draft', 409, 'Presentation draft not available.');
            abort_unless((int) $draft->version === (int) DB::table('site_configuration_revisions')
                ->where('domain', self::DOMAIN)->max('version'), 409, 'A newer presentation draft exists.');
            $policy = $this->parse($draft->snapshot);
            abort_unless($policy !== null, 409, 'Presentation draft is invalid.');

            DB::table('site_configuration_revisions')->where('domain', self::DOMAIN)
                ->where('state', 'published')->update(['state' => 'superseded', 'updated_at' => now()]);
            DB::table('site_configuration_revisions')->where('id', $draftId)->update([
                'state' => 'published', 'published_by_admin_id' => $admin->id,
                'published_at' => now(), 'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $admin->id, 'website_payment_presentation_published',
                'site_configuration_revision:'.$draftId);

            return ['id' => $draftId, 'version' => (int) $draft->version, 'policy' => $policy];
        });
    }

    private function serializedRevision(callable $operation): array
    {
        $connection = DB::connection();
        $acquired = $connection->selectOne('SELECT GET_LOCK(?, 5) AS acquired', ['mobisttech.website.payments.presentation.revision']);
        abort_unless((int) ($acquired->acquired ?? 0) === 1, 409, 'Presentation revision is busy.');
        try {
            return $connection->transaction($operation);
        } finally {
            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', ['mobisttech.website.payments.presentation.revision']);
        }
    }

    private function authorize(IdentityAccount $actor): Admin
    {
        abort_unless($actor instanceof Admin
            && app(Access::class)->allows($actor, 'website.payments.manage'), 403);

        return $actor;
    }

    /** Applies to newly created COD orders only, after server-side discounts. */
    public function assertCodAmount(string $amount): void
    {
        $policy = $this->published();
        if ($policy['cod_min_amount'] !== null && bccomp($amount, $policy['cod_min_amount'], 2) < 0) {
            abort(422, 'Order amount is below the Cash on Delivery minimum.');
        }
        if ($policy['cod_max_amount'] !== null && bccomp($amount, $policy['cod_max_amount'], 2) > 0) {
            abort(422, 'Order amount exceeds the Cash on Delivery maximum.');
        }
    }

    public function published(): array
    {
        $snapshot = DB::table('site_configuration_revisions')->where('domain', self::DOMAIN)
            ->where('state', 'published')->orderByDesc('version')->value('snapshot');
        if (! is_string($snapshot)) {
            return $this->defaults();
        }
        try {
            $value = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);

            return is_array($value) ? $this->validate($value) : $this->defaults();
        } catch (\JsonException|HttpException) {
            // Invalid or tampered settings cannot activate a channel or override defaults.
            return $this->defaults();
        }
    }
}
