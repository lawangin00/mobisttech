<?php

namespace App\Addendum;

use Illuminate\Support\Facades\DB;
use LogicException;

/** Internal v1 contract. Publication, routes and customer ownership checks belong to later points. */
final class WebsiteCapabilities
{
    public const PUBLIC_OPERATIONS = [
        'catalogue.read' => 'commerce', 'category.read' => 'commerce', 'compare.read' => 'commerce',
        'cart.write' => 'commerce', 'checkout.create' => 'commerce', 'wishlist.write' => 'commerce',
        'product-notification.subscribe' => 'commerce', 'service.read' => 'digital', 'enquiry.create' => 'digital',
        'consultation.create' => 'digital', 'case-study.read' => 'digital',
    ];

    public const HISTORICAL_RESOURCES = ['order.status', 'invoice.read', 'project.read', 'approved-milestone.pay', 'delivery.read'];

    public function snapshot(bool $lock = false): array
    {
        if ($lock && DB::transactionLevel() < 1) {
            throw new LogicException('Capability creation checks require the owning transaction.');
        }
        // Never trust client flags or an unversioned cache for authorization.
        $profile = DB::table('website_operating_profiles')->where('id', 1)->when($lock, fn ($q) => $q->lockForUpdate())->first();
        if ($profile) {
            $revision = DB::table('site_configuration_revisions')->where('id', $profile->revision_id)->when($lock, fn ($q) => $q->lockForUpdate())->first();
            $content = $revision ? json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR) : null;
            if (! $revision || $revision->domain !== 'website.mode' || $revision->state !== 'published'
                || (string) $revision->version !== (string) $profile->version || ($content['mode'] ?? null) !== $profile->mode) {
                throw new LogicException('Published capability pointer and revision disagree.');
            }
        }
        $mode = $profile?->mode;
        $capabilities = ['commerce' => in_array($mode, ['hybrid', 'commerce_only'], true), 'digital' => in_array($mode, ['hybrid', 'digital_only'], true)];

        return ['contract' => 'website-capabilities.v1', 'mode' => $mode, 'version' => (string) ($profile?->version ?? 0),
            'capabilities' => $capabilities, 'cache_namespace' => 'website-mode:'.($profile?->version ?? 0).':'.($mode ?? 'unpublished')];
    }

    public function assertCreationAllowed(string $operation): array
    {
        $snapshot = $this->snapshot(true);
        $capability = self::PUBLIC_OPERATIONS[$operation] ?? null;
        if ($capability === null || ! $snapshot['capabilities'][$capability]) {
            throw new LogicException('Unknown or inactive public capability.');
        }

        return $snapshot;
    }
}
