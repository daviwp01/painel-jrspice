<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\ContractProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractFilterPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();

        $this->master = User::factory()->master()->create();
    }

    public function test_first_visit_without_filters_does_not_redirect(): void
    {
        $this->actingAs($this->master)
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertCookieMissing('contract_filters');
    }

    public function test_applying_filters_remembers_them_for_the_user(): void
    {
        $this->actingAs($this->master)
            ->get(route('admin.clients.index', [
                'status' => ['Invoice ENVIADA'],
                'contract_number' => '1374',
                'sort_by' => 'date',
                'sort_dir' => 'asc',
            ]))
            ->assertOk()
            ->assertCookie('contract_filters');

        $expected = [(string) $this->master->id => [
            'status' => ['Invoice ENVIADA'],
            'contract_number' => '1374',
            'sort_by' => 'date',
            'sort_dir' => 'asc',
        ]];

        $this->withCookie('contract_filters', json_encode($expected, JSON_UNESCAPED_UNICODE))
            ->actingAs($this->master)
            ->get(route('admin.clients.index'))
            ->assertRedirect(route('admin.clients.index', [
                'status' => ['Invoice ENVIADA'],
                'contract_number' => '1374',
                'sort_by' => 'date',
                'sort_dir' => 'asc',
            ]));
    }

    public function test_restored_filters_are_actually_applied_to_the_query(): void
    {
        $this->makeContract('1374', 'Invoice ENVIADA');
        $this->makeContract('1375', 'Processo FINALIZADO');

        $saved = [(string) $this->master->id => ['status' => 'Invoice ENVIADA']];

        $this->withCookie('contract_filters', json_encode($saved))
            ->actingAs($this->master)
            ->get(route('admin.clients.index'))
            ->assertRedirect();

        // following the redirect keeps the filters in the URL and in the results
        $this->assertDatabaseCount('contracts', 2);

        $this->withCookie('contract_filters', json_encode($saved))
            ->actingAs($this->master)
            ->get(route('admin.clients.index', ['status' => 'Invoice ENVIADA']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rows.total', 1)
                ->where('rows.data.0.contract_number', '1374')
            );
    }

    public function test_clearing_filters_forgets_the_saved_one(): void
    {
        $saved = [(string) $this->master->id => ['status' => 'Invoice ENVIADA']];

        $this->withCookie('contract_filters', json_encode($saved))
            ->actingAs($this->master)
            ->get(route('admin.clients.index', ['clear_filters' => 1]))
            ->assertOk();

        // the cookie is expired, so the next visit starts clean
        $this->actingAs($this->master)
            ->get(route('admin.clients.index'))
            ->assertCookieExpired('contract_filters');
    }

    public function test_filters_are_scoped_to_each_user(): void
    {
        $other = User::factory()->master()->create();

        $mine = [(string) $this->master->id => ['contract_number' => 'SO-MEUS']];

        $response = $this->withCookie('contract_filters', json_encode($mine, JSON_UNESCAPED_UNICODE))
            ->actingAs($other)
            ->get(route('admin.clients.index'));

        // the other user has nothing remembered, so no redirect is issued
        $response->assertOk();
    }

    public function test_malformed_cookie_is_ignored(): void
    {
        $this->withCookie('contract_filters', 'nao e json {')
            ->actingAs($this->master)
            ->get(route('admin.clients.index'))
            ->assertOk();
    }

    private function makeContract(string $number, string $status): void
    {
        $exporter = Client::firstOrCreate(
            ['name' => 'Golden Spices', 'type' => 'exporter'],
            ['country' => 'Brazil'],
        );
        $importer = Client::firstOrCreate(
            ['name' => 'Expin', 'type' => 'importer'],
            ['country' => 'India'],
        );

        $product = ContractProduct::firstOrCreate(
            ['normalized_name' => 'alhogranulado'],
            ['name' => 'Alho Granulado'],
        );

        $contract = Contract::create([
            'date' => '2026-01-10',
            'contract_number' => $number,
            'exporter_id' => $exporter->id,
            'importer_id' => $importer->id,
            'country' => 'Brazil',
            'status' => $status,
        ]);

        ContractItem::create([
            'contract_id' => $contract->id,
            'position' => 1,
            'product_id' => $product->id,
            'quantity_tons' => 10,
            'price_per_ton_usd' => 2000,
            'sales_usd' => 20000,
        ]);
    }
}
