<?php

namespace App\Commerce;

use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Identity\IdentityAudit;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Nonsecret Website payment policy; external merchant credentials are never accepted here. */
final class WebsitePaymentAdministration
{
    private const COD_DOMAIN = 'website.payments.cod';

    public function __construct(private PaymentProviders $providers) {}

    public function overview(IdentityAccount $actor): array
    {
        $this->authorize($actor);

        return array_map(function (array $channel): array {
            $code = $channel['code'];
            $configuration = config('commerce.providers.'.$code, []);
            $configuration = is_array($configuration) ? $configuration : [];

            return [
                'code' => $code,
                'label' => $channel['label'],
                'enabled' => $code === 'cod' ? $this->codEnabled() : (bool) ($configuration['enabled'] ?? false),
                'merchant_configured' => $code === 'cod' || (is_string($configuration['merchant'] ?? null)
                    && trim($configuration['merchant']) !== ''),
                'available' => $channel['available'],
            ];
        }, $this->providers->checkoutChannels());
    }

    /** The effective policy is public to checkout but only its nonsecret status is shown in Admin. */
    public function codEnabled(): bool
    {
        $snapshot = DB::table('site_configuration_revisions')->where('domain', self::COD_DOMAIN)
            ->where('state', 'published')->orderByDesc('version')->value('snapshot');
        if ($snapshot === null) {
            return (bool) config('commerce.providers.cod.enabled', false);
        }
        $policy = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($policy) || array_keys($policy) !== ['cod_enabled'] || ! is_bool($policy['cod_enabled'])) {
            throw new LogicException('Published COD policy is invalid.');
        }

        return $policy['cod_enabled'] && (bool) config('commerce.providers.cod.enabled', false);
    }

    public function settings(IdentityAccount $actor): array
    {
        $admin = $this->authorize($actor);
        $published = DB::table('site_configuration_revisions')->where('domain', self::COD_DOMAIN)
            ->where('state', 'published')->orderByDesc('version')->first();
        $draft = DB::table('site_configuration_revisions')->where('domain', self::COD_DOMAIN)
            ->where('state', 'draft')->orderByDesc('version')->first();
        $draftPolicy = $draft ? json_decode($draft->snapshot, true, flags: JSON_THROW_ON_ERROR) : null;

        return [
            'cod_enabled' => $this->codEnabled(),
            'published_version' => (int) ($published->version ?? 0),
            'draft' => $draft ? [
                'id' => (int) $draft->id,
                'version' => (int) $draft->version,
                'cod_enabled' => (bool) ($draftPolicy['cod_enabled'] ?? false),
            ] : null,
            'can_publish' => app(Access::class)->allows($admin, 'website.publish'),
        ];
    }

    public function saveDraft(IdentityAccount $actor, array $input): array
    {
        $admin = $this->authorize($actor);
        // Deliberately no provider, merchant, credentials, mode, limits or unknown-key writes.
        abort_unless(array_keys($input) === ['cod_enabled'] && is_bool($input['cod_enabled']), 422,
            'Only the nonsecret COD enabled setting is supported.');

        return DB::transaction(function () use ($admin, $input): array {
            $version = (int) DB::table('site_configuration_revisions')->where('domain', self::COD_DOMAIN)
                ->lockForUpdate()->max('version') + 1;
            $id = DB::table('site_configuration_revisions')->insertGetId([
                'domain' => self::COD_DOMAIN, 'version' => $version, 'state' => 'draft',
                'snapshot' => json_encode($input, JSON_THROW_ON_ERROR),
                'created_by_admin_id' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $admin->id, 'website_cod_policy_draft_saved',
                'site_configuration_revision:'.$id);

            return ['id' => $id, 'version' => $version, 'cod_enabled' => $input['cod_enabled']];
        });
    }

    public function publish(IdentityAccount $actor, int $draftId): array
    {
        $admin = $this->authorize($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.publish'), 403);

        return DB::transaction(function () use ($admin, $draftId): array {
            $draft = DB::table('site_configuration_revisions')->where('id', $draftId)
                ->where('domain', self::COD_DOMAIN)->lockForUpdate()->first();
            abort_unless($draft && $draft->state === 'draft', 409, 'COD policy draft not available.');
            $latest = DB::table('site_configuration_revisions')->where('domain', self::COD_DOMAIN)->max('version');
            abort_unless((int) $draft->version === (int) $latest, 409, 'A newer COD policy draft exists.');
            $policy = json_decode($draft->snapshot, true, flags: JSON_THROW_ON_ERROR);
            abort_unless(is_array($policy) && array_keys($policy) === ['cod_enabled']
                && is_bool($policy['cod_enabled']), 409, 'COD policy draft is invalid.');

            DB::table('site_configuration_revisions')->where('domain', self::COD_DOMAIN)
                ->where('state', 'published')->update(['state' => 'superseded', 'updated_at' => now()]);
            DB::table('site_configuration_revisions')->where('id', $draft->id)->update([
                'state' => 'published', 'published_by_admin_id' => $admin->id,
                'published_at' => now(), 'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $admin->id, 'website_cod_policy_published',
                'site_configuration_revision:'.$draft->id);

            return ['id' => (int) $draft->id, 'version' => (int) $draft->version,
                'cod_enabled' => $policy['cod_enabled']];
        });
    }

    private function authorize(IdentityAccount $actor): Admin
    {
        abort_unless($actor instanceof Admin && app(Access::class)->allows($actor, 'website.payments.manage'), 403);

        return $actor;
    }
}
