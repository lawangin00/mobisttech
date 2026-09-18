<?php

namespace App\Pos;

use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Models\Admin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PosConfiguration
{
    private const CACHE_KEY = 'mobist.target.pos.configuration.v1';

    private const DEFINITIONS = [
        'invoice.show_logo' => ['domain' => 'documents', 'group' => 'invoice', 'label' => 'Show invoice logo / wordmark', 'type' => 'boolean', 'input' => 'boolean', 'default' => true, 'sort' => 5],
        'invoice.show_business_address' => ['domain' => 'documents', 'group' => 'invoice', 'label' => 'Show business / branch address', 'type' => 'boolean', 'input' => 'boolean', 'default' => true, 'sort' => 10],
        'invoice.show_business_contacts' => ['domain' => 'documents', 'group' => 'invoice', 'label' => 'Show business contact details', 'type' => 'boolean', 'input' => 'boolean', 'default' => true, 'sort' => 20],
        'invoice.show_business_legal_name' => ['domain' => 'documents', 'group' => 'invoice', 'label' => 'Show legal / registered name', 'type' => 'boolean', 'input' => 'boolean', 'default' => false, 'sort' => 21],
        'invoice.show_business_hours' => ['domain' => 'documents', 'group' => 'invoice', 'label' => 'Show business hours', 'type' => 'boolean', 'input' => 'boolean', 'default' => false, 'sort' => 22],
        'invoice.show_business_identifiers' => ['domain' => 'documents', 'group' => 'invoice', 'label' => 'Show approved business identifiers', 'type' => 'boolean', 'input' => 'boolean', 'default' => false, 'sort' => 23],
        'invoice.show_customer_cnic' => ['domain' => 'documents', 'group' => 'invoice', 'label' => 'Show customer CNIC on receipt', 'type' => 'boolean', 'input' => 'boolean', 'default' => true, 'sort' => 30],
        'invoice.show_salesperson' => ['domain' => 'documents', 'group' => 'invoice', 'label' => 'Show salesperson name', 'type' => 'boolean', 'input' => 'boolean', 'default' => false, 'sort' => 31],
        'invoice.show_warranty_terms' => ['domain' => 'documents', 'group' => 'invoice', 'label' => 'Show warranty terms section', 'type' => 'boolean', 'input' => 'boolean', 'default' => true, 'sort' => 40],
        'invoice.show_thank_you' => ['domain' => 'documents', 'group' => 'invoice', 'label' => 'Show thank-you footer', 'type' => 'boolean', 'input' => 'boolean', 'default' => true, 'sort' => 90],
        'invoice.thank_you_text' => ['domain' => 'documents', 'group' => 'invoice', 'label' => 'Invoice thank-you text', 'type' => 'string', 'input' => 'text', 'default' => 'Thank you for shopping with us!', 'sort' => 100],
        'invoice.default_output_format' => ['domain' => 'documents', 'group' => 'invoice', 'label' => 'Default invoice output', 'type' => 'string', 'input' => 'select', 'default' => 'thermal', 'sort' => 101, 'options' => ['thermal' => 'Thermal receipt (80mm)', 'a4' => 'A4 document']],
        'invoice.footer_alignment' => ['domain' => 'documents', 'group' => 'invoice', 'label' => 'Footer text alignment', 'type' => 'string', 'input' => 'select', 'default' => 'center', 'sort' => 102, 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']],
        'warranty.show_business_address' => ['domain' => 'documents', 'group' => 'warranty', 'label' => 'Show business / branch address', 'type' => 'boolean', 'input' => 'boolean', 'default' => true, 'sort' => 110],
        'warranty.show_business_contacts' => ['domain' => 'documents', 'group' => 'warranty', 'label' => 'Show business contact details', 'type' => 'boolean', 'input' => 'boolean', 'default' => true, 'sort' => 120],
        'warranty.show_customer_cnic' => ['domain' => 'documents', 'group' => 'warranty', 'label' => 'Show customer CNIC', 'type' => 'boolean', 'input' => 'boolean', 'default' => true, 'sort' => 130],
        'warranty.show_assigned_to' => ['domain' => 'documents', 'group' => 'warranty', 'label' => 'Show Assigned To field', 'type' => 'boolean', 'input' => 'boolean', 'default' => true, 'sort' => 140],
        'warranty.show_expected_completion' => ['domain' => 'documents', 'group' => 'warranty', 'label' => 'Show expected completion', 'type' => 'boolean', 'input' => 'boolean', 'default' => true, 'sort' => 150],
        'warranty.show_status' => ['domain' => 'documents', 'group' => 'warranty', 'label' => 'Show claim status', 'type' => 'boolean', 'input' => 'boolean', 'default' => true, 'sort' => 160],

        'theme.primary' => ['domain' => 'theme', 'group' => 'theme', 'label' => 'Primary', 'type' => 'color', 'input' => 'color', 'default' => '#008080', 'sort' => 181],
        'theme.primary_hover' => ['domain' => 'theme', 'group' => 'theme', 'label' => 'Primary hover', 'type' => 'color', 'input' => 'color', 'default' => '#007176', 'sort' => 182],
        'theme.secondary' => ['domain' => 'theme', 'group' => 'theme', 'label' => 'Secondary', 'type' => 'color', 'input' => 'color', 'default' => '#005b60', 'sort' => 183],
        'theme.accent' => ['domain' => 'theme', 'group' => 'theme', 'label' => 'Accent', 'type' => 'color', 'input' => 'color', 'default' => '#00b4d8', 'sort' => 184],
        'theme.background' => ['domain' => 'theme', 'group' => 'theme', 'label' => 'Background', 'type' => 'color', 'input' => 'color', 'default' => '#f7f8fb', 'sort' => 185],
        'theme.surface' => ['domain' => 'theme', 'group' => 'theme', 'label' => 'Surface', 'type' => 'color', 'input' => 'color', 'default' => '#ffffff', 'sort' => 186],
        'theme.text' => ['domain' => 'theme', 'group' => 'theme', 'label' => 'Text', 'type' => 'color', 'input' => 'color', 'default' => '#111827', 'sort' => 187],
        'theme.muted_text' => ['domain' => 'theme', 'group' => 'theme', 'label' => 'Muted text', 'type' => 'color', 'input' => 'color', 'default' => '#667085', 'sort' => 188],
        'theme.border' => ['domain' => 'theme', 'group' => 'theme', 'label' => 'Border', 'type' => 'color', 'input' => 'color', 'default' => '#e7eaf0', 'sort' => 189],

        'branding.full_wordmark_media_id' => ['domain' => 'branding', 'group' => 'branding', 'label' => 'Full wordmark', 'type' => 'integer', 'input' => 'media', 'default' => 0, 'sort' => 280],
        'branding.app_icon_media_id' => ['domain' => 'branding', 'group' => 'branding', 'label' => 'App icon', 'type' => 'integer', 'input' => 'media', 'default' => 0, 'sort' => 290],
        'branding.header_logo_media_id' => ['domain' => 'branding', 'group' => 'branding', 'label' => 'Portal header logo', 'type' => 'integer', 'input' => 'media', 'default' => 0, 'sort' => 300],
        'branding.login_logo_media_id' => ['domain' => 'branding', 'group' => 'branding', 'label' => 'Login-screen logo', 'type' => 'integer', 'input' => 'media', 'default' => 0, 'sort' => 310],
        'branding.invoice_logo_media_id' => ['domain' => 'branding', 'group' => 'branding', 'label' => 'Invoice logo', 'type' => 'integer', 'input' => 'media', 'default' => 0, 'sort' => 320],
        'branding.warranty_logo_media_id' => ['domain' => 'branding', 'group' => 'branding', 'label' => 'Warranty-document logo', 'type' => 'integer', 'input' => 'media', 'default' => 0, 'sort' => 330],
        'branding.favicon_media_id' => ['domain' => 'branding', 'group' => 'branding', 'label' => 'Favicon / browser icon', 'type' => 'integer', 'input' => 'media', 'default' => 0, 'sort' => 340],
        'branding.desktop_app_icon_media_id' => ['domain' => 'branding', 'group' => 'branding', 'label' => 'Desktop / app icon', 'type' => 'integer', 'input' => 'media', 'default' => 0, 'sort' => 350],
    ];

    private const DOMAIN_PERMISSION = [
        'documents' => 'config.documents.manage',
        'theme' => 'config.theme.manage',
        'branding' => 'config.branding.manage',
    ];

    public function catalogue(Admin $actor): array
    {
        $domains = [];
        foreach (array_keys(self::DOMAIN_PERMISSION) as $domain) {
            if (! $this->can($actor, $domain)) {
                continue;
            }
            $domains[$domain] = [
                'definitions' => $this->definitions($domain),
                'values' => $this->current($domain),
                'revisions' => $this->revisions($domain),
            ];
        }

        return [
            'domains' => $domains,
            'branding_media' => $this->can($actor, 'branding') ? $this->media() : [],
        ];
    }

    public function preview(Admin $actor, string $domain, array $changes): array
    {
        $this->authorize($actor, $domain);
        $snapshot = array_replace($this->current($domain), $this->normalize($domain, $changes));
        if ($domain === 'theme') {
            $this->validateTheme($snapshot);
        }
        if ($domain === 'branding') {
            $this->validateBranding($snapshot);
        }

        return $snapshot;
    }

    public function draft(Admin $actor, string $domain, array $changes): array
    {
        $snapshot = $this->preview($actor, $domain, $changes);

        return DB::transaction(function () use ($actor, $domain, $snapshot) {
            $version = (int) DB::table('pos_configuration_revisions')->where('domain', $domain)->max('version') + 1;
            $id = DB::table('pos_configuration_revisions')->insertGetId([
                'domain' => $domain, 'version' => $version, 'state' => 'draft',
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'created_by_type' => Admin::class, 'created_by_id' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $actor->id, 'pos_configuration_draft_saved', "pos-configuration:{$domain}:{$id}");

            return $this->revision($id);
        });
    }

    public function publish(Admin $actor, int $revisionId): array
    {
        return DB::transaction(function () use ($actor, $revisionId) {
            $revision = DB::table('pos_configuration_revisions')->where('id', $revisionId)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $revision->domain);
            abort_unless($revision->state === 'draft', 409, 'Only a draft POS configuration revision can be published.');
            $snapshot = json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR);
            $expected = array_keys($this->defaults($revision->domain));
            $actual = array_keys($snapshot);
            sort($expected);
            sort($actual);
            abort_unless($expected === $actual, 409, 'POS configuration revision no longer matches its registered contract.');
            if ($revision->domain === 'theme') {
                $this->validateTheme($snapshot);
            }
            if ($revision->domain === 'branding') {
                $this->validateBranding($snapshot);
            }

            DB::table('pos_configuration_revisions')->where('domain', $revision->domain)->where('state', 'published')
                ->update(['state' => 'superseded', 'updated_at' => now()]);
            foreach ($this->definitions($revision->domain) as $key => $definition) {
                DB::table('pos_settings')->updateOrInsert(['key' => $key], [
                    'value' => $this->storage($snapshot[$key], $definition['type']),
                    'group' => $definition['group'], 'label' => $definition['label'],
                    'input_type' => $definition['input'], 'sort_order' => $definition['sort'],
                    'updated_at' => now(), 'created_at' => now(),
                ]);
            }
            DB::table('pos_configuration_revisions')->where('id', $revisionId)->update([
                'state' => 'published', 'published_by_type' => Admin::class, 'published_by_id' => $actor->id,
                'published_at' => now(), 'updated_at' => now(),
            ]);
            if ($revision->domain === 'branding') {
                $this->syncBrandingUsages($snapshot);
            }
            Cache::forget(self::CACHE_KEY);
            IdentityAudit::record('admin', $actor->id, 'pos_configuration_published', "pos-configuration:{$revision->domain}:{$revisionId}");

            return $this->revision($revisionId);
        });
    }

    public function rollback(Admin $actor, int $revisionId): array
    {
        $source = DB::table('pos_configuration_revisions')->where('id', $revisionId)->firstOrFail();
        $this->authorize($actor, $source->domain);
        abort_unless(in_array($source->state, ['published', 'superseded'], true), 409, 'Rollback requires published POS configuration history.');
        $snapshot = json_decode($source->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $draft = $this->draft($actor, $source->domain, $snapshot);
        DB::table('pos_configuration_revisions')->where('id', $draft['id'])->update(['restored_from_revision_id' => $source->id]);

        return $this->publish($actor, $draft['id']);
    }

    public function uploadBranding(Admin $actor, string $base64, string $extension, string $originalName, ?string $altText): array
    {
        $this->authorize($actor, 'branding');
        $bytes = base64_decode($base64, true);
        if ($bytes === false || strlen($bytes) < 1 || strlen($bytes) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['media' => 'The POS branding image is invalid or exceeds 5 MB.']);
        }
        $image = @getimagesizefromstring($bytes);
        if (! is_array($image)) {
            throw ValidationException::withMessages(['media' => 'The POS branding file is not a valid image.']);
        }
        $mime = $image['mime'] ?? '';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (! isset($allowed[$mime])) {
            throw ValidationException::withMessages(['media' => 'Only validated JPEG, PNG and WebP images are supported.']);
        }
        $extension = strtolower(trim($extension));
        if (! in_array($extension, [$allowed[$mime], $mime === 'image/jpeg' ? 'jpeg' : $allowed[$mime]], true)) {
            throw ValidationException::withMessages(['media' => 'File extension does not match the validated image type.']);
        }
        [$width, $height] = $image;
        if ($width < 1 || $height < 1 || $width > 8192 || $height > 8192 || $width * $height > 32_000_000) {
            throw ValidationException::withMessages(['media' => 'The image dimensions exceed safe processing limits.']);
        }
        $filename = Str::uuid().'.'.$allowed[$mime];
        $path = 'dynamic-media/branding/'.$filename;
        Storage::disk('public')->put($path, $bytes);

        try {
            $id = DB::table('pos_media_assets')->insertGetId([
                'disk' => 'public', 'path' => $path, 'original_name' => Str::limit(basename(str_replace('\\', '/', $originalName)), 255, ''),
                'mime_type' => $mime, 'extension' => $allowed[$mime], 'byte_size' => strlen($bytes),
                'width' => $width, 'height' => $height, 'aspect_ratio' => round($width / $height, 6),
                'sha256' => hash('sha256', $bytes), 'alt_text' => $altText ? Str::limit(trim($altText), 500, '') : null,
                'status' => 'active', 'uploaded_by_type' => Admin::class, 'uploaded_by_id' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
        IdentityAudit::record('admin', $actor->id, 'pos_branding_media_uploaded', "pos-media:{$id}");

        return $this->mediaRow(DB::table('pos_media_assets')->where('id', $id)->firstOrFail());
    }

    private function definitions(string $domain): array
    {
        $this->domain($domain);

        return collect(self::DEFINITIONS)->filter(fn ($definition) => $definition['domain'] === $domain)
            ->sortBy('sort')->all();
    }

    private function defaults(string $domain): array
    {
        return collect($this->definitions($domain))->mapWithKeys(fn ($definition, $key) => [$key => $definition['default']])->all();
    }

    private function current(string $domain): array
    {
        $defaults = $this->defaults($domain);
        $rows = Cache::remember(self::CACHE_KEY, 300, fn () => DB::table('pos_settings')->get(['key', 'value'])->keyBy('key')->all());
        $result = [];
        foreach ($this->definitions($domain) as $key => $definition) {
            $raw = isset($rows[$key]) ? $rows[$key]->value : null;
            $result[$key] = $raw === null ? $defaults[$key] : $this->decode($raw, $definition['type'], $defaults[$key]);
        }

        return $result;
    }

    private function revisions(string $domain): array
    {
        return DB::table('pos_configuration_revisions')->where('domain', $domain)->orderByDesc('version')->limit(30)->get()
            ->map(fn ($row) => $this->revisionRow($row))->all();
    }

    private function revision(int $id): array
    {
        return $this->revisionRow(DB::table('pos_configuration_revisions')->where('id', $id)->firstOrFail());
    }

    private function revisionRow(object $row): array
    {
        return ['id' => (int) $row->id, 'domain' => $row->domain, 'version' => (int) $row->version, 'state' => $row->state,
            'snapshot' => json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR), 'published_at' => $row->published_at,
            'restored_from_revision_id' => $row->restored_from_revision_id ? (int) $row->restored_from_revision_id : null,
            'created_at' => $row->created_at];
    }

    private function media(): array
    {
        return DB::table('pos_media_assets')->where('status', 'active')->orderByDesc('id')->limit(100)->get()
            ->map(fn ($row) => $this->mediaRow($row))->all();
    }

    private function mediaRow(object $row): array
    {
        return ['id' => (int) $row->id, 'original_name' => $row->original_name, 'mime_type' => $row->mime_type,
            'byte_size' => (int) $row->byte_size, 'width' => (int) $row->width, 'height' => (int) $row->height,
            'aspect_ratio' => (string) $row->aspect_ratio, 'sha256' => $row->sha256, 'alt_text' => $row->alt_text,
            'status' => $row->status, 'created_at' => $row->created_at];
    }

    private function normalize(string $domain, array $changes): array
    {
        $definitions = $this->definitions($domain);
        $unknown = array_diff(array_keys($changes), array_keys($definitions));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['settings' => 'Unsupported POS configuration key submitted.']);
        }
        $normalized = [];
        foreach ($changes as $key => $value) {
            $definition = $definitions[$key];
            $normalized[$key] = match ($definition['type']) {
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
                'integer' => is_numeric($value) ? (int) $value : null,
                'color' => is_string($value) && preg_match('/\A#[0-9a-fA-F]{6}\z/', $value) ? strtolower($value) : null,
                default => is_scalar($value) || $value === null ? trim((string) $value) : null,
            };
            if ($normalized[$key] === null) {
                throw ValidationException::withMessages([$key => 'Invalid POS configuration value.']);
            }
            if (($definition['options'] ?? []) !== [] && ! array_key_exists((string) $normalized[$key], $definition['options'])) {
                throw ValidationException::withMessages([$key => 'Unsupported POS configuration option.']);
            }
            if (is_string($normalized[$key]) && mb_strlen($normalized[$key]) > 1000) {
                throw ValidationException::withMessages([$key => 'POS configuration text is too long.']);
            }
        }

        return $normalized;
    }

    private function validateTheme(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! preg_match('/\A#[0-9a-fA-F]{6}\z/', (string) $value)) {
                throw ValidationException::withMessages([$key => 'Theme colors must use six-digit hexadecimal values.']);
            }
        }
        if ($this->contrast($values['theme.text'], $values['theme.surface']) < 4.5) {
            throw ValidationException::withMessages(['theme.text' => 'Text and surface colors require at least 4.5:1 contrast.']);
        }
    }

    private function validateBranding(array $values): void
    {
        $ids = array_values(array_filter(array_map('intval', $values)));
        if ($ids === []) {
            return;
        }
        $found = DB::table('pos_media_assets')->whereIn('id', array_unique($ids))->where('status', 'active')->count();
        if ($found !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['branding' => 'One or more branding assets are missing or inactive.']);
        }
    }

    private function syncBrandingUsages(array $values): void
    {
        DB::table('pos_media_usages')->where('usage_domain', 'branding')->where('usage_key', 'like', 'setting.%')->delete();
        foreach ($values as $key => $id) {
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            DB::table('pos_media_usages')->insert([
                'media_asset_id' => $id, 'usage_domain' => 'branding', 'usage_key' => 'setting.'.$key,
                'entity_type' => null, 'entity_id' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function decode(string $raw, string $type, mixed $fallback): mixed
    {
        return match ($type) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $fallback,
            'integer' => (int) $raw,
            default => $raw,
        };
    }

    private function storage(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) ((int) $value),
            default => trim((string) $value),
        };
    }

    private function domain(string $domain): void
    {
        if (! isset(self::DOMAIN_PERMISSION[$domain])) {
            throw ValidationException::withMessages(['domain' => 'Unknown POS configuration domain.']);
        }
    }

    private function can(Admin $actor, string $domain): bool
    {
        $this->domain($domain);

        return app(Access::class)->allows($actor->fresh(), self::DOMAIN_PERMISSION[$domain]);
    }

    private function authorize(Admin $actor, string $domain): void
    {
        abort_unless($this->can($actor, $domain), 403);
    }

    private function contrast(string $a, string $b): float
    {
        $lum = function (string $hex): float {
            $channels = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
            $channels = array_map(function ($v) {
                $v /= 255;

                return $v <= .03928 ? $v / 12.92 : (($v + .055) / 1.055) ** 2.4;
            }, $channels);

            return .2126 * $channels[0] + .7152 * $channels[1] + .0722 * $channels[2];
        };
        $x = $lum($a);
        $y = $lum($b);

        return (max($x, $y) + .05) / (min($x, $y) + .05);
    }
}
