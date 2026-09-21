<?php

namespace App\Identity;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AdminProfilePhoto
{
    private function prefix(Admin $actor): string
    {
        return 'admin-profile-photos/'.$actor->public_id.'/';
    }

    private function owned(Admin $actor, ?string $path): bool
    {
        return is_string($path) && str_starts_with($path, $this->prefix($actor))
            && preg_match('/^[a-f0-9-]{36}\.(jpg|png|webp)$/D', substr($path, strlen($this->prefix($actor)))) === 1;
    }

    public function upload(Admin $actor, Request $request): array
    {
        if (array_diff(array_keys($request->all()), ['profile_photo'])) {
            throw ValidationException::withMessages(['profile_photo' => 'Unexpected profile-photo fields.']);
        }
        $file = $request->validate(['profile_photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']])['profile_photo'];
        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => null
        };
        if ($extension === null) {
            throw ValidationException::withMessages(['profile_photo' => 'Unsupported image format.']);
        }
        $path = $this->prefix($actor).Str::uuid().'.'.$extension;
        Storage::disk('local')->putFileAs($this->prefix($actor), $file, basename($path));
        $previous = null;
        try {
            $previous = DB::transaction(function () use ($actor, $path) {
                $locked = Admin::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->usable(), 403);
                $previous = $locked->profile_photo;
                $locked->forceFill(['profile_photo' => $path])->save();
                IdentityAudit::record('admin', $actor->id, 'admin_profile_photo_updated', 'admin:'.$actor->public_id);

                return $previous;
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        if ($this->owned($actor, $previous) && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }

        return ['photo_url' => '/internal/admin/manage-account/photo', 'has_photo' => true];
    }

    public function remove(Admin $actor): array
    {
        $previous = DB::transaction(function () use ($actor) {
            $locked = Admin::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->usable(), 403);
            $previous = $locked->profile_photo;
            if ($previous !== null) {
                $locked->forceFill(['profile_photo' => null])->save();
                IdentityAudit::record('admin', $actor->id, 'admin_profile_photo_removed', 'admin:'.$actor->public_id);
            }

            return $previous;
        });
        if ($this->owned($actor, $previous)) {
            Storage::disk('local')->delete($previous);
        }

        return ['has_photo' => false];
    }

    public function show(Admin $actor)
    {
        $path = $actor->fresh()->profile_photo;
        abort_unless($this->owned($actor, $path) && Storage::disk('local')->exists($path), 404);
        $mime = match (pathinfo($path, PATHINFO_EXTENSION)) {
            'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'
        };

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $mime, 'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
