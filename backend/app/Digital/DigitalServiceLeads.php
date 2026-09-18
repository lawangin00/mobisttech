<?php

namespace App\Digital;

use App\Addendum\WebsiteCapabilities;
use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Infrastructure\PrivateObjects;
use App\Models\Admin;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class DigitalServiceLeads
{
    private const PRICE_TYPES = ['quote', 'fixed', 'starting_from', 'package'];

    private const LEAD_STATUSES = ['new', 'contacted', 'qualified', 'proposal', 'approved', 'in_progress', 'completed', 'lost', 'closed'];

    private const FILE_MIMES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'text/plain' => 'bin'];

    public function __construct(
        private readonly Access $access,
        private readonly WebsiteCapabilities $capabilities,
        private readonly PrivateObjects $objects,
    ) {}

    public function catalogue(): array
    {
        if (! $this->capabilities->allowsScope('digital')) {
            throw new LogicException('Digital service discovery is inactive in the published Website mode.');
        }

        return DB::table('digital_services')->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->map(function ($service) {
                $this->assertBasePrice((string) $service->price_type, $service->price);

                return [
                    'slug' => $service->slug, 'name' => $service->name, 'category' => $service->category,
                    'short_description' => $service->short_description, 'description' => $service->description,
                    'price_type' => $service->price_type, 'price' => $service->price,
                    'packages' => $this->offerRows('digital_service_packages', (int) $service->id),
                    'addons' => $this->offerRows('digital_service_addons', (int) $service->id),
                ];
            })->all();
    }

    public function configureService(Admin $actor, array $input): array
    {
        $this->authorize($actor, 'website.services.manage');
        $this->fields($input, ['slug', 'name', 'category', 'short_description', 'description', 'price_type', 'price', 'is_active', 'sort_order', 'packages', 'addons']);
        $data = Validator::make($input, [
            'slug' => 'required|string|max:160', 'name' => 'required|string|max:190', 'category' => 'nullable|string|max:120',
            'short_description' => 'required|string|max:1000', 'description' => 'nullable|string|max:20000',
            'price_type' => 'required|string|in:quote,fixed,starting_from,package', 'price' => 'nullable',
            'is_active' => 'required|boolean', 'sort_order' => 'nullable|integer|min:0|max:10000',
            'packages' => 'nullable|array|max:30', 'addons' => 'nullable|array|max:50',
        ])->validate();
        $slug = Str::slug($data['slug']);
        abort_if($slug === '', 422, 'Invalid service slug.');
        $price = $this->money($data['price'] ?? null);
        $this->assertBasePrice($data['price_type'], $price);
        if ($data['price_type'] === 'package' && empty($data['packages'])) {
            throw ValidationException::withMessages(['packages' => 'Package-priced services require at least one package.']);
        }

        return DB::transaction(function () use ($actor, $data, $slug, $price) {
            $existing = DB::table('digital_services')->where('slug', $slug)->lockForUpdate()->first();
            $values = [
                'name' => trim($data['name']), 'category' => $this->nullable($data['category'] ?? null),
                'short_description' => trim($data['short_description']), 'description' => $this->nullable($data['description'] ?? null),
                'price_type' => $data['price_type'], 'price' => $price, 'is_active' => (bool) $data['is_active'],
                'sort_order' => (int) ($data['sort_order'] ?? 0), 'updated_at' => now(),
            ];
            if ($existing) {
                DB::table('digital_services')->where('id', $existing->id)->update($values);
                $serviceId = (int) $existing->id;
            } else {
                $serviceId = (int) DB::table('digital_services')->insertGetId($values + ['slug' => $slug, 'created_at' => now()]);
            }
            $this->syncOffers('digital_service_packages', $serviceId, $data['packages'] ?? []);
            $this->syncOffers('digital_service_addons', $serviceId, $data['addons'] ?? []);
            $this->bump('digital-services');
            IdentityAudit::record('admin', $actor->id, 'digital_service_configured', 'service:'.$serviceId);

            return $this->servicePayload($serviceId);
        });
    }

    public function configureConsultation(Admin $actor, array $input): array
    {
        $this->authorize($actor, 'website.consultations.manage');
        $this->fields($input, ['enabled', 'timezone', 'weekly_availability']);
        $data = Validator::make($input, [
            'enabled' => 'required|boolean', 'timezone' => 'required|string|max:64',
            'weekly_availability' => 'nullable|array|max:7',
        ])->validate();
        try {
            new DateTimeZone($data['timezone']);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['timezone' => 'Invalid consultation timezone.']);
        }
        $availability = $this->availability($data['weekly_availability'] ?? []);

        return DB::transaction(function () use ($actor, $data, $availability) {
            $current = DB::table('digital_consultation_settings')->where('id', 1)->lockForUpdate()->first();
            $values = ['enabled' => (bool) $data['enabled'], 'timezone' => $data['timezone'],
                'weekly_availability' => json_encode($availability, JSON_THROW_ON_ERROR),
                'version' => $current ? (int) $current->version + 1 : 1, 'updated_by_admin_id' => $actor->id, 'updated_at' => now()];
            if ($current) {
                DB::table('digital_consultation_settings')->where('id', 1)->update($values);
            } else {
                DB::table('digital_consultation_settings')->insert($values + ['id' => 1, 'created_at' => now()]);
            }
            IdentityAudit::record('admin', $actor->id, 'digital_consultation_configured', 'consultation-settings');

            return ['enabled' => (bool) $data['enabled'], 'timezone' => $data['timezone'], 'weekly_availability' => $availability,
                'version' => $values['version'], 'external_calendar' => ['enabled' => false, 'provider' => null]];
        });
    }

    public function submit(string $idempotencyKey, string $clientFingerprint, array $input, array $files = []): array
    {
        abort_if($idempotencyKey === '' || strlen($idempotencyKey) > 120, 422, 'Invalid enquiry idempotency key.');
        abort_if($clientFingerprint === '' || strlen($clientFingerprint) > 1000, 422, 'Invalid submission fingerprint.');
        $this->fields($input, ['service_slug', 'customer_name', 'business_name', 'customer_mobile', 'customer_email', 'requirements',
            'project_type', 'existing_url', 'budget_range', 'preferred_timeline', 'preferred_contact', 'package_public_id', 'addon_public_ids',
            'source', 'campaign', 'consultation_requested', 'preferred_timezone', 'preferred_window_start', 'preferred_window_end']);
        $data = Validator::make($input, [
            'service_slug' => 'required|string|max:160', 'customer_name' => 'required|string|max:190',
            'business_name' => 'nullable|string|max:190', 'customer_mobile' => 'required|string|max:40',
            'customer_email' => 'nullable|email:rfc|max:254', 'requirements' => 'required|string|max:10000',
            'project_type' => 'nullable|string|max:120', 'existing_url' => 'nullable|url|max:500',
            'budget_range' => 'nullable|string|max:120', 'preferred_timeline' => 'nullable|string|max:190',
            'preferred_contact' => 'nullable|string|in:whatsapp,phone,email', 'package_public_id' => 'nullable|uuid',
            'addon_public_ids' => 'nullable|array|max:20', 'addon_public_ids.*' => 'uuid',
            'source' => 'nullable|string|max:120', 'campaign' => 'nullable|string|max:120',
            'consultation_requested' => 'nullable|boolean', 'preferred_timezone' => 'nullable|string|max:64',
            'preferred_window_start' => 'nullable|string|max:80', 'preferred_window_end' => 'nullable|string|max:80',
        ])->validate();
        $fileDescriptors = $this->validateFiles($files);
        $fingerprint = hash('sha256', $clientFingerprint);
        $requestHash = $this->digest(['input' => $data, 'files' => array_map(fn ($f) => ['name' => $f['name'], 'sha256' => $f['sha256']], $fileDescriptors)]);

        return DB::transaction(function () use ($data, $fileDescriptors, $fingerprint, $requestHash, $idempotencyKey) {
            $this->capabilities->assertCreationAllowed('enquiry.create');
            $idem = $this->claimIdempotency($fingerprint, $idempotencyKey, $requestHash);
            if ($idem['replay']) {
                return $idem['response'];
            }
            $this->consumeRate($fingerprint);
            $service = DB::table('digital_services')->where('slug', Str::slug($data['service_slug']))->where('is_active', true)->lockForUpdate()->first();
            abort_unless($service, 422, 'Selected Digital Service is unavailable.');
            $selections = $this->resolveSelections((int) $service->id, $data);
            [$windowStart, $windowEnd, $timezone, $consultation] = $this->consultationSelection($data);
            $publicId = (string) Str::uuid();
            $reference = 'DSR-'.now()->format('ymd').'-'.Str::upper(Str::random(10));
            $requestId = (int) DB::table('service_requests')->insertGetId([
                'reference' => $reference, 'digital_service_id' => $service->id, 'customer_name' => trim($data['customer_name']),
                'business_name' => $this->nullable($data['business_name'] ?? null), 'customer_mobile' => trim($data['customer_mobile']),
                'customer_email' => $this->nullable($data['customer_email'] ?? null), 'requirements' => trim($data['requirements']),
                'preferred_contact' => $data['preferred_contact'] ?? 'whatsapp', 'status' => 'new', 'public_id' => $publicId,
                'version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('service_request_details')->insert([
                'service_request_id' => $requestId, 'project_type' => $this->nullable($data['project_type'] ?? null),
                'existing_url' => $this->nullable($data['existing_url'] ?? null), 'budget_range' => $this->nullable($data['budget_range'] ?? null),
                'preferred_timeline' => $this->nullable($data['preferred_timeline'] ?? null), 'source' => $this->nullable($data['source'] ?? null),
                'campaign' => $this->nullable($data['campaign'] ?? null), 'submission_fingerprint' => $fingerprint,
                'preferred_timezone' => $timezone, 'preferred_window_start_utc' => $windowStart, 'preferred_window_end_utc' => $windowEnd,
                'consultation_requested' => $consultation, 'consultation_status' => $consultation ? 'requested' : 'not_requested',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($selections as $selection) {
                DB::table('service_request_selections')->insert($selection + ['service_request_id' => $requestId, 'created_at' => now()]);
            }
            foreach ($fileDescriptors as $file) {
                $objectId = (string) Str::uuid();
                $objectKey = 'media/'.$objectId.'.'.$file['extension'];
                $this->objects->put($objectKey, $file['contents'], $file['sha256']);
                DB::table('service_request_files')->insert([
                    'id' => $objectId, 'service_request_id' => $requestId, 'object_key' => $objectKey,
                    'original_name' => $file['name'], 'mime_type' => $file['mime'], 'byte_size' => $file['size'],
                    'sha256' => $file['sha256'], 'uploaded_at' => now(),
                ]);
            }
            $snapshot = [
                'service_slug' => $service->slug, 'service_name' => $service->name, 'status' => 'new',
                'selection_count' => count($selections), 'file_count' => count($fileDescriptors),
                'consultation_requested' => $consultation, 'mode_version' => $this->capabilities->snapshot()['version'],
            ];
            $this->event($requestId, 'submitted', null, $snapshot);
            $response = ['public_id' => $publicId, 'reference' => $reference, 'status' => 'new',
                'service' => ['slug' => $service->slug, 'name' => $service->name],
                'selections' => array_map(fn ($row) => ['type' => $row['selection_type'], 'label' => $row['label_snapshot'],
                    'pricing_type' => $row['pricing_type'], 'price' => $row['price_snapshot'], 'currency' => $row['currency']], $selections),
                'consultation_status' => $consultation ? 'requested' : 'not_requested'];
            DB::table('idempotency_requests')->where('id', $idem['id'])->update([
                'resource_type' => 'service_request', 'resource_id' => $requestId, 'status' => 'completed',
                'response' => json_encode($response, JSON_THROW_ON_ERROR), 'response_expires_at' => now()->addDays(7), 'updated_at' => now(),
            ]);

            return $response;
        });
    }

    public function transition(Admin $actor, string $publicId, array $input): array
    {
        $this->authorize($actor, 'website.digital-leads.manage');
        $this->fields($input, ['version', 'status', 'assigned_admin_public_id', 'follow_up_at', 'note', 'consultation_status']);
        $data = Validator::make($input, [
            'version' => 'required|integer|min:1', 'status' => 'required|string|in:'.implode(',', self::LEAD_STATUSES),
            'assigned_admin_public_id' => 'nullable|uuid', 'follow_up_at' => 'nullable|string|max:80',
            'note' => 'nullable|string|max:2000',
            'consultation_status' => 'nullable|string|in:requested,confirmed,completed,cancelled',
        ])->validate();

        return DB::transaction(function () use ($actor, $publicId, $data) {
            $request = DB::table('service_requests')->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            abort_unless((int) $request->version === (int) $data['version'], 409, 'Lead version changed; reload before editing.');
            $detail = DB::table('service_request_details')->where('service_request_id', $request->id)->lockForUpdate()->firstOrFail();
            $assignee = null;
            if (! empty($data['assigned_admin_public_id'])) {
                $assignee = Admin::query()->where('public_id', $data['assigned_admin_public_id'])->firstOrFail();
                abort_unless($assignee->usable() && $assignee->hasPermission('website.digital-leads.manage'), 422, 'Assigned lead owner is not eligible.');
            }
            $followUp = $this->utcInstant($data['follow_up_at'] ?? null, 'follow_up_at');
            $closedAt = in_array($data['status'], ['completed', 'lost', 'closed'], true) ? now() : null;
            DB::table('service_requests')->where('id', $request->id)->update([
                'status' => $data['status'], 'version' => (int) $request->version + 1, 'updated_at' => now(),
            ]);
            DB::table('service_request_details')->where('service_request_id', $request->id)->update([
                'assigned_admin_id' => $assignee?->id ?? $detail->assigned_admin_id,
                'follow_up_at' => $followUp, 'last_contacted_at' => $data['status'] === 'contacted' ? now() : $detail->last_contacted_at,
                'closed_at' => $closedAt, 'consultation_status' => $data['consultation_status'] ?? $detail->consultation_status,
                'updated_at' => now(),
            ]);
            $snapshot = [
                'from_status' => $request->status, 'to_status' => $data['status'],
                'assigned_admin_id' => $assignee?->id ?? $detail->assigned_admin_id,
                'follow_up_at' => $followUp?->toISOString(), 'note' => $this->nullable($data['note'] ?? null),
                'consultation_status' => $data['consultation_status'] ?? $detail->consultation_status,
                'version' => (int) $request->version + 1,
            ];
            $this->event((int) $request->id, 'lead_updated', $actor->id, $snapshot);
            IdentityAudit::record('admin', $actor->id, 'digital_lead_updated', 'service-request:'.$request->id);

            return $this->leadPayload((int) $request->id);
        });
    }

    public function lead(Admin $actor, string $publicId): array
    {
        $this->authorize($actor, 'website.digital-leads.manage');
        $requestId = DB::table('service_requests')->where('public_id', $publicId)->value('id') ?? abort(404);

        return $this->leadPayload((int) $requestId);
    }

    public function downloadReference(Admin $actor, string $publicId, string $fileId): array
    {
        $this->authorize($actor, 'website.digital-leads.manage');
        $request = DB::table('service_requests')->where('public_id', $publicId)->firstOrFail();
        $file = DB::table('service_request_files')->where('id', $fileId)
            ->where('service_request_id', $request->id)->firstOrFail();
        $contents = $this->objects->get($file->object_key);
        abort_unless(hash_equals($file->sha256, hash('sha256', $contents)), 500, 'Private enquiry file integrity check failed.');
        IdentityAudit::record('admin', $actor->id, 'digital_lead_file_downloaded', 'service-request-file:'.$file->id);

        return [
            'id' => $file->id, 'name' => $file->original_name, 'mime_type' => $file->mime_type,
            'byte_size' => (int) $file->byte_size, 'sha256' => $file->sha256, 'contents' => $contents,
        ];
    }

    private function syncOffers(string $table, int $serviceId, array $rows): void
    {
        $seen = [];
        foreach ($rows as $index => $row) {
            abort_unless(is_array($row), 422, 'Service offers must be objects.');
            $this->fields($row, ['code', 'name', 'pricing_type', 'price', 'active', 'sort_order']);
            $data = Validator::make($row, [
                'code' => 'required|string|max:80', 'name' => 'required|string|max:190',
                'pricing_type' => 'required|string|in:fixed,starting_from,quote', 'price' => 'nullable',
                'active' => 'nullable|boolean', 'sort_order' => 'nullable|integer|min:0|max:10000',
            ])->validate();
            $code = Str::slug($data['code']);
            abort_if($code === '' || isset($seen[$code]), 422, 'Service offer codes must be unique and valid.');
            $seen[$code] = true;
            $price = $this->money($data['price'] ?? null);
            $this->assertOfferPrice($data['pricing_type'], $price);
            $existing = DB::table($table)->where('digital_service_id', $serviceId)->where('code', $code)->lockForUpdate()->first();
            $values = ['name' => trim($data['name']), 'pricing_type' => $data['pricing_type'], 'price' => $price,
                'active' => (bool) ($data['active'] ?? true), 'sort_order' => (int) ($data['sort_order'] ?? $index), 'updated_at' => now()];
            if ($existing) {
                DB::table($table)->where('id', $existing->id)->update($values + ['version' => (int) $existing->version + 1]);
            } else {
                DB::table($table)->insert($values + ['public_id' => (string) Str::uuid(), 'digital_service_id' => $serviceId,
                    'code' => $code, 'version' => 1, 'created_at' => now()]);
            }
        }
        DB::table($table)->where('digital_service_id', $serviceId)->when($seen, fn ($q) => $q->whereNotIn('code', array_keys($seen)))
            ->where('active', true)->update(['active' => false, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
    }

    private function offerRows(string $table, int $serviceId): array
    {
        return DB::table($table)->where('digital_service_id', $serviceId)->where('active', true)
            ->orderBy('sort_order')->orderBy('id')->get()->map(fn ($row) => [
                'public_id' => $row->public_id, 'code' => $row->code, 'name' => $row->name,
                'pricing_type' => $row->pricing_type, 'price' => $row->price, 'currency' => 'PKR',
                'version' => (int) $row->version,
            ])->all();
    }

    private function servicePayload(int $serviceId): array
    {
        $service = DB::table('digital_services')->where('id', $serviceId)->firstOrFail();

        return [
            'id' => (int) $service->id, 'slug' => $service->slug, 'name' => $service->name,
            'price_type' => $service->price_type, 'price' => $service->price, 'is_active' => (bool) $service->is_active,
            'packages' => $this->offerRows('digital_service_packages', $serviceId),
            'addons' => $this->offerRows('digital_service_addons', $serviceId),
        ];
    }

    private function resolveSelections(int $serviceId, array $data): array
    {
        $rows = [];
        if (! empty($data['package_public_id'])) {
            $package = DB::table('digital_service_packages')->where('public_id', $data['package_public_id'])
                ->where('digital_service_id', $serviceId)->where('active', true)->lockForUpdate()->firstOrFail();
            $rows[] = $this->selection('package', $package);
        }
        foreach (array_values(array_unique($data['addon_public_ids'] ?? [])) as $publicId) {
            $addon = DB::table('digital_service_addons')->where('public_id', $publicId)
                ->where('digital_service_id', $serviceId)->where('active', true)->lockForUpdate()->firstOrFail();
            $rows[] = $this->selection('addon', $addon);
        }
        $service = DB::table('digital_services')->where('id', $serviceId)->firstOrFail();
        if ($service->price_type === 'package' && ! collect($rows)->contains(fn ($row) => $row['selection_type'] === 'package')) {
            throw ValidationException::withMessages(['package_public_id' => 'A package must be selected for this service.']);
        }

        return $rows;
    }

    private function selection(string $type, object $row): array
    {
        return [
            'selection_type' => $type, 'source_id' => (int) $row->id, 'label_snapshot' => $row->name,
            'pricing_type' => $row->pricing_type, 'price_snapshot' => $row->price,
            'currency' => 'PKR', 'source_version' => (int) $row->version,
        ];
    }

    private function consultationSelection(array $data): array
    {
        $requested = (bool) ($data['consultation_requested'] ?? false);
        $hasWindow = ! empty($data['preferred_window_start']) || ! empty($data['preferred_window_end']);
        if (! $requested && ! $hasWindow) {
            return [null, null, null, false];
        }
        $timezone = $data['preferred_timezone'] ?? null;
        abort_unless($timezone && ! empty($data['preferred_window_start']) && ! empty($data['preferred_window_end']), 422,
            'Preferred timezone, window start and window end are required together.');
        try {
            $tz = new DateTimeZone($timezone);
            $start = CarbonImmutable::parse($data['preferred_window_start'], $tz);
            $end = CarbonImmutable::parse($data['preferred_window_end'], $tz);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['preferred_window_start' => 'Invalid callback/consultation time window.']);
        }
        abort_unless($end->greaterThan($start) && $end->diffInHours($start) <= 8, 422, 'Invalid callback/consultation time window.');
        if ($requested) {
            $this->capabilities->assertCreationAllowed('consultation.create');
            $setting = DB::table('digital_consultation_settings')->where('id', 1)->lockForUpdate()->first();
            abort_unless($setting && $setting->enabled, 422, 'Consultation booking is not currently enabled.');
            $availability = json_decode($setting->weekly_availability ?: '[]', true, flags: JSON_THROW_ON_ERROR);
            abort_unless($this->withinAvailability($start, $end, $availability, $setting->timezone), 422,
                'Requested consultation window is outside configured availability.');
        }

        return [$start->utc(), $end->utc(), $timezone, $requested];
    }

    private function withinAvailability(CarbonImmutable $start, CarbonImmutable $end, array $rows, string $timezone): bool
    {
        if ($rows === []) {
            return true;
        }
        $localStart = $start->setTimezone($timezone);
        $localEnd = $end->setTimezone($timezone);
        if ($localStart->toDateString() !== $localEnd->toDateString()) {
            return false;
        }
        foreach ($rows as $row) {
            if ((int) $row['day'] !== $localStart->isoWeekday()) {
                continue;
            }
            $open = CarbonImmutable::parse($localStart->toDateString().' '.$row['start'], $timezone);
            $close = CarbonImmutable::parse($localStart->toDateString().' '.$row['end'], $timezone);
            if ($localStart->greaterThanOrEqualTo($open) && $localEnd->lessThanOrEqualTo($close)) {
                return true;
            }
        }

        return false;
    }

    private function availability(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            abort_unless(is_array($row), 422, 'Consultation availability entries must be objects.');
            $this->fields($row, ['day', 'start', 'end']);
            $day = (int) ($row['day'] ?? 0);
            $start = (string) ($row['start'] ?? '');
            $end = (string) ($row['end'] ?? '');
            abort_unless($day >= 1 && $day <= 7 && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $start)
                && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $end) && $end > $start, 422,
                'Invalid consultation availability entry.');
            $clean[] = ['day' => $day, 'start' => $start, 'end' => $end];
        }

        return $clean;
    }

    private function claimIdempotency(string $fingerprint, string $key, string $requestHash): array
    {
        $scope = 'public-digital-lead:'.$fingerprint;
        $created = DB::table('idempotency_requests')->insertOrIgnore([
            'actor_scope' => $scope, 'operation' => 'service_request.submit', 'key' => $key,
            'request_hash' => $requestHash, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = DB::table('idempotency_requests')->where('actor_scope', $scope)
            ->where('operation', 'service_request.submit')->where('key', $key)->lockForUpdate()->firstOrFail();
        abort_unless(hash_equals($row->request_hash, $requestHash), 409, 'Enquiry idempotency key was reused with different input.');
        if (! $created) {
            abort_unless($row->status === 'completed' && $row->response, 409, 'Enquiry submission is already being processed.');

            return ['id' => (int) $row->id, 'replay' => true,
                'response' => json_decode($row->response, true, flags: JSON_THROW_ON_ERROR)];
        }

        return ['id' => (int) $row->id, 'replay' => false, 'response' => null];
    }

    private function consumeRate(string $fingerprint): void
    {
        $bucket = now()->startOfHour();
        DB::table('service_request_rate_buckets')->insertOrIgnore([
            'submission_fingerprint' => $fingerprint, 'bucket_start' => $bucket, 'request_count' => 0,
        ]);
        $row = DB::table('service_request_rate_buckets')->where('submission_fingerprint', $fingerprint)
            ->where('bucket_start', $bucket)->lockForUpdate()->firstOrFail();
        if ((int) $row->request_count >= 5) {
            throw ValidationException::withMessages(['request' => 'Too many enquiries were submitted recently.']);
        }
        DB::table('service_request_rate_buckets')->where('submission_fingerprint', $fingerprint)
            ->where('bucket_start', $bucket)->update(['request_count' => (int) $row->request_count + 1]);
    }

    private function validateFiles(array $files): array
    {
        abort_if(count($files) > 3, 422, 'At most three reference files are allowed.');
        $total = 0;
        $clean = [];
        foreach ($files as $file) {
            abort_unless(is_array($file) && is_string($file['name'] ?? null) && is_string($file['contents'] ?? null), 422,
                'Reference files require a name and binary contents.');
            $name = trim(basename($file['name']));
            abort_if($name === '' || mb_strlen($name) > 255 || preg_match('/[\x00-\x1F\x7F]/', $name), 422, 'Invalid reference filename.');
            $contents = $file['contents'];
            $size = strlen($contents);
            $total += $size;
            abort_if($size < 1 || $size > 5 * 1024 * 1024 || $total > 10 * 1024 * 1024, 422, 'Reference file size limit exceeded.');
            $mime = strtolower((string) ((new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: ''));
            abort_unless(isset(self::FILE_MIMES[$mime]), 422, 'Unsupported reference file type.');
            if (str_starts_with($mime, 'image/')) {
                abort_unless(@getimagesizefromstring($contents) !== false, 422, 'Invalid reference image.');
            }
            $clean[] = ['name' => $name, 'contents' => $contents, 'size' => $size, 'mime' => $mime,
                'extension' => self::FILE_MIMES[$mime], 'sha256' => hash('sha256', $contents)];
        }

        return $clean;
    }

    private function leadPayload(int $requestId): array
    {
        $request = DB::table('service_requests as r')->leftJoin('digital_services as s', 's.id', '=', 'r.digital_service_id')
            ->where('r.id', $requestId)->select('r.*', 's.slug as service_slug', 's.name as service_name')->firstOrFail();
        $detail = DB::table('service_request_details')->where('service_request_id', $requestId)->firstOrFail();
        $selections = DB::table('service_request_selections')->where('service_request_id', $requestId)->orderBy('id')->get()
            ->map(fn ($row) => ['type' => $row->selection_type, 'label' => $row->label_snapshot,
                'pricing_type' => $row->pricing_type, 'price' => $row->price_snapshot, 'currency' => $row->currency,
                'source_version' => (int) $row->source_version])->all();
        $files = DB::table('service_request_files')->where('service_request_id', $requestId)->orderBy('uploaded_at')->get()
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->original_name, 'mime_type' => $row->mime_type,
                'byte_size' => (int) $row->byte_size, 'sha256' => $row->sha256])->all();
        $events = DB::table('service_request_events')->where('service_request_id', $requestId)->orderBy('id')->get()
            ->map(fn ($row) => ['type' => $row->event_type, 'actor_admin_id' => $row->actor_admin_id,
                'snapshot' => json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR), 'occurred_at' => $row->occurred_at])->all();

        return [
            'public_id' => $request->public_id, 'reference' => $request->reference, 'version' => (int) $request->version,
            'status' => $request->status, 'service' => ['slug' => $request->service_slug, 'name' => $request->service_name],
            'customer' => ['name' => $request->customer_name, 'business_name' => $request->business_name,
                'mobile' => $request->customer_mobile, 'email' => $request->customer_email],
            'requirements' => $request->requirements, 'preferred_contact' => $request->preferred_contact,
            'project_type' => $detail->project_type, 'existing_url' => $detail->existing_url, 'budget_range' => $detail->budget_range,
            'preferred_timeline' => $detail->preferred_timeline, 'assigned_admin_id' => $detail->assigned_admin_id,
            'follow_up_at' => $detail->follow_up_at, 'consultation_status' => $detail->consultation_status,
            'preferred_timezone' => $detail->preferred_timezone, 'preferred_window_start_utc' => $detail->preferred_window_start_utc,
            'preferred_window_end_utc' => $detail->preferred_window_end_utc, 'selections' => $selections,
            'reference_files' => $files, 'events' => $events,
        ];
    }

    private function event(int $requestId, string $type, ?int $actorAdminId, array $snapshot): void
    {
        $json = json_encode($this->canonical($snapshot), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        DB::table('service_request_events')->insert([
            'service_request_id' => $requestId, 'event_type' => $type, 'actor_admin_id' => $actorAdminId,
            'snapshot' => $json, 'snapshot_sha256' => hash('sha256', $json), 'occurred_at' => now(),
        ]);
    }

    private function bump(string $domain): void
    {
        DB::table('publication_versions')->insertOrIgnore(['domain' => $domain, 'version' => 0, 'updated_at' => now()]);
        DB::table('publication_versions')->where('domain', $domain)->increment('version', 1, ['updated_at' => now()]);
    }

    private function assertBasePrice(string $type, mixed $price): void
    {
        abort_unless(in_array($type, self::PRICE_TYPES, true), 422, 'Invalid Digital Service price type.');
        if (in_array($type, ['fixed', 'starting_from'], true)) {
            abort_unless(is_string($price) && $this->money($price) !== null, 422, 'Fixed/starting-from services require an exact price.');
        } else {
            abort_unless($price === null, 422, 'Quote/package services cannot own a base price.');
        }
    }

    private function assertOfferPrice(string $type, ?string $price): void
    {
        abort_unless(in_array($type, ['fixed', 'starting_from', 'quote'], true), 422, 'Invalid service offer price type.');
        if ($type === 'quote') {
            abort_unless($price === null, 422, 'Quote-based offers cannot contain a price.');
        } else {
            abort_unless($price !== null, 422, 'Priced offers require an exact amount.');
        }
    }

    private function money(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || ! preg_match('/\A(?:0|[1-9][0-9]{0,16})\.[0-9]{2}\z/', $value)
            || bccomp($value, '0.00', 2) <= 0) {
            throw ValidationException::withMessages(['price' => 'Use a positive exact decimal amount such as 12500.00.']);
        }

        return $value;
    }

    private function utcInstant(mixed $value, string $field): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse((string) $value)->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Invalid timezone-aware date/time.']);
        }
    }

    private function authorize(Admin $actor, string $permission): void
    {
        abort_unless($this->access->allows($actor, $permission), 403);
    }

    private function fields(array $input, array $allowed): void
    {
        $extra = array_diff(array_keys($input), $allowed);
        abort_if($extra !== [], 422, 'Unknown input fields: '.implode(', ', $extra));
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function digest(mixed $value): string
    {
        return hash('sha256', json_encode($this->canonical($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->canonical($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonical($item);
        }

        return $value;
    }
}
