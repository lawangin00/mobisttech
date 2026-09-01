<?php

namespace App\Warranty;

use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Identity\IdentityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

final class WarrantyClauses
{
    public const DOMAIN = 'pos.warranty_clauses';

    public const MAX_CLAUSES = 20;

    public function snapshot(): array
    {
        $revision = DB::table('pos_configuration_revisions')->where('domain', self::DOMAIN)->where('state', 'published')
            ->orderByDesc('version')->first();
        $snapshot = $revision ? json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR) : $this->defaults();
        $snapshot = $this->normalize($snapshot);
        $snapshot['version'] = $revision ? (int) $revision->version : 0;
        $snapshot['sha256'] = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $snapshot;
    }

    public function publish(IdentityAccount $actor, string $key, array $input): array
    {
        return DB::transaction(function () use ($actor, $key, $input) {
            $fresh = $actor->fresh();
            abort_unless($fresh && app(Access::class)->allows($fresh, 'config.documents.manage'), 403);
            Validator::make(['key' => $key], ['key' => 'required|string|max:100'])->validate();
            $snapshot = $this->normalize($input);
            $digest = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $identity = ['actor_scope' => $actor::class.':'.$actor->id, 'operation' => 'warranty.clauses.publish', 'key' => $key];
            DB::table('idempotency_requests')->insertOrIgnore([...$identity, 'request_hash' => $digest, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now()]);
            $request = DB::table('idempotency_requests')->where($identity)->lockForUpdate()->firstOrFail();
            if (! hash_equals($request->request_hash, $digest)) {
                throw new LogicException('Idempotency key was already used for different warranty clauses.');
            }
            if ($request->status === 'completed') {
                return json_decode($request->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $last = DB::table('pos_configuration_revisions')->where('domain', self::DOMAIN)->orderByDesc('version')->lockForUpdate()->first();
            $version = ($last?->version ?? 0) + 1;
            DB::table('pos_configuration_revisions')->insert(['domain' => self::DOMAIN, 'version' => $version, 'state' => 'published',
                'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'created_by_type' => $actor::class, 'created_by_id' => $actor->id, 'published_by_type' => $actor::class,
                'published_by_id' => $actor->id, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $response = $this->snapshot();
            IdentityAudit::record('admin', $actor->id, 'warranty_clauses_published', 'version:'.$version);
            DB::table('idempotency_requests')->where('id', $request->id)->update(['status' => 'completed',
                'response' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'resource_type' => 'pos_configuration_revision', 'updated_at' => now()]);

            return $response;
        }, 3);
    }

    private function normalize(array $input): array
    {
        if (array_diff(array_keys($input), ['show', 'title', 'clauses'])) {
            throw ValidationException::withMessages(['input' => 'Unexpected warranty clause fields.']);
        }
        $data = Validator::make($input, ['show' => 'required|boolean', 'title' => 'required|string|max:255',
            'clauses' => 'required|array|max:'.self::MAX_CLAUSES, 'clauses.*.id' => ['required', 'string', 'max:80', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/'],
            'clauses.*.enabled' => 'required|boolean', 'clauses.*.order' => 'required|integer|min:1|max:'.self::MAX_CLAUSES,
            'clauses.*.text' => 'required|string|max:4000'])->validate();
        if (trim($data['title']) === '' || strip_tags($data['title']) !== $data['title']) {
            throw ValidationException::withMessages(['title' => 'Warranty title must be non-empty plain text.']);
        }
        $clauses = collect($data['clauses'])->map(function (array $row) {
            $text = trim($row['text']);
            if ($text === '' || strip_tags($text) !== $text || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text)) {
                throw ValidationException::withMessages(['clauses' => 'Warranty clauses must be non-empty plain text.']);
            }

            return ['id' => $row['id'], 'enabled' => (bool) $row['enabled'], 'order' => (int) $row['order'], 'text' => $text];
        })->sortBy(fn (array $row) => [$row['order'], $row['id']])->values();
        if ($clauses->pluck('id')->duplicates()->isNotEmpty() || $clauses->pluck('order')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['clauses' => 'Warranty clause IDs and order values must be unique.']);
        }

        return ['show' => (bool) $data['show'], 'title' => trim($data['title']), 'clauses' => $clauses->all()];
    }

    private function defaults(): array
    {
        return ['show' => true, 'title' => 'Warranty Terms', 'clauses' => [
            ['id' => 'legacy-1', 'enabled' => true, 'order' => 1, 'text' => 'خریدا ہوا مال واپس نہیں ہوگا۔'],
            ['id' => 'legacy-2', 'enabled' => true, 'order' => 2, 'text' => 'ایکسچینج پر آئٹم / پروڈکٹ کے حساب سے 20% تا 40% تک کٹوتی ہوگی۔'],
            ['id' => 'legacy-3', 'enabled' => true, 'order' => 3, 'text' => 'ٹچ اسکرین یا کیمرہ ڈیڈ ہونے کی کوئی وارنٹی نہیں۔'],
        ]];
    }
}
