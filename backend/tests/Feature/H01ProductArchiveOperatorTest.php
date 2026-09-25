<?php

namespace Tests\Feature;

use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class H01ProductArchiveOperatorTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
        $this->inventoryFixture();
        $this->actor->forceFill(['password' => Hash::make('SyntheticPass123!')])->save();
    }

    public function test_p03_source_soft_delete_has_versioned_protected_real_http_control_and_retains_history(): void
    {
        $product = $this->product();
        $url = '/internal/admin/pos/inventory/products/'.$product->public_id.'/archive';
        $input = ['expected_version' => (int) $product->version];
        $key = ['HTTP_IDEMPOTENCY_KEY' => 'h01-archive-'.Str::uuid()];
        $guest = $this->client();
        $this->send($guest, 'POST', $url, $input, true, $key)->assertUnauthorized();
        $client = $this->authenticatedClient();
        $this->send($client, 'POST', $url, $input, false, $key)->assertStatus(419);
        $this->send($client, 'POST', $url, ['expected_version' => 999], true, $key)->assertStatus(409);
        $this->assertFalse($product->fresh()->isDeleted);
        $this->acquire($product);
        $this->send($client, 'POST', $url, ['expected_version' => (int) $product->fresh()->version], true,
            ['HTTP_IDEMPOTENCY_KEY' => 'h01-stock-block-'.Str::uuid()])->assertStatus(409);
        $this->assertFalse($product->fresh()->isDeleted);
        $this->send($client, 'POST', '/internal/admin/pos/inventory/products/'.$product->public_id.'/adjust', [
            'type' => 'correction_out', 'quantity' => 1, 'reason' => 'Synthetic adjustment', 'unit_id' => null,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'h01-adjust-'.Str::uuid()])->assertOk();
        $this->assertSame(0, (int) $product->fresh()->qty);
        $recorded = DB::table('stock_acquisitions')->where('product_id', $product->id)->count();
        $archive = $this->send($client, 'POST', $url, ['expected_version' => (int) $product->fresh()->version], true,
            ['HTTP_IDEMPOTENCY_KEY' => 'h01-final-'.Str::uuid()])->assertOk()->assertJsonPath('data.product_id', $product->public_id);
        $this->assertTrue($product->fresh()->isDeleted);
        $this->assertNotNull($product->fresh()->archived_at);
        $this->assertSame($recorded, DB::table('stock_acquisitions')->where('product_id', $product->id)->count());
        $this->send($client, 'GET', '/internal/admin/pos/lookup?q='.$product->public_id)->assertNotFound();
        $this->send($client, 'POST', $url, ['expected_version' => (int) $product->fresh()->version], true,
            ['HTTP_IDEMPOTENCY_KEY' => 'h01-already-archived-'.Str::uuid()])->assertNotFound();
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
