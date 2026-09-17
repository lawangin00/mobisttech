<?php

namespace App\Addendum;

use Illuminate\Support\Facades\DB;
use LogicException;

/** Authoritative Website capability contract for creation, discovery and historical-access gating. */
final class WebsiteCapabilities
{
    public const PUBLIC_OPERATIONS = [
        'catalogue.read' => 'commerce', 'category.read' => 'commerce', 'compare.read' => 'commerce',
        'cart.write' => 'commerce', 'checkout.create' => 'commerce', 'wishlist.write' => 'commerce', 'review.write' => 'commerce',
        'product-notification.subscribe' => 'commerce', 'service.read' => 'digital', 'enquiry.create' => 'digital',
        'consultation.create' => 'digital', 'case-study.read' => 'digital',
    ];

    public const HISTORICAL_RESOURCES = ['order.status', 'invoice.read', 'project.read', 'approved-milestone.pay', 'delivery.read'];

    public function snapshot(bool $lock = false): array
    {
        if ($lock && DB::transactionLevel() < 1) {
            throw new LogicException('Capability creation checks require the owning transaction.');
        }
        // Authorization always consults the master pointer; public caches are never authority.
        $profile = DB::table('website_operating_profiles')->where('id', 1)->when($lock, fn ($q) => $q->lockForUpdate())->first();
        if ($profile) {
            $revision = DB::table('site_configuration_revisions')->where('id', $profile->revision_id)
                ->when($lock, fn ($q) => $q->lockForUpdate())->first();
            $content = $revision ? json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR) : null;
            if (! $revision || $revision->domain !== 'website.mode' || $revision->state !== 'published'
                || (string) $revision->version !== (string) $profile->version || ($content['mode'] ?? null) !== $profile->mode) {
                throw new LogicException('Published capability pointer and revision disagree.');
            }
        }
        $mode = $profile?->mode;
        $capabilities = [
            'commerce' => in_array($mode, ['hybrid', 'commerce_only'], true),
            'digital' => in_array($mode, ['hybrid', 'digital_only'], true),
        ];

        return [
            'contract' => 'website-capabilities.v1', 'mode' => $mode,
            'version' => (string) ($profile?->version ?? 0), 'capabilities' => $capabilities,
            'cache_namespace' => 'website-mode:'.($profile?->version ?? 0).':'.($mode ?? 'unpublished'),
        ];
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

    public function allowsScope(string $scope): bool
    {
        $snapshot = $this->snapshot();
        if ($snapshot['mode'] === null) {
            return false;
        }
        if ($scope === 'common' || $scope === $snapshot['mode']) {
            return true;
        }
        if (in_array($scope, ['digital', 'commerce'], true)) {
            return (bool) $snapshot['capabilities'][$scope];
        }

        return false;
    }

    public function assertHistoricalAllowed(string $resource): array
    {
        if (! in_array($resource, self::HISTORICAL_RESOURCES, true)) {
            throw new LogicException('Unknown historical Website resource.');
        }

        return $this->snapshot();
    }
}
