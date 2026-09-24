<?php

namespace App\Cms;

use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Identity\IdentityAudit;
use App\Identity\RealmSessionPolicy;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WebsiteCms
{
    private const PAGE_PURPOSES = ['general', 'homepage', 'catalogue', 'promotion', 'service_landing', 'case_study', 'digital_testimonial', 'faq', 'insight', 'guide', 'about', 'contact'];

    private const CAPABILITY_SCOPES = ['common', 'digital', 'commerce', 'digital_only', 'hybrid', 'commerce_only'];

    private const PROTECTED_ROOTS = ['account', 'admin', 'api', 'cart', 'checkout', 'login', 'order', 'payment', 'software'];

    private const RESERVED_PAGE_ROOTS = ['products', 'categories', 'compare', 'enquiry', 'services', 'reset-password'];

    private const PRESENTATION_PERMISSIONS = [
        'homepage' => 'website.content.manage', 'catalogue' => 'website.content.manage', 'promotion' => 'website.content.manage',
        'navigation' => 'website.navigation.manage', 'seo' => 'website.seo.manage', 'theme' => 'website.theme.manage',
        'branding' => 'website.branding.manage',
    ];

    private const POLICY_TYPES = [
        'privacy' => ['slug' => 'privacy-policy', 'title' => 'Privacy Policy', 'requirement' => 'required', 'footer' => 'privacy'],
        'terms' => ['slug' => 'terms-conditions', 'title' => 'Terms & Conditions', 'requirement' => 'required', 'footer' => 'terms'],
        'returns_refunds' => ['slug' => 'return-refund-policy', 'title' => 'Return & Refund Policy', 'requirement' => 'required', 'footer' => 'returns'],
        'shipping_delivery' => ['slug' => 'shipping-delivery-policy', 'title' => 'Shipping / Delivery Policy', 'requirement' => 'required', 'footer' => 'shipping'],
        'warranty' => ['slug' => 'warranty-policy', 'title' => 'Warranty Policy', 'requirement' => 'required', 'footer' => 'warranty'],
        'cookie' => ['slug' => 'cookie-policy', 'title' => 'Cookie Policy', 'requirement' => 'conditional', 'footer' => 'cookies'],
        'digital_services_terms' => ['slug' => 'digital-services-terms', 'title' => 'Digital Services / Project Terms', 'requirement' => 'conditional', 'footer' => 'digital-terms'],
        'payment_disclosures' => ['slug' => 'payment-disclosures', 'title' => 'Payment Disclosures', 'requirement' => 'conditional', 'footer' => 'payments'],
    ];

    private const RELEASE_IMPACT_KEYS = ['overview', 'privacy', 'terms', 'faq', 'system_requirements', 'support_guidance'];

    private const RELEASE_IMPACT_STATES = ['not_affected', 'reviewed_updated', 'reviewed_no_change'];

    private const MEDIA_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp'],
        'video/mp4' => ['mp4'], 'video/webm' => ['webm'],
    ];

    public function savePresentationDraft(IdentityAccount $actor, array $snapshot): array
    {
        $admin = $this->admin($actor);
        abort_if($snapshot === [] || array_diff(array_keys($snapshot), array_keys(self::PRESENTATION_PERMISSIONS)), 422, 'Unknown Website presentation section.');
        foreach (array_keys($snapshot) as $section) {
            abort_unless(app(Access::class)->allows($admin, self::PRESENTATION_PERMISSIONS[$section]), 403);
        }
        if (array_key_exists('seo', $snapshot)) {
            $snapshot['seo'] = $this->globalSeoSnapshot($snapshot['seo']);
        }
        if (array_key_exists('promotion', $snapshot)) {
            $snapshot['promotion'] = $this->promotionSnapshot($snapshot['promotion']);
        }
        $snapshot = $this->safeTree($snapshot);

        return DB::transaction(function () use ($admin, $snapshot) {
            $version = (int) DB::table('site_configuration_revisions')->where('domain', 'website.presentation')->lockForUpdate()->max('version') + 1;
            $id = DB::table('site_configuration_revisions')->insertGetId([
                'domain' => 'website.presentation', 'version' => $version, 'state' => 'draft',
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'created_by_admin_id' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $admin->id, 'website_presentation_draft_saved', 'site_configuration_revision:'.$id);

            return $this->presentationPayload($id);
        });
    }

    public function publishPresentation(IdentityAccount $actor, int $revisionId): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.publish'), 403);

        return DB::transaction(function () use ($admin, $revisionId) {
            $revision = DB::table('site_configuration_revisions')->where('id', $revisionId)->where('domain', 'website.presentation')->lockForUpdate()->firstOrFail();
            abort_unless($revision->state === 'draft', 409, 'Only a draft presentation revision can be published.');
            $snapshot = json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR);
            $this->applyPresentation($admin, $snapshot);
            DB::table('site_configuration_revisions')->where('domain', 'website.presentation')->where('state', 'published')
                ->update(['state' => 'superseded', 'updated_at' => now()]);
            DB::table('site_configuration_revisions')->where('id', $revisionId)->update([
                'state' => 'published', 'published_by_admin_id' => $admin->id, 'published_at' => now(), 'updated_at' => now(),
            ]);
            $this->bump('cms.presentation');
            IdentityAudit::record('admin', $admin->id, 'website_presentation_published', 'site_configuration_revision:'.$revisionId);

            return $this->presentationPayload($revisionId);
        });
    }

    public function rollbackPresentation(IdentityAccount $actor, int $revisionId): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.publish'), 403);

        return DB::transaction(function () use ($admin, $revisionId) {
            $source = DB::table('site_configuration_revisions')->where('id', $revisionId)->where('domain', 'website.presentation')->firstOrFail();
            abort_unless(in_array($source->state, ['published', 'superseded'], true), 409, 'Rollback requires a previously published presentation revision.');
            $snapshot = json_decode($source->snapshot, true, flags: JSON_THROW_ON_ERROR);
            $version = (int) DB::table('site_configuration_revisions')->where('domain', 'website.presentation')->lockForUpdate()->max('version') + 1;
            $newId = DB::table('site_configuration_revisions')->insertGetId([
                'domain' => 'website.presentation', 'version' => $version, 'state' => 'draft', 'snapshot' => $source->snapshot,
                'created_by_admin_id' => $admin->id, 'restored_from_revision_id' => $source->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->applyPresentation($admin, $snapshot);
            DB::table('site_configuration_revisions')->where('domain', 'website.presentation')->where('state', 'published')
                ->update(['state' => 'superseded', 'updated_at' => now()]);
            DB::table('site_configuration_revisions')->where('id', $newId)->update([
                'state' => 'published', 'published_by_admin_id' => $admin->id, 'published_at' => now(), 'updated_at' => now(),
            ]);
            $this->bump('cms.presentation');
            IdentityAudit::record('admin', $admin->id, 'website_presentation_rolled_back', 'site_configuration_revision:'.$newId);

            return $this->presentationPayload($newId);
        });
    }

    public function registerMedia(IdentityAccount $actor, array $input): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.media.manage'), 403);
        $bytes = $input['bytes'] ?? null;
        abort_unless(is_string($bytes) && $bytes !== '', 422, 'Website media bytes are required.');
        $size = strlen($bytes);
        abort_if($size > 100 * 1024 * 1024, 422, 'Website media is too large.');
        $mime = strtolower((string) ((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: ''));
        $extension = strtolower((string) ($input['extension'] ?? ''));
        abort_unless(isset(self::MEDIA_TYPES[$mime]) && in_array($extension, self::MEDIA_TYPES[$mime], true), 422, 'Unsupported Website media type.');
        $max = str_starts_with($mime, 'video/') ? 100 * 1024 * 1024 : 10 * 1024 * 1024;
        abort_if($size > $max, 422, 'Website media exceeds its type limit.');
        if (str_starts_with($mime, 'image/')) {
            $dimensions = @getimagesizefromstring($bytes);
            abort_unless(is_array($dimensions) && isset($dimensions[0], $dimensions[1]), 422, 'Invalid image bytes.');
            $width = (int) $dimensions[0];
            $height = (int) $dimensions[1];
        } else {
            $width = filter_var($input['width'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 16384]]);
            $height = filter_var($input['height'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 16384]]);
            abort_unless($width && $height, 422, 'Verified video dimensions are required.');
        }
        abort_if($width > 16384 || $height > 16384, 422, 'Website media dimensions are too large.');
        $sha = hash('sha256', $bytes);
        $existing = DB::table('site_media_assets')->where('sha256', $sha)->where('byte_size', $size)
            ->where('mime_type', $mime)->where('disk', 'local')->first();
        if ($existing) {
            return $this->mediaPayload($existing);
        }
        $path = 'cms/'.Str::uuid().'.'.$extension;
        // Draft CMS media must never be reachable through /storage or an eventual storage:link.
        $disk = Storage::disk('local');
        abort_if($disk->exists($path), 500, 'Generated CMS media path already exists.');
        abort_unless($disk->put($path, $bytes, ['visibility' => 'private']), 500, 'CMS media write failed.');
        try {
            abort_unless(hash_equals($sha, hash('sha256', $disk->get($path))), 500, 'CMS media verification failed.');
            $id = DB::table('site_media_assets')->insertGetId([
                'disk' => 'local', 'path' => $path,
                'original_name' => $this->plain((string) ($input['original_name'] ?? ''), 255),
                'mime_type' => $mime, 'extension' => $extension, 'byte_size' => $size,
                'width' => $width, 'height' => $height, 'aspect_ratio' => number_format($width / $height, 6, '.', ''),
                'sha256' => $sha, 'alt_text' => $this->nullablePlain($input['alt_text'] ?? null, 500),
                'status' => 'active', 'uploaded_by_admin_id' => $admin->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $error) {
            $disk->delete($path);
            throw $error;
        }
        $this->bump('cms.media');
        IdentityAudit::record('admin', $admin->id, 'website_media_registered', 'site_media_asset:'.$id);

        return $this->mediaPayload(DB::table('site_media_assets')->where('id', $id)->firstOrFail());
    }

    public function savePageDraft(IdentityAccount $actor, ?string $publicId, array $input): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.content.manage'), 403);
        $snapshot = $this->pageSnapshot($input);

        return DB::transaction(function () use ($admin, $publicId, $snapshot) {
            $page = $publicId ? DB::table('site_managed_pages')->where('public_id', $publicId)->lockForUpdate()->firstOrFail() : null;
            if ($page && ($page->protected_slug || $page->publish_state === 'published') && $page->slug !== $snapshot['slug']) {
                throw ValidationException::withMessages(['slug' => 'Published or protected page slugs cannot be changed.']);
            }
            if (! $page) {
                $id = DB::table('site_managed_pages')->insertGetId($this->pageProjection($snapshot, $admin, false));
                $page = DB::table('site_managed_pages')->where('id', $id)->lockForUpdate()->firstOrFail();
            }
            $version = (int) DB::table('site_page_revisions')->where('site_managed_page_id', $page->id)->max('version') + 1;
            $revisionId = DB::table('site_page_revisions')->insertGetId([
                'site_managed_page_id' => $page->id, 'version' => $version, 'state' => 'draft',
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'snapshot_sha256' => $this->hash($snapshot), 'created_by_admin_id' => $admin->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($page->publish_state !== 'published') {
                DB::table('site_managed_pages')->where('id', $page->id)->update($this->pageProjection($snapshot, $admin, true));
            }
            IdentityAudit::record('admin', $admin->id, 'website_page_draft_saved', 'site_page_revision:'.$revisionId);

            return $this->pageRevisionPayload($revisionId);
        });
    }

    public function publishPage(IdentityAccount $actor, int $revisionId): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.publish'), 403);

        return DB::transaction(function () use ($admin, $revisionId) {
            $revision = DB::table('site_page_revisions')->where('id', $revisionId)->lockForUpdate()->firstOrFail();
            abort_unless($revision->state === 'draft', 409, 'Only a draft page revision can be published.');
            $page = DB::table('site_managed_pages')->where('id', $revision->site_managed_page_id)->lockForUpdate()->firstOrFail();
            $snapshot = json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR);
            $collision = DB::table('site_managed_pages')->where('slug', $snapshot['slug'])->where('id', '<>', $page->id)->exists();
            abort_if($collision, 409, 'Page slug is already in use.');
            DB::table('site_page_revisions')->where('site_managed_page_id', $page->id)->where('state', 'published')
                ->update(['state' => 'superseded', 'updated_at' => now()]);
            DB::table('site_page_revisions')->where('id', $revisionId)->update([
                'state' => 'published', 'published_by_admin_id' => $admin->id, 'published_at' => now(), 'updated_at' => now(),
            ]);
            $projection = $this->pageProjection($snapshot, $admin, true);
            $projection['publish_state'] = 'published';
            $projection['published_at'] = now();
            $projection['current_revision_id'] = $revisionId;
            $projection['version'] = (int) $page->version + 1;
            DB::table('site_managed_pages')->where('id', $page->id)->update($projection);
            $this->syncPageServices((int) $page->id, $snapshot['service_slugs'] ?? []);
            $this->bump('cms.pages');
            IdentityAudit::record('admin', $admin->id, 'website_page_published', 'site_page_revision:'.$revisionId);

            return $this->pageRevisionPayload($revisionId);
        });
    }

    public function rollbackPage(IdentityAccount $actor, int $revisionId): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.publish'), 403);

        return DB::transaction(function () use ($admin, $revisionId) {
            $source = DB::table('site_page_revisions')->where('id', $revisionId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($source->state, ['published', 'superseded'], true), 409, 'Rollback requires published page history.');
            $page = DB::table('site_managed_pages')->where('id', $source->site_managed_page_id)->lockForUpdate()->firstOrFail();
            $version = (int) DB::table('site_page_revisions')->where('site_managed_page_id', $page->id)->max('version') + 1;
            $newId = DB::table('site_page_revisions')->insertGetId([
                'site_managed_page_id' => $page->id, 'version' => $version, 'state' => 'draft', 'snapshot' => $source->snapshot,
                'snapshot_sha256' => $source->snapshot_sha256, 'created_by_admin_id' => $admin->id,
                'restored_from_revision_id' => $source->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $snapshot = json_decode($source->snapshot, true, flags: JSON_THROW_ON_ERROR);
            DB::table('site_page_revisions')->where('site_managed_page_id', $page->id)->where('state', 'published')
                ->update(['state' => 'superseded', 'updated_at' => now()]);
            DB::table('site_page_revisions')->where('id', $newId)->update([
                'state' => 'published', 'published_by_admin_id' => $admin->id, 'published_at' => now(), 'updated_at' => now(),
            ]);
            $projection = $this->pageProjection($snapshot, $admin, true);
            $projection['publish_state'] = 'published';
            $projection['published_at'] = now();
            $projection['current_revision_id'] = $newId;
            $projection['version'] = (int) $page->version + 1;
            DB::table('site_managed_pages')->where('id', $page->id)->update($projection);
            $this->syncPageServices((int) $page->id, $snapshot['service_slugs'] ?? []);
            $this->bump('cms.pages');
            IdentityAudit::record('admin', $admin->id, 'website_page_rolled_back', 'site_page_revision:'.$newId);

            return $this->pageRevisionPayload($newId);
        });
    }

    public function savePolicyDraft(IdentityAccount $actor, string $policyType, array $input): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.content.manage'), 403);
        abort_unless(isset(self::POLICY_TYPES[$policyType]), 422, 'Unknown policy type.');
        $meta = self::POLICY_TYPES[$policyType];
        $content = $this->rich((string) ($input['content'] ?? ''), 50000);
        $effectiveDate = $this->dateOrNull($input['effective_date'] ?? null);
        $approval = (string) ($input['approval_state'] ?? 'draft');
        $factual = (string) ($input['factual_review_state'] ?? 'pending');
        abort_unless(in_array($approval, ['draft', 'owner_approved'], true), 422, 'Invalid policy approval state.');
        abort_unless(in_array($factual, ['pending', 'verified'], true), 422, 'Invalid factual review state.');
        if ($approval === 'owner_approved' || $factual === 'verified') {
            abort_unless(app(Access::class)->allows($admin, 'website.publish'), 403, 'Only a Website publisher may record policy approval/review completion.');
        }
        $decisions = $this->plainList($input['unresolved_decisions'] ?? [], 20, 500);
        $applicability = $meta['requirement'] === 'required' ? 'required' : (string) ($input['applicability_state'] ?? 'undecided');
        abort_unless(in_array($applicability, ['required', 'applicable', 'not_required', 'undecided'], true), 422, 'Invalid policy applicability state.');
        if ($applicability !== 'not_required') {
            abort_if($content === '', 422, 'Applicable policy content is required.');
        }

        return DB::transaction(function () use ($admin, $policyType, $meta, $input, $content, $effectiveDate, $approval, $factual, $decisions, $applicability) {
            $policy = DB::table('cms_policies')->where('policy_type', $policyType)->lockForUpdate()->first();
            if (! $policy) {
                $id = DB::table('cms_policies')->insertGetId([
                    'public_id' => (string) Str::uuid(), 'policy_type' => $policyType, 'slug' => $meta['slug'], 'title' => $meta['title'],
                    'requirement_state' => $meta['requirement'], 'applicability_state' => $applicability,
                    'footer_destination' => $applicability === 'not_required' ? null : $meta['footer'],
                    'approval_state' => 'draft', 'factual_review_state' => 'pending', 'protected_slug' => true,
                    'created_by_admin_id' => $admin->id, 'updated_by_admin_id' => $admin->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $policy = DB::table('cms_policies')->where('id', $id)->lockForUpdate()->firstOrFail();
            } else {
                abort_unless($policy->slug === $meta['slug'] && $policy->protected_slug, 409, 'Protected policy route identity changed unexpectedly.');
                DB::table('cms_policies')->where('id', $policy->id)->update([
                    'applicability_state' => $applicability,
                    'footer_destination' => $applicability === 'not_required' ? null : $meta['footer'],
                    'updated_by_admin_id' => $admin->id, 'updated_at' => now(),
                ]);
            }
            $version = (int) DB::table('cms_policy_revisions')->where('cms_policy_id', $policy->id)->max('version') + 1;
            $revisionId = DB::table('cms_policy_revisions')->insertGetId([
                'cms_policy_id' => $policy->id, 'version' => $version, 'state' => 'draft', 'content' => $content,
                'content_sha256' => hash('sha256', $content), 'effective_date' => $effectiveDate,
                'approval_state' => $approval, 'factual_review_state' => $factual,
                'unresolved_decisions' => $decisions === [] ? null : json_encode($decisions, JSON_THROW_ON_ERROR),
                'professional_review_reference' => $this->nullablePlain($input['professional_review_reference'] ?? null, 255),
                'review_notes' => $this->nullablePlain($input['review_notes'] ?? null, 2000),
                'created_by_admin_id' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $admin->id, 'website_policy_draft_saved', 'cms_policy_revision:'.$revisionId);

            return $this->policyRevisionPayload($revisionId);
        });
    }

    public function publishPolicy(IdentityAccount $actor, int $revisionId, Request $request): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.publish'), 403);
        abort_unless($request->hasSession() && app(RealmSessionPolicy::class)->recentlyAuthenticated($request), 403, 'Recent authentication is required to publish legal policy content.');

        return DB::transaction(function () use ($admin, $revisionId) {
            $revision = DB::table('cms_policy_revisions')->where('id', $revisionId)->lockForUpdate()->firstOrFail();
            abort_unless($revision->state === 'draft', 409, 'Only a draft policy revision can be published.');
            $policy = DB::table('cms_policies')->where('id', $revision->cms_policy_id)->lockForUpdate()->firstOrFail();
            abort_unless($revision->approval_state === 'owner_approved', 422, 'Business owner approval is required.');
            abort_unless($revision->factual_review_state === 'verified', 422, 'Technical/data-flow factual review is required.');
            $unresolved = $revision->unresolved_decisions ? json_decode($revision->unresolved_decisions, true, flags: JSON_THROW_ON_ERROR) : [];
            abort_if($unresolved !== [], 422, 'Unresolved policy decisions must remain explicit and block publication.');
            abort_if($revision->effective_date === null, 422, 'A policy effective date is required before publication.');
            if ($policy->applicability_state !== 'not_required') {
                abort_if(trim((string) $revision->content) === '', 422, 'Applicable policy content cannot be empty.');
            }
            DB::table('cms_policy_revisions')->where('cms_policy_id', $policy->id)->where('state', 'published')
                ->update(['state' => 'superseded', 'updated_at' => now()]);
            DB::table('cms_policy_revisions')->where('id', $revisionId)->update([
                'state' => 'published', 'published_by_admin_id' => $admin->id, 'published_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('cms_policies')->where('id', $policy->id)->update([
                'current_revision_id' => $revisionId, 'approval_state' => $revision->approval_state,
                'factual_review_state' => $revision->factual_review_state, 'effective_date' => $revision->effective_date,
                'updated_by_admin_id' => $admin->id, 'updated_at' => now(),
            ]);
            $this->bump('cms.policies');
            IdentityAudit::record('admin', $admin->id, 'website_policy_published', 'cms_policy_revision:'.$revisionId);

            return $this->policyRevisionPayload($revisionId);
        });
    }

    public function rollbackPolicy(IdentityAccount $actor, int $revisionId, Request $request): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.publish'), 403);
        abort_unless($request->hasSession() && app(RealmSessionPolicy::class)->recentlyAuthenticated($request), 403, 'Recent authentication is required to roll back legal policy content.');

        return DB::transaction(function () use ($admin, $revisionId) {
            $source = DB::table('cms_policy_revisions')->where('id', $revisionId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($source->state, ['published', 'superseded'], true), 409, 'Rollback requires published policy history.');
            $policy = DB::table('cms_policies')->where('id', $source->cms_policy_id)->lockForUpdate()->firstOrFail();
            $version = (int) DB::table('cms_policy_revisions')->where('cms_policy_id', $policy->id)->max('version') + 1;
            $newId = DB::table('cms_policy_revisions')->insertGetId([
                'cms_policy_id' => $policy->id, 'version' => $version, 'state' => 'draft', 'content' => $source->content,
                'content_sha256' => $source->content_sha256, 'effective_date' => $source->effective_date,
                'approval_state' => $source->approval_state, 'factual_review_state' => $source->factual_review_state,
                'unresolved_decisions' => $source->unresolved_decisions,
                'professional_review_reference' => $source->professional_review_reference, 'review_notes' => $source->review_notes,
                'created_by_admin_id' => $admin->id, 'restored_from_revision_id' => $source->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('cms_policy_revisions')->where('cms_policy_id', $policy->id)->where('state', 'published')
                ->update(['state' => 'superseded', 'updated_at' => now()]);
            DB::table('cms_policy_revisions')->where('id', $newId)->update([
                'state' => 'published', 'published_by_admin_id' => $admin->id, 'published_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('cms_policies')->where('id', $policy->id)->update([
                'current_revision_id' => $newId, 'approval_state' => $source->approval_state,
                'factual_review_state' => $source->factual_review_state, 'effective_date' => $source->effective_date,
                'updated_by_admin_id' => $admin->id, 'updated_at' => now(),
            ]);
            $this->bump('cms.policies');
            IdentityAudit::record('admin', $admin->id, 'website_policy_rolled_back', 'cms_policy_revision:'.$newId);

            return $this->policyRevisionPayload($newId);
        });
    }

    public function saveSoftwareDraft(IdentityAccount $actor, ?string $publicId, array $input): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.content.manage'), 403);
        $snapshot = $this->softwareSnapshot($input);

        return DB::transaction(function () use ($admin, $publicId, $snapshot) {
            $product = $publicId ? DB::table('software_products')->where('public_id', $publicId)->lockForUpdate()->firstOrFail() : null;
            if ($product && ($product->protected_slug || $product->lifecycle_state === 'published') && $product->slug !== $snapshot['slug']) {
                throw ValidationException::withMessages(['slug' => 'Published software slugs are protected. Use the explicit canonical-route change workflow.']);
            }
            if (! $product) {
                abort_if(DB::table('software_products')->where('slug', $snapshot['slug'])->exists(), 409, 'Software slug is already in use.');
                $id = DB::table('software_products')->insertGetId([
                    'public_id' => (string) Str::uuid(), 'name' => $snapshot['name'], 'slug' => $snapshot['slug'],
                    'lifecycle_state' => 'draft', 'protected_slug' => false,
                    'created_by_admin_id' => $admin->id, 'updated_by_admin_id' => $admin->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $product = DB::table('software_products')->where('id', $id)->lockForUpdate()->firstOrFail();
            }
            $revisionNo = (int) DB::table('software_product_revisions')->where('software_product_id', $product->id)->max('revision_no') + 1;
            $revisionId = DB::table('software_product_revisions')->insertGetId([
                'software_product_id' => $product->id, 'revision_no' => $revisionNo, 'state' => 'draft',
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'snapshot_sha256' => $this->hash($snapshot), 'created_by_admin_id' => $admin->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($product->lifecycle_state !== 'published') {
                DB::table('software_products')->where('id', $product->id)->update([
                    'name' => $snapshot['name'], 'slug' => $snapshot['slug'], 'updated_by_admin_id' => $admin->id, 'updated_at' => now(),
                ]);
            }
            IdentityAudit::record('admin', $admin->id, 'software_draft_saved', 'software_product_revision:'.$revisionId);

            return $this->softwareRevisionPayload($revisionId);
        });
    }

    public function publishSoftware(IdentityAccount $actor, int $revisionId): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.publish'), 403);

        return DB::transaction(function () use ($admin, $revisionId) {
            $revision = DB::table('software_product_revisions')->where('id', $revisionId)->lockForUpdate()->firstOrFail();
            abort_unless($revision->state === 'draft', 409, 'Only a draft software revision can be published.');
            $product = DB::table('software_products')->where('id', $revision->software_product_id)->lockForUpdate()->firstOrFail();
            $snapshot = json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR);
            foreach (['name', 'summary', 'overview', 'privacy', 'terms'] as $required) {
                abort_if(trim((string) ($snapshot[$required] ?? '')) === '', 422, 'Software publication requires complete Overview/Privacy/Terms content.');
            }
            abort_if(($snapshot['faq'] ?? []) === [], 422, 'Software publication requires product-specific FAQ content.');
            abort_if(DB::table('software_products')->where('slug', $snapshot['slug'])->where('id', '<>', $product->id)->exists(), 409, 'Software slug is already in use.');
            if ($product->protected_slug && $product->slug !== $snapshot['slug']) {
                throw ValidationException::withMessages(['slug' => 'Protected software slug cannot be silently replaced.']);
            }
            DB::table('software_product_revisions')->where('software_product_id', $product->id)->where('state', 'published')
                ->update(['state' => 'superseded', 'updated_at' => now()]);
            DB::table('software_product_revisions')->where('id', $revisionId)->update([
                'state' => 'published', 'published_by_admin_id' => $admin->id, 'published_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('software_products')->where('id', $product->id)->update([
                'name' => $snapshot['name'], 'slug' => $snapshot['slug'], 'lifecycle_state' => 'published', 'protected_slug' => true,
                'current_revision_id' => $revisionId, 'updated_by_admin_id' => $admin->id, 'archived_at' => null, 'updated_at' => now(),
            ]);
            $this->bump('cms.software');
            IdentityAudit::record('admin', $admin->id, 'software_published', 'software_product_revision:'.$revisionId);

            return $this->softwareRevisionPayload($revisionId);
        });
    }

    public function rollbackSoftware(IdentityAccount $actor, int $revisionId): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.publish'), 403);

        return DB::transaction(function () use ($admin, $revisionId) {
            $source = DB::table('software_product_revisions')->where('id', $revisionId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($source->state, ['published', 'superseded'], true), 409, 'Rollback requires published software history.');
            $product = DB::table('software_products')->where('id', $source->software_product_id)->lockForUpdate()->firstOrFail();
            $snapshot = json_decode($source->snapshot, true, flags: JSON_THROW_ON_ERROR);
            $snapshot['slug'] = $product->slug;
            $snapshotJson = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $revisionNo = (int) DB::table('software_product_revisions')->where('software_product_id', $product->id)->max('revision_no') + 1;
            $newId = DB::table('software_product_revisions')->insertGetId([
                'software_product_id' => $product->id, 'revision_no' => $revisionNo, 'state' => 'draft',
                'snapshot' => $snapshotJson, 'snapshot_sha256' => $this->hash($snapshot),
                'created_by_admin_id' => $admin->id, 'restored_from_revision_id' => $source->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('software_product_revisions')->where('software_product_id', $product->id)->where('state', 'published')
                ->update(['state' => 'superseded', 'updated_at' => now()]);
            DB::table('software_product_revisions')->where('id', $newId)->update([
                'state' => 'published', 'published_by_admin_id' => $admin->id, 'published_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('software_products')->where('id', $product->id)->update([
                'name' => $snapshot['name'], 'current_revision_id' => $newId, 'lifecycle_state' => 'published',
                'updated_by_admin_id' => $admin->id, 'archived_at' => null, 'updated_at' => now(),
            ]);
            $this->bump('cms.software');
            IdentityAudit::record('admin', $admin->id, 'software_rolled_back', 'software_product_revision:'.$newId);

            return $this->softwareRevisionPayload($newId);
        });
    }

    public function saveReleaseDraft(IdentityAccount $actor, string $softwarePublicId, array $input): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.content.manage'), 403);
        $product = DB::table('software_products')->where('public_id', $softwarePublicId)->firstOrFail();
        $version = $this->plain((string) ($input['version'] ?? ''), 80);
        abort_if($version === '', 422, 'Release version is required.');
        $releaseDate = $this->dateOrNull($input['release_date'] ?? null);
        abort_if($releaseDate === null, 422, 'Release date is required.');
        $summary = $this->plain((string) ($input['summary'] ?? ''), 4000);
        abort_if($summary === '', 422, 'Release summary is required.');
        $notes = $this->releaseNotes($input['notes'] ?? []);
        $impact = $this->releaseImpact($input['impact_review'] ?? []);
        abort_if(DB::table('software_releases')->where('software_product_id', $product->id)->where('version', $version)->exists(), 409, 'Release version already exists.');
        $snapshot = ['software_product_id' => $product->id, 'version' => $version, 'release_date' => $releaseDate,
            'summary' => $summary, 'notes' => $notes, 'impact_review' => $impact];
        $id = DB::table('software_releases')->insertGetId([
            'public_id' => (string) Str::uuid(), 'software_product_id' => $product->id, 'version' => $version,
            'release_date' => $releaseDate, 'state' => 'draft', 'summary' => $summary,
            'notes' => json_encode($notes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'impact_review' => json_encode($impact, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'snapshot_sha256' => $this->hash($snapshot), 'created_by_admin_id' => $admin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        IdentityAudit::record('admin', $admin->id, 'software_release_draft_saved', 'software_release:'.$id);

        return $this->releasePayload($id);
    }

    public function publishRelease(IdentityAccount $actor, int $releaseId): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.publish'), 403);

        return DB::transaction(function () use ($admin, $releaseId) {
            $release = DB::table('software_releases')->where('id', $releaseId)->lockForUpdate()->firstOrFail();
            abort_unless($release->state === 'draft', 409, 'Only a draft release can be published.');
            $product = DB::table('software_products')->where('id', $release->software_product_id)->lockForUpdate()->firstOrFail();
            abort_unless($product->lifecycle_state === 'published' && $product->current_revision_id, 409, 'Software overview must be published before a release.');
            $impact = json_decode($release->impact_review, true, flags: JSON_THROW_ON_ERROR);
            foreach (self::RELEASE_IMPACT_KEYS as $key) {
                abort_unless(isset($impact[$key]) && in_array($impact[$key], self::RELEASE_IMPACT_STATES, true), 422, 'Release documentation-impact review is incomplete.');
            }
            foreach ($impact['_material'] ?? [] as $key) {
                abort_if(($impact[$key] ?? 'not_affected') === 'not_affected', 422, 'Material release impact review cannot be bypassed.');
            }
            DB::table('software_releases')->where('id', $releaseId)->update([
                'state' => 'published', 'published_by_admin_id' => $admin->id, 'published_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('software_products')->where('id', $product->id)->update([
                'current_release_id' => $releaseId, 'updated_by_admin_id' => $admin->id, 'updated_at' => now(),
            ]);
            $this->bump('cms.software');
            IdentityAudit::record('admin', $admin->id, 'software_release_published', 'software_release:'.$releaseId);

            return $this->releasePayload($releaseId);
        });
    }

    public function archiveSoftware(IdentityAccount $actor, string $softwarePublicId): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.publish'), 403);
        $product = DB::table('software_products')->where('public_id', $softwarePublicId)->firstOrFail();
        DB::table('software_products')->where('id', $product->id)->update([
            'lifecycle_state' => 'archived', 'updated_by_admin_id' => $admin->id, 'archived_at' => now(), 'updated_at' => now(),
        ]);
        $this->bump('cms.software');
        IdentityAudit::record('admin', $admin->id, 'software_archived', 'software_product:'.$product->id);

        return $this->softwareProductPayload((int) $product->id);
    }

    public function changeSoftwareSlug(IdentityAccount $actor, string $softwarePublicId, string $newSlug, string $reason): array
    {
        $admin = $this->admin($actor);
        abort_unless(app(Access::class)->allows($admin, 'website.publish'), 403);
        $newSlug = $this->slug($newSlug);
        $reason = $this->plain($reason, 255);
        abort_if($reason === '', 422, 'Canonical-route change reason is required.');

        return DB::transaction(function () use ($admin, $softwarePublicId, $newSlug, $reason) {
            $product = DB::table('software_products')->where('public_id', $softwarePublicId)->lockForUpdate()->firstOrFail();
            abort_unless($product->lifecycle_state === 'published' && $product->protected_slug, 409, 'Only a published protected software route may use this workflow.');
            abort_if($product->slug === $newSlug, 422, 'New software slug must differ from the current slug.');
            abort_if(DB::table('software_products')->where('slug', $newSlug)->exists(), 409, 'Software slug is already in use.');
            $oldSlug = $product->slug;
            $routes = ['', '/privacy', '/terms', '/faq', '/releases'];
            foreach (DB::table('software_releases')->where('software_product_id', $product->id)->where('state', 'published')->pluck('version') as $version) {
                $routes[] = '/releases/'.rawurlencode((string) $version);
            }
            foreach ($routes as $suffix) {
                DB::table('cms_route_redirects')->insert([
                    'from_path' => '/software/'.$oldSlug.$suffix, 'to_path' => '/software/'.$newSlug.$suffix,
                    'software_product_id' => $product->id, 'reason' => $reason,
                    'created_by_admin_id' => $admin->id, 'created_at' => now(),
                ]);
            }
            $current = DB::table('software_product_revisions')->where('id', $product->current_revision_id)->lockForUpdate()->firstOrFail();
            $snapshot = json_decode($current->snapshot, true, flags: JSON_THROW_ON_ERROR);
            $snapshot['slug'] = $newSlug;
            $revisionNo = (int) DB::table('software_product_revisions')->where('software_product_id', $product->id)->max('revision_no') + 1;
            $newRevisionId = DB::table('software_product_revisions')->insertGetId([
                'software_product_id' => $product->id, 'revision_no' => $revisionNo, 'state' => 'published',
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'snapshot_sha256' => $this->hash($snapshot), 'created_by_admin_id' => $admin->id,
                'published_by_admin_id' => $admin->id, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('software_product_revisions')->where('id', $current->id)->update(['state' => 'superseded', 'updated_at' => now()]);
            DB::table('software_products')->where('id', $product->id)->update([
                'slug' => $newSlug, 'current_revision_id' => $newRevisionId, 'updated_by_admin_id' => $admin->id, 'updated_at' => now(),
            ]);
            $this->bump('cms.software');
            IdentityAudit::record('admin', $admin->id, 'software_slug_changed', 'software_product:'.$product->id);

            return $this->softwareProductPayload((int) $product->id);
        });
    }

    public function publicPage(string $slug): array
    {
        $page = DB::table('site_managed_pages')->where('slug', $this->slug($slug))->where('publish_state', 'published')->firstOrFail();
        abort_unless($page->current_revision_id, 404);
        $revision = DB::table('site_page_revisions')->where('id', $page->current_revision_id)->where('state', 'published')->firstOrFail();
        $snapshot = json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR);

        return ['public_id' => $page->public_id, 'version' => (int) $revision->version, 'published_at' => $revision->published_at,
            'snapshot' => $snapshot, 'sha256' => $revision->snapshot_sha256];
    }

    public function publicPolicies(): array
    {
        return DB::table('cms_policies')->whereNotNull('current_revision_id')->where('applicability_state', '<>', 'not_required')->orderBy('policy_type')
            ->get()->map(function ($policy) {
                $revision = DB::table('cms_policy_revisions')->where('id', $policy->current_revision_id)->where('state', 'published')->firstOrFail();

                return ['type' => $policy->policy_type, 'slug' => $policy->slug, 'title' => $policy->title,
                    'footer_destination' => $policy->footer_destination, 'effective_date' => (string) $revision->effective_date,
                    'version' => (int) $revision->version, 'content' => $revision->content, 'content_sha256' => $revision->content_sha256];
            })->all();
    }

    public function publicSoftware(string $slug): array
    {
        $product = DB::table('software_products')->where('slug', $this->slug($slug))->where('lifecycle_state', 'published')->firstOrFail();
        abort_unless($product->current_revision_id, 404);
        $revision = DB::table('software_product_revisions')->where('id', $product->current_revision_id)->where('state', 'published')->firstOrFail();
        $snapshot = json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $releases = DB::table('software_releases')->where('software_product_id', $product->id)->where('state', 'published')
            ->orderByDesc('release_date')->orderByDesc('id')->get()->map(fn ($release) => [
                'public_id' => $release->public_id, 'version' => $release->version, 'release_date' => (string) $release->release_date,
                'summary' => $release->summary, 'notes' => json_decode($release->notes, true, flags: JSON_THROW_ON_ERROR),
                'sha256' => $release->snapshot_sha256,
            ])->all();

        return ['public_id' => $product->public_id, 'slug' => $product->slug, 'revision' => (int) $revision->revision_no,
            'published_at' => $revision->published_at, 'snapshot' => $snapshot, 'sha256' => $revision->snapshot_sha256,
            'current_version' => $product->current_release_id ? DB::table('software_releases')->where('id', $product->current_release_id)->value('version') : null,
            'releases' => $releases, 'routes' => $this->softwareRoutes($product->slug)];
    }

    public function resolveRedirect(string $path): ?string
    {
        $path = '/'.ltrim(trim($path), '/');
        abort_if(strlen($path) > 500 || str_contains($path, '..'), 422, 'Invalid route path.');

        return DB::table('cms_route_redirects')->where('from_path', $path)->value('to_path');
    }

    private function admin(IdentityAccount $actor): Admin
    {
        abort_unless($actor instanceof Admin && $actor->usable(), 403);

        return $actor;
    }

    private function presentationPayload(int $id): array
    {
        $row = DB::table('site_configuration_revisions')->where('id', $id)->firstOrFail();

        return ['id' => $row->id, 'version' => (int) $row->version, 'state' => $row->state,
            'snapshot' => json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR), 'published_at' => $row->published_at];
    }

    private function applyPresentation(Admin $admin, array $snapshot): void
    {
        foreach ($snapshot as $section => $value) {
            DB::table('site_settings')->updateOrInsert(['key' => 'cms.presentation.'.$section], [
                'value' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'group' => 'presentation', 'label' => 'Website '.str_replace('_', ' ', ucfirst($section)),
                'sort_order' => array_search($section, array_keys(self::PRESENTATION_PERMISSIONS), true) ?: 0,
                'updated_by_admin_id' => $admin->id, 'updated_at' => now(),
            ]);
        }
        if (array_key_exists('navigation', $snapshot)) {
            $this->applyNavigation($admin, $snapshot['navigation']);
        }
    }

    private function globalSeoSnapshot(mixed $input): array
    {
        abort_unless(is_array($input) && ! array_is_list($input)
            && array_diff(array_keys($input), ['title', 'description', 'social_title', 'social_description', 'canonical_url']) === [],
            422, 'Invalid global Website SEO settings.');
        $canonical = $input['canonical_url'] ?? null;
        abort_unless($canonical === null || $canonical === '' || $canonical === '/', 422,
            'The homepage canonical URL must use the published Website root.');

        return [
            'title' => $this->nullablePlain($input['title'] ?? null, 190),
            'description' => $this->nullablePlain($input['description'] ?? null, 320),
            'social_title' => $this->nullablePlain($input['social_title'] ?? null, 190),
            'social_description' => $this->nullablePlain($input['social_description'] ?? null, 320),
            'canonical_url' => $canonical === '/' ? '/' : null,
        ];
    }

    private function promotionSnapshot(mixed $input): array
    {
        abort_unless(is_array($input) && ! array_is_list($input)
            && array_diff(array_keys($input), ['announcement', 'banners']) === [], 422, 'Invalid Website promotion layout.');
        $banner = function (mixed $row, bool $announcement): array {
            abort_unless(is_array($row) && ! array_is_list($row), 422, 'Promotion entry must be an object.');
            $keys = $announcement ? ['text', 'href', 'scope'] : ['title', 'body', 'href', 'scope'];
            abort_unless(array_diff(array_keys($row), $keys) === [], 422, 'Unknown promotion field.');
            $scope = (string) ($row['scope'] ?? 'common');
            abort_unless(in_array($scope, self::CAPABILITY_SCOPES, true), 422, 'Invalid promotion capability.');
            $href = $this->nullableUrl($row['href'] ?? null);
            if ($href !== null) {
                abort_unless(str_starts_with($href, '/') || str_starts_with($href, 'https://'), 422, 'Public promotion links require HTTPS.');
                if (str_starts_with($href, '/')) {
                    abort_unless(preg_match('/\A\/[a-z0-9]+(?:[\/-][a-z0-9]+)*\z/', $href)
                        && ! in_array(explode('/', trim($href, '/'))[0], self::PROTECTED_ROOTS, true), 422, 'Promotion cannot link to private application routes.');
                }
            }
            if ($announcement) {
                $text = $this->plain((string) ($row['text'] ?? ''), 280);
                abort_if($text === '', 422, 'Promotion announcement text is required.');

                return compact('text', 'href', 'scope');
            }
            $title = $this->plain((string) ($row['title'] ?? ''), 150);
            $body = $this->plain((string) ($row['body'] ?? ''), 500);
            abort_if($title === '', 422, 'Promotion banner title is required.');

            return compact('title', 'body', 'href', 'scope');
        };
        $items = $input['banners'] ?? [];
        abort_unless(is_array($items) && array_is_list($items) && count($items) <= 6, 422, 'Too many promotion banners.');

        return [
            'announcement' => isset($input['announcement']) ? $banner($input['announcement'], true) : null,
            'banners' => array_map(fn ($item) => $banner($item, false), $items),
        ];
    }

    private function applyNavigation(Admin $admin, mixed $raw): void
    {
        abort_unless(is_array($raw) && array_is_list($raw), 422, 'Navigation must be a list.');
        $items = [];
        foreach ($raw as $entry) {
            abort_unless(is_array($entry), 422, 'Invalid navigation item.');
            $key = $this->key((string) ($entry['key'] ?? ''), 80);
            abort_if(isset($items[$key]), 422, 'Navigation keys must be unique.');
            $scope = (string) ($entry['capability_scope'] ?? 'common');
            abort_unless(in_array($scope, self::CAPABILITY_SCOPES, true), 422, 'Invalid navigation capability scope.');
            $type = (string) ($entry['destination_type'] ?? 'page');
            abort_unless(in_array($type, ['page', 'route', 'url'], true), 422, 'Invalid navigation destination type.');
            $items[$key] = ['key' => $key, 'parent_key' => $entry['parent_key'] ?? null,
                'label' => $this->plain((string) ($entry['label'] ?? ''), 120), 'destination_type' => $type,
                'destination_key' => $this->nullableKey($entry['destination_key'] ?? null, 100),
                'destination_payload' => isset($entry['destination_payload']) ? json_encode($this->safeTree($entry['destination_payload']), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) : null,
                'sort_order' => max(0, min(10000, (int) ($entry['sort_order'] ?? 0))),
                'is_visible' => (bool) ($entry['is_visible'] ?? true), 'is_enabled' => (bool) ($entry['is_enabled'] ?? true),
                'target_behavior' => in_array(($entry['target_behavior'] ?? 'same_tab'), ['same_tab', 'new_tab'], true) ? ($entry['target_behavior'] ?? 'same_tab') : 'same_tab',
                'capability_scope' => $scope];
        }
        // Reject transitive loops BEFORE touching published navigation rows.
        foreach ($items as $key => $item) {
            $ancestors = [$key => true];
            $parent = $item['parent_key'];
            while ($parent !== null && $parent !== '') {
                $parent = $this->key((string) $parent, 80);
                abort_unless(isset($items[$parent]) && ! isset($ancestors[$parent]), 422, 'Navigation contains an invalid parent cycle.');
                $ancestors[$parent] = true;
                $parent = $items[$parent]['parent_key'];
            }
        }
        DB::table('site_navigation_items')->whereNotNull('created_by_admin_id')->whereNotIn('key', array_keys($items))
            ->update(['is_visible' => false, 'is_enabled' => false, 'updated_by_admin_id' => $admin->id, 'updated_at' => now()]);
        foreach ($items as $key => $item) {
            DB::table('site_navigation_items')->updateOrInsert(['key' => $key], [
                'parent_id' => null, 'label' => $item['label'], 'destination_type' => $item['destination_type'],
                'destination_key' => $item['destination_key'], 'destination_payload' => $item['destination_payload'],
                'sort_order' => $item['sort_order'], 'is_visible' => $item['is_visible'], 'is_enabled' => $item['is_enabled'],
                'target_behavior' => $item['target_behavior'], 'capability_scope' => $item['capability_scope'],
                'created_by_admin_id' => DB::raw('COALESCE(created_by_admin_id, '.(int) $admin->id.')'),
                'updated_by_admin_id' => $admin->id, 'updated_at' => now(),
            ]);
        }
        foreach ($items as $key => $item) {
            $parent = $item['parent_key'];
            if ($parent === null || $parent === '') {
                continue;
            }
            $parentKey = $this->key((string) $parent, 80);
            abort_unless(isset($items[$parentKey]) && $parentKey !== $key, 422, 'Navigation parent must reference another item in the same revision.');
            $parentId = DB::table('site_navigation_items')->where('key', $parentKey)->value('id');
            DB::table('site_navigation_items')->where('key', $key)->update(['parent_id' => $parentId, 'updated_at' => now()]);
        }
    }

    private function pageSnapshot(array $input): array
    {
        $purpose = (string) ($input['content_purpose'] ?? 'general');
        abort_unless(in_array($purpose, self::PAGE_PURPOSES, true), 422, 'Unsupported managed page purpose.');
        $scope = (string) ($input['capability_scope'] ?? 'common');
        abort_unless(in_array($scope, self::CAPABILITY_SCOPES, true), 422, 'Invalid page capability scope.');
        $slug = $this->slug((string) ($input['slug'] ?? ''));
        $root = explode('/', $slug)[0] ?? '';
        abort_if(in_array($root, self::PROTECTED_ROOTS, true) || in_array($root, self::RESERVED_PAGE_ROOTS, true)
            || in_array($slug, array_column(self::POLICY_TYPES, 'slug'), true), 422, 'Managed pages cannot replace protected application or policy routes.');
        $structured = $this->safeTree($input['structured_content'] ?? []);
        $serviceSlugs = array_values(array_unique(array_map(fn ($value) => $this->slug((string) $value), $input['service_slugs'] ?? [])));
        if ($purpose === 'service_landing') {
            abort_if($serviceSlugs === [], 422, 'Service landing pages must be associated with at least one Digital Service.');
        }
        if ($purpose === 'digital_testimonial') {
            abort_unless(($structured['consent_confirmed'] ?? false) === true, 422, 'Digital testimonial display requires explicit consent evidence.');
        }
        if ($purpose === 'case_study') {
            abort_unless(in_array(($structured['client_disclosure'] ?? null), ['named', 'industry_only', 'anonymous'], true), 422, 'Case study disclosure state is required.');
        }

        return [
            'title' => $this->plain((string) ($input['title'] ?? ''), 190), 'slug' => $slug,
            'content' => $this->rich((string) ($input['content'] ?? ''), 50000),
            'template' => $this->key((string) ($input['template'] ?? 'standard'), 40),
            'show_in_navigation' => (bool) ($input['show_in_navigation'] ?? false), 'content_format' => 'safe_html',
            'seo_title' => $this->nullablePlain($input['seo_title'] ?? null, 190),
            'meta_description' => $this->nullablePlain($input['meta_description'] ?? null, 320),
            'canonical_url' => $this->nullableUrl($input['canonical_url'] ?? null),
            'social_title' => $this->nullablePlain($input['social_title'] ?? null, 190),
            'social_description' => $this->nullablePlain($input['social_description'] ?? null, 320),
            'social_image_media_id' => $this->mediaIdOrNull($input['social_image_media_id'] ?? null),
            'is_indexable' => (bool) ($input['is_indexable'] ?? true), 'content_purpose' => $purpose,
            'capability_scope' => $scope, 'structured_content' => $structured, 'service_slugs' => $serviceSlugs,
        ];
    }

    private function pageProjection(array $snapshot, Admin $admin, bool $existing): array
    {
        $projection = [
            'title' => $snapshot['title'], 'slug' => $snapshot['slug'], 'content' => $snapshot['content'],
            'template' => $snapshot['template'], 'show_in_navigation' => $snapshot['show_in_navigation'],
            'content_format' => $snapshot['content_format'], 'seo_title' => $snapshot['seo_title'],
            'meta_description' => $snapshot['meta_description'], 'canonical_url' => $snapshot['canonical_url'],
            'social_title' => $snapshot['social_title'], 'social_description' => $snapshot['social_description'],
            'social_image_media_id' => $snapshot['social_image_media_id'], 'is_indexable' => $snapshot['is_indexable'],
            'content_purpose' => $snapshot['content_purpose'], 'capability_scope' => $snapshot['capability_scope'],
            'structured_content' => json_encode($snapshot['structured_content'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'updated_by_admin_id' => $admin->id, 'updated_at' => now(),
        ];
        if (! $existing) {
            $projection += [
                'public_id' => (string) Str::uuid(), 'version' => 1, 'publish_state' => 'draft', 'published_at' => null,
                'protected_slug' => false, 'created_by_admin_id' => $admin->id, 'created_at' => now(),
            ];
        }

        return $projection;
    }

    private function syncPageServices(int $pageId, array $serviceSlugs): void
    {
        $ids = [];
        foreach ($serviceSlugs as $slug) {
            $id = DB::table('digital_services')->where('slug', $slug)->value('id');
            abort_unless($id, 422, 'Unknown Digital Service association: '.$slug);
            $ids[] = (int) $id;
        }
        DB::table('site_page_service_links')->where('site_managed_page_id', $pageId)->delete();
        foreach (array_values(array_unique($ids)) as $id) {
            DB::table('site_page_service_links')->insert([
                'site_managed_page_id' => $pageId, 'digital_service_id' => $id, 'relationship' => 'related', 'created_at' => now(),
            ]);
        }
    }

    private function softwareSnapshot(array $input): array
    {
        $faq = [];
        foreach ($input['faq'] ?? [] as $entry) {
            abort_unless(is_array($entry), 422, 'Software FAQ entries must be objects.');
            $question = $this->plain((string) ($entry['question'] ?? ''), 300);
            $answer = $this->rich((string) ($entry['answer'] ?? ''), 5000);
            abort_if($question === '' || $answer === '', 422, 'Software FAQ question and answer are required.');
            $faq[] = ['question' => $question, 'answer' => $answer];
        }
        abort_if(count($faq) > 100, 422, 'Too many Software FAQ entries.');
        $screens = [];
        foreach ($input['screenshot_media_ids'] ?? [] as $id) {
            $mediaId = $this->mediaIdOrNull($id);
            abort_unless($mediaId, 422, 'Invalid Software screenshot media.');
            $screens[] = $mediaId;
        }

        return [
            'name' => $this->plain((string) ($input['name'] ?? ''), 190), 'slug' => $this->slug((string) ($input['slug'] ?? '')),
            'summary' => $this->plain((string) ($input['summary'] ?? ''), 1000),
            'overview' => $this->rich((string) ($input['overview'] ?? ''), 50000),
            'features' => $this->safeTree($input['features'] ?? []), 'platforms' => $this->plainList($input['platforms'] ?? [], 20, 120),
            'system_requirements' => $this->rich((string) ($input['system_requirements'] ?? ''), 20000),
            'logo_media_id' => $this->mediaIdOrNull($input['logo_media_id'] ?? null), 'icon_media_id' => $this->mediaIdOrNull($input['icon_media_id'] ?? null),
            'hero_media_id' => $this->mediaIdOrNull($input['hero_media_id'] ?? null), 'screenshot_media_ids' => array_values(array_unique($screens)),
            'demo_media_id' => $this->mediaIdOrNull($input['demo_media_id'] ?? null),
            'limitations' => $this->plainList($input['limitations'] ?? [], 50, 500),
            'support' => $this->safeTree($input['support'] ?? []), 'cta' => $this->safeTree($input['cta'] ?? []),
            'privacy' => $this->rich((string) ($input['privacy'] ?? ''), 50000),
            'terms' => $this->rich((string) ($input['terms'] ?? ''), 50000), 'faq' => $faq,
            'seo' => [
                'title' => $this->nullablePlain($input['seo_title'] ?? null, 190),
                'description' => $this->nullablePlain($input['seo_description'] ?? null, 320),
                'canonical_url' => $this->nullableUrl($input['canonical_url'] ?? null),
                'social_title' => $this->nullablePlain($input['social_title'] ?? null, 190),
                'social_description' => $this->nullablePlain($input['social_description'] ?? null, 320),
                'social_image_media_id' => $this->mediaIdOrNull($input['social_image_media_id'] ?? null),
                'sitemap' => (bool) ($input['sitemap'] ?? true),
            ],
        ];
    }

    private function releaseNotes(mixed $raw): array
    {
        abort_unless(is_array($raw), 422, 'Release notes must be structured.');
        $result = [];
        foreach (['added', 'changed', 'fixed', 'security'] as $group) {
            $result[$group] = $this->plainList($raw[$group] ?? [], 100, 1000);
        }
        abort_if(array_sum(array_map('count', $result)) === 0, 422, 'At least one customer-readable release note is required.');

        return $result;
    }

    private function releaseImpact(mixed $raw): array
    {
        abort_unless(is_array($raw), 422, 'Release documentation-impact review must be structured.');
        $impact = [];
        foreach (self::RELEASE_IMPACT_KEYS as $key) {
            $state = (string) ($raw[$key] ?? '');
            abort_unless(in_array($state, self::RELEASE_IMPACT_STATES, true), 422, 'Release impact review is required for '.$key.'.');
            $impact[$key] = $state;
        }
        $material = $raw['material'] ?? [];
        abort_unless(is_array($material) && array_is_list($material), 422, 'Material release impacts must be a list.');
        $material = array_values(array_unique(array_map('strval', $material)));
        foreach ($material as $key) {
            abort_unless(in_array($key, self::RELEASE_IMPACT_KEYS, true), 422, 'Unknown material release impact area.');
            abort_if($impact[$key] === 'not_affected', 422, 'Material release impact requires an explicit reviewed outcome for '.$key.'.');
        }
        $impact['_material'] = $material;

        return $impact;
    }

    private function softwareRoutes(string $slug): array
    {
        $base = '/software/'.$slug;

        return ['overview' => $base, 'privacy' => $base.'/privacy', 'terms' => $base.'/terms', 'faq' => $base.'/faq', 'releases' => $base.'/releases'];
    }

    private function pageRevisionPayload(int $id): array
    {
        $row = DB::table('site_page_revisions')->where('id', $id)->firstOrFail();
        $page = DB::table('site_managed_pages')->where('id', $row->site_managed_page_id)->firstOrFail();

        return ['id' => $row->id, 'page_public_id' => $page->public_id, 'version' => (int) $row->version,
            'state' => $row->state, 'snapshot' => json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR),
            'sha256' => $row->snapshot_sha256, 'published_at' => $row->published_at];
    }

    private function policyRevisionPayload(int $id): array
    {
        $row = DB::table('cms_policy_revisions')->where('id', $id)->firstOrFail();
        $policy = DB::table('cms_policies')->where('id', $row->cms_policy_id)->firstOrFail();

        return ['id' => $row->id, 'policy_public_id' => $policy->public_id, 'policy_type' => $policy->policy_type,
            'slug' => $policy->slug, 'version' => (int) $row->version, 'state' => $row->state,
            'approval_state' => $row->approval_state, 'factual_review_state' => $row->factual_review_state,
            'effective_date' => $row->effective_date ? (string) $row->effective_date : null,
            'content_sha256' => $row->content_sha256, 'published_at' => $row->published_at];
    }

    private function softwareRevisionPayload(int $id): array
    {
        $row = DB::table('software_product_revisions')->where('id', $id)->firstOrFail();
        $product = DB::table('software_products')->where('id', $row->software_product_id)->firstOrFail();

        return ['id' => $row->id, 'software_public_id' => $product->public_id, 'slug' => $product->slug,
            'revision' => (int) $row->revision_no, 'state' => $row->state,
            'snapshot' => json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR),
            'sha256' => $row->snapshot_sha256, 'published_at' => $row->published_at];
    }

    private function softwareProductPayload(int $id): array
    {
        $row = DB::table('software_products')->where('id', $id)->firstOrFail();

        return ['public_id' => $row->public_id, 'name' => $row->name, 'slug' => $row->slug,
            'state' => $row->lifecycle_state, 'current_revision_id' => $row->current_revision_id,
            'current_release_id' => $row->current_release_id, 'archived_at' => $row->archived_at];
    }

    private function releasePayload(int $id): array
    {
        $row = DB::table('software_releases')->where('id', $id)->firstOrFail();
        $product = DB::table('software_products')->where('id', $row->software_product_id)->firstOrFail();

        return ['public_id' => $row->public_id, 'software_public_id' => $product->public_id, 'version' => $row->version,
            'release_date' => (string) $row->release_date, 'state' => $row->state, 'summary' => $row->summary,
            'notes' => json_decode($row->notes, true, flags: JSON_THROW_ON_ERROR),
            'impact_review' => json_decode($row->impact_review, true, flags: JSON_THROW_ON_ERROR),
            'sha256' => $row->snapshot_sha256, 'published_at' => $row->published_at];
    }

    private function mediaPayload(object $row): array
    {
        return ['id' => (int) $row->id, 'path' => $row->path, 'mime_type' => $row->mime_type,
            'byte_size' => (int) $row->byte_size, 'width' => (int) $row->width, 'height' => (int) $row->height,
            'sha256' => $row->sha256, 'alt_text' => $row->alt_text, 'status' => $row->status];
    }

    private function mediaIdOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_unless($id && DB::table('site_media_assets')->where('id', $id)->where('status', 'active')->exists(), 422, 'Unknown or inactive Website media asset.');

        return (int) $id;
    }

    private function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($this->canonical($snapshot), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->canonical($item), $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonical($item);
        }

        return $value;
    }

    private function plain(string $value, int $max): string
    {
        abort_unless(mb_check_encoding($value, 'UTF-8'), 422, 'Invalid text encoding.');
        $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags($value)) ?? '');
        abort_if(mb_strlen($value) > $max, 422, 'Text value is too long.');

        return $value;
    }

    private function nullablePlain(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $text = $this->plain((string) $value, $max);

        return $text === '' ? null : $text;
    }

    private function plainList(mixed $value, int $maxItems, int $maxLength): array
    {
        abort_unless(is_array($value) && array_is_list($value) && count($value) <= $maxItems, 422, 'Invalid list value.');
        $result = [];
        foreach ($value as $item) {
            $text = $this->plain((string) $item, $maxLength);
            abort_if($text === '', 422, 'List entries cannot be empty.');
            $result[] = $text;
        }

        return $result;
    }

    private function key(string $value, int $max): string
    {
        $value = strtolower(trim($value));
        abort_if($value === '' || strlen($value) > $max || ! preg_match('/\A[a-z][a-z0-9._-]*\z/', $value), 422, 'Invalid stable key.');

        return $value;
    }

    private function nullableKey(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->key((string) $value, $max);
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        abort_if($value === '' || strlen($value) > 160 || ! preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*\z/', $value), 422, 'Invalid canonical slug.');

        return $value;
    }

    private function nullableUrl(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $url = trim((string) $value);
        abort_if(strlen($url) > 500, 422, 'URL is too long.');
        if (str_starts_with($url, '/')) {
            abort_if(str_contains($url, '..') || str_starts_with($url, '//'), 422, 'Invalid relative URL.');

            return $url;
        }
        $parts = parse_url($url);
        abort_unless(is_array($parts) && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['https', 'http'], true) && isset($parts['host']), 422, 'Invalid public URL.');

        return $url;
    }

    private function dateOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        abort_unless($date && $date->format('Y-m-d') === $value, 422, 'Date must use YYYY-MM-DD.');

        return $value;
    }

    private function rich(string $value, int $max): string
    {
        abort_unless(mb_check_encoding($value, 'UTF-8'), 422, 'Invalid rich-content encoding.');
        abort_if(mb_strlen($value) > $max, 422, 'Rich content is too long.');
        abort_if(preg_match('/<(?:script|style|iframe|object|embed|form|input|svg|math)\b|\bon[a-z]+\s*=|javascript\s*:|data\s*:\s*text\/html/i', $value), 422, 'Unsafe rich content.');
        $clean = strip_tags($value, '<p><br><strong><em><ul><ol><li><h2><h3><blockquote><a><code><pre>');
        preg_match_all('/<([a-z0-9]+)\s+([^>]+)>/i', $clean, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            abort_unless(strtolower($match[1]) === 'a', 422, 'Rich-content attributes are not allowed on this tag.');
            abort_unless(preg_match('/\Ahref\s*=\s*(["\'])(.*?)\1\z/is', trim($match[2]), $href), 422, 'Only an href attribute is allowed on links.');
            $url = trim($href[2]);
            if (str_starts_with(strtolower($url), 'mailto:')) {
                abort_unless(filter_var(substr($url, 7), FILTER_VALIDATE_EMAIL), 422, 'Invalid email link.');
            } else {
                $this->nullableUrl($url);
            }
        }
        $clean = preg_replace_callback('/<a\s+href\s*=\s*(["\'])(.*?)\1\s*>/is', function ($match) {
            return '<a href="'.htmlspecialchars(trim($match[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
        }, $clean) ?? '';

        return trim($clean);
    }

    private function safeTree(mixed $value, int $depth = 0): mixed
    {
        abort_if($depth > 6, 422, 'Structured content is too deeply nested.');
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            abort_if(! is_finite($value), 422, 'Structured number is invalid.');

            return $value;
        }
        if (is_string($value)) {
            return $this->plain($value, 5000);
        }
        abort_unless(is_array($value) && count($value) <= 100, 422, 'Structured content must be a bounded JSON-like value.');
        $result = [];
        if (array_is_list($value)) {
            foreach ($value as $item) {
                $result[] = $this->safeTree($item, $depth + 1);
            }

            return $result;
        }
        foreach ($value as $key => $item) {
            abort_unless(is_string($key) && preg_match('/\A[a-zA-Z][a-zA-Z0-9._-]{0,79}\z/', $key), 422, 'Invalid structured-content key.');
            $result[$key] = $this->safeTree($item, $depth + 1);
        }

        return $result;
    }

    private function bump(string $domain): void
    {
        DB::table('publication_versions')->insertOrIgnore(['domain' => $domain, 'version' => 0, 'updated_at' => now()]);
        DB::table('publication_versions')->where('domain', $domain)->increment('version', 1, ['updated_at' => now()]);
    }
}
