<?php

namespace App\Identity;

use App\Models\Admin;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OutletProfileAdministration
{
    private const FIELDS = ['name', 'business_legal_name', 'business_phone', 'business_whatsapp', 'business_address', 'business_hours'];

    public function show(Admin $actor, Outlet $outlet): array
    {
        abort_unless(app(Access::class)->allows($actor->fresh(), 'shop.profile', $outlet->fresh()), 403);
        return $this->view($outlet->fresh());
    }

    public function update(Admin $actor, Outlet $outlet, array $input): array
    {
        if (array_diff(array_keys($input), [...self::FIELDS, 'version'])) {
            throw ValidationException::withMessages(['input' => 'Unexpected outlet profile fields.']);
        }
        $data = validator($input, [
            'version' => ['required', 'integer', 'min:1'], 'name' => ['required', 'string', 'max:160'],
            'business_legal_name' => ['nullable', 'string', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:40'], 'business_whatsapp' => ['nullable', 'string', 'max:40'],
            'business_address' => ['nullable', 'string', 'max:1000'], 'business_hours' => ['nullable', 'string', 'max:1000'],
        ])->validate();
        $data['name'] = trim($data['name']);
        if ($data['name'] === '') {
            throw ValidationException::withMessages(['name' => 'Outlet name is required.']);
        }
        foreach (['business_phone', 'business_whatsapp'] as $field) {
            $value = preg_replace('/\D+/', '', (string) ($data[$field] ?? ''));
            if (str_starts_with($value, '92') && strlen($value) === 12) {
                $value = '0'.substr($value, 2);
            }
            if ($value !== '' && ! preg_match('/^03\d{9}$/', $value)) {
                throw ValidationException::withMessages([$field => 'Enter a valid Pakistani mobile number.']);
            }
            $data[$field] = $value === '' ? null : $value;
        }
        foreach (['business_legal_name', 'business_address', 'business_hours'] as $field) {
            $data[$field] = trim((string) ($data[$field] ?? '')) ?: null;
        }
        return DB::transaction(function () use ($actor, $outlet, $data) {
            $locked = Outlet::whereKey($outlet->id)->lockForUpdate()->firstOrFail();
            abort_unless(app(Access::class)->allows($actor->fresh(), 'shop.profile', $locked), 403);
            abort_if((int) $locked->version !== (int) $data['version'], 409, 'Outlet profile has changed; reload before saving.');
            $values = array_intersect_key($data, array_flip(self::FIELDS));
            $locked->forceFill([...$values, 'version' => $locked->version + 1])->save();
            IdentityAudit::record('admin', $actor->id, 'outlet_profile_updated', 'outlet:'.$locked->public_id.':v'.$locked->version, $locked->id);
            return $this->view($locked);
        });
    }

    private function view(Outlet $outlet): array
    {
        return [
            'id' => $outlet->public_id, 'outlet_code' => $outlet->outlet_code,
            'version' => (int) $outlet->version,
            'name' => $outlet->name, 'business_legal_name' => $outlet->business_legal_name,
            'business_phone' => $outlet->business_phone, 'business_whatsapp' => $outlet->business_whatsapp,
            'business_address' => $outlet->business_address, 'business_hours' => $outlet->business_hours,
        ];
    }
}
