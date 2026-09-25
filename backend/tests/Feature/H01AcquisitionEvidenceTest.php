<?php

namespace Tests\Feature;

use App\Infrastructure\PrivateObjects;
use App\Models\Outlet;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class H01AcquisitionEvidenceTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
        // Test-only: exercise many negative requests without consuming the shared identity limiter.
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
        $this->inventoryFixture();
        $this->actor->forceFill(['password' => Hash::make('SyntheticPass123!')])->save();
    }

    public function test_private_acquisition_front_back_requires_inventory_outlet_and_returns_only_authorized_image_bytes(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $row = DB::table('stock_acquisitions')->where('product_id', $product->id)->firstOrFail();
        $base = '/internal/admin/pos/inventory/acquisitions/'.$row->id.'/evidence/';
        $png = file_get_contents(public_path('icon-192.png'));
        $this->assertSame('image/png', getimagesizefromstring($png)['mime']);
        $guest = $this->client();
        $this->send($guest, 'GET', $base.'front')->assertUnauthorized();
        $this->send($guest, 'POST', $base.'front', ['image_base64' => base64_encode($png)])->assertUnauthorized();
        $client = $this->authenticatedClient();
        $this->send($client, 'GET', $base.'front')->assertNotFound();
        $this->send($client, 'POST', $base.'sideways', ['image_base64' => base64_encode($png)])->assertNotFound();
        $this->send($client, 'POST', $base.'front', ['image_base64' => 'not-base64'])->assertUnprocessable();
        $this->send($client, 'POST', $base.'front', ['image_base64' => base64_encode('<svg></svg>')])->assertUnprocessable();
        $this->send($client, 'POST', $base.'front', ['image_base64' => base64_encode($png)], false)->assertStatus(419);
        // DatabaseTransactions intentionally prevents the upload API's out-of-transaction object write.
        // Exercise its positive upload in the separate owned real-browser test; seed read path privately here.
        $key = 'acquisitions/'.Str::uuid().'.png';
        app(PrivateObjects::class)->put($key, $png, hash('sha256', $png));
        DB::table('stock_acquisitions')->where('id', $row->id)->update(['cnic_front_path' => $key]);
        $stored = DB::table('stock_acquisitions')->where('id', $row->id)->firstOrFail();
        $this->assertStringStartsWith('acquisitions/', $stored->cnic_front_path);
        $this->assertTrue(Storage::disk('local')->exists($stored->cnic_front_path));
        $this->send($client, 'GET', $base.'front')->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'attachment; filename="acquisition-'.$row->id.'-front.png"');
        $this->assertSame($png, $this->send($client, 'GET', $base.'front')->getContent());
        $this->send($client, 'GET', $base.'back')->assertNotFound();
        $payload = $this->send($client, 'GET', '/internal/admin/pos/catalogue?mode=inventory')->assertOk();
        $payload->assertJsonPath('data.products.0.acquisitions.0.id', $row->id)
            ->assertJsonPath('data.products.0.acquisitions.0.has_front', true)
            ->assertJsonPath('data.products.0.acquisitions.0.has_back', false);
        $this->assertStringNotContainsString($stored->cnic_front_path, $payload->getContent());
        $second = new Outlet;
        $second->forceFill(['name' => 'H01 evidence foreign outlet', 'outlet_code' => 'X99', 'public_id' => (string) Str::uuid()])->save();
        $this->actor->shops()->attach($second);
        $this->send($client, 'POST', '/internal/admin/outlets/select', ['outlet_id' => $second->public_id])->assertOk();
        $this->send($client, 'GET', $base.'front')->assertNotFound();
        $this->send($client, 'POST', $base.'back', ['image_base64' => base64_encode($png)])->assertNotFound();
        $this->assertNull(DB::table('stock_acquisitions')->where('id', $row->id)->value('cnic_back_path'));
    }

    private function authenticatedClient(): array
    {
        $client = $this->client();
        $this->send($client, 'POST', '/internal/admin/auth/login', [
            'email' => $this->actor->email, 'password' => 'SyntheticPass123!',
        ])->assertOk();
        $this->send($client, 'POST', '/internal/admin/outlets/select', [
            'outlet_id' => $this->outlet->public_id,
        ])->assertOk();

        return $client;
    }

    private function client(): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic MT-4.2 browser'];
        $this->send($client, 'GET', '/internal/admin/auth/csrf-cookie')->assertOk();

        return $client;
    }

    private function send(
        array &$client,
        string $method,
        string $uri,
        array $data = [],
        bool $csrf = true,
        array $extraServer = [],
    ) {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => $client['agent'],
            ...$extraServer,
        ];
        if ($csrf && isset($client['tokens']['XSRF-TOKEN-admin'])) {
            $server['HTTP_X_CSRF_TOKEN'] = $client['tokens']['XSRF-TOKEN-admin'];
        }
        $response = $this->call($method, $uri, [], $client['cookies'], [], $server, json_encode($data));
        foreach ($response->headers->getCookies() as $cookie) {
            $client['cookies'][$cookie->getName()] = $cookie->getValue();
            if (str_starts_with($cookie->getName(), 'XSRF-TOKEN')) {
                $client['tokens'][$cookie->getName()] = CookieValuePrefix::remove(
                    app('encrypter')->decrypt($cookie->getValue(), false),
                );
            }
        }

        return $response;
    }
}
