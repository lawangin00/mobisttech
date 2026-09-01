<?php

namespace App\Business;

use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BusinessProfile
{
    public function current(): array
    {
        $profile = DB::table('business_profiles')->where('id', 1)->firstOrFail();

        return ['business_name' => $profile->business_name, 'business_email' => $profile->business_email,
            'public_website' => $profile->public_website, 'version' => (int) $profile->version];
    }

    public function update(Admin $actor, array $input): array
    {
        abort_unless(app(Access::class)->allows($actor, 'admin.business-profile.manage'), 403);
        if (array_diff(array_keys($input), ['business_name', 'business_email', 'public_website'])) {
            throw ValidationException::withMessages(['input' => 'Unexpected business profile fields.']);
        }
        validator($input, ['business_name' => ['required', 'string', 'max:255'], 'business_email' => ['required', 'email:rfc', 'max:255'],
            'public_website' => ['required', 'url:https', 'max:255']])->validate();
        $values = ['business_name' => trim($input['business_name']), 'business_email' => strtolower(trim($input['business_email'])),
            'public_website' => rtrim(trim($input['public_website']), '/')];
        if ($values !== ['business_name' => 'mobiST Technologies', 'business_email' => 'mobisttech@gmail.com', 'public_website' => 'https://mobisttech.com']) {
            throw ValidationException::withMessages(['business_profile' => 'The approved canonical business identity cannot be changed by this checkpoint.']);
        }
        DB::transaction(function () use ($actor, $values) {
            $profile = DB::table('business_profiles')->where('id', 1)->lockForUpdate()->firstOrFail();
            DB::table('business_profiles')->where('id', 1)->update([...$values, 'version' => $profile->version + 1,
                'updated_by_admin_id' => $actor->id, 'updated_at' => now()]);
            IdentityAudit::record('admin', $actor->id, 'business_profile_updated', 'version:'.($profile->version + 1));
        });

        return $this->current();
    }
}
