<?php

namespace App\Identity;

use App\Models\CustomerAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CustomerProfile
{
    private function prefix(CustomerAccount $customer): string
    {
        return 'customer-profile-photos/'.$customer->public_id.'/';
    }

    private function owned(CustomerAccount $customer, ?string $path): bool
    {
        return is_string($path) && str_starts_with($path, $this->prefix($customer))
            && preg_match('/^[a-f0-9-]{36}\.(jpg|png|webp)$/D', substr($path, strlen($this->prefix($customer)))) === 1;
    }

    public function update(CustomerAccount $customer, array $input): array
    {
        if (array_diff(array_keys($input), ['name', 'mobile'])) {
            throw ValidationException::withMessages(['profile' => 'Unexpected customer-profile fields.']);
        }
        $data = validator($input, [
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'regex:/^03[0-9]{9}$/', Rule::unique('users', 'mobile')->ignore($customer->id)],
        ])->validate();

        return DB::transaction(function () use ($customer, $data) {
            $locked = CustomerAccount::query()->lockForUpdate()->findOrFail($customer->id);
            $locked->forceFill(['name' => trim($data['name']), 'mobile' => $data['mobile']])->save();
            DB::table('customers')->where('website_user_id', $locked->id)->update(['display_name' => $locked->name, 'mobile' => $locked->mobile]);
            IdentityAudit::record('customer', $locked->id, 'customer_profile_updated', 'customer:'.$locked->public_id);

            return ['name' => $locked->name, 'mobile' => $locked->mobile];
        });
    }

    public function upload(CustomerAccount $customer, Request $request): array
    {
        if (array_diff(array_keys($request->all()), ['profile_photo'])) {
            throw ValidationException::withMessages(['profile_photo' => 'Unexpected profile-photo fields.']);
        }
        $file = $request->validate(['profile_photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']])['profile_photo'];
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => null,
        };
        if ($extension === null) {
            throw ValidationException::withMessages(['profile_photo' => 'Unsupported image format.']);
        }
        $path = $this->prefix($customer).Str::uuid().'.'.$extension;
        Storage::disk('local')->putFileAs($this->prefix($customer), $file, basename($path));
        try {
            $previous = DB::transaction(function () use ($customer, $path) {
                $locked = CustomerAccount::query()->lockForUpdate()->findOrFail($customer->id);
                $previous = $locked->profile_photo_path;
                $locked->forceFill(['profile_photo_path' => $path])->save();
                IdentityAudit::record('customer', $locked->id, 'customer_profile_photo_updated', 'customer:'.$locked->public_id);

                return $previous;
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        if ($this->owned($customer, $previous) && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }

        return ['photo_url' => '/api/v1/account/photo', 'has_photo' => true];
    }

    public function remove(CustomerAccount $customer): array
    {
        $previous = DB::transaction(function () use ($customer) {
            $locked = CustomerAccount::query()->lockForUpdate()->findOrFail($customer->id);
            $previous = $locked->profile_photo_path;
            $locked->forceFill(['profile_photo_path' => null])->save();
            IdentityAudit::record('customer', $locked->id, 'customer_profile_photo_removed', 'customer:'.$locked->public_id);

            return $previous;
        });
        if ($this->owned($customer, $previous)) {
            Storage::disk('local')->delete($previous);
        }

        return ['has_photo' => false];
    }

    public function show(CustomerAccount $customer)
    {
        $path = $customer->fresh()->profile_photo_path;
        abort_unless($this->owned($customer, $path) && Storage::disk('local')->exists($path), 404);
        $mime = match (pathinfo($path, PATHINFO_EXTENSION)) {
            'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        };

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $mime, 'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
