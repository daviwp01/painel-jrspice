<?php

namespace Tests\Feature\Contracts;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\ContractProduct;
use App\Models\User;
use App\Services\Contracts\ContractQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ContractManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();

        $this->master = User::factory()->master()->create();
    }

    public function test_master_can_view_contract_listing(): void
    {
        $this->actingAs($this->master)
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Clients/Index'));
    }

    public function test_non_master_user_cannot_access_contract_listing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.clients.index'))
            ->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('writeEndpoints')]
    public function test_non_master_user_cannot_write_contracts(string $method, string $routeName): void
    {
        $user = User::factory()->create();
        $contract = $this->makeContract();

        $this->actingAs($user)
            ->call($method, route($routeName, $contract), $this->validPayload())
            ->assertForbidden();

        $this->assertSame(1, Contract::count());
    }

    public static function writeEndpoints(): array
    {
        return [
            'update' => ['put', 'contracts.update'],
            'destroy' => ['delete', 'contracts.destroy'],
            'status' => ['patch', 'contracts.status'],
        ];
    }

    public function test_guest_cannot_write_contracts(): void
    {
        $contract = $this->makeContract();

        $this->post(route('contracts.store'), $this->validPayload())->assertRedirect(route('login'));
        $this->put(route('contracts.update', $contract), $this->validPayload())->assertRedirect(route('login'));
        $this->delete(route('contracts.destroy', $contract))->assertRedirect(route('login'));
    }

    public function test_master_can_create_contract_with_computed_item_values(): void
    {
        $product = ContractProduct::create(['name' => 'Alho Granulado 8/16', 'normalized_name' => 'alhogranulado816']);

        $this->actingAs($this->master)
            ->post(route('contracts.store'), $this->validPayload([
                'items' => [[
                    'register_number' => '45885',
                    'product_id' => $product->id,
                    'quantity_tons' => 25,
                    'price_per_ton_usd' => 1400,
                    'commission_rate' => 3,
                    'to_pay_rate' => 0.9,
                ]],
            ]));

        $contract = Contract::firstWhere('contract_number', '9999');

        $this->assertNotNull($contract);
        $this->assertSame('China', $contract->country);
        $this->assertSame('A embarcar dia', $contract->status);

        $item = $contract->items()->first();
        $this->assertSame(35000.0, (float) $item->sales_usd);
        $this->assertSame(1050.0, (float) $item->commission_usd);
        $this->assertSame(3.0, (float) $item->commission_rate);
        $this->assertSame(315.0, (float) $item->to_pay_usd);
        $this->assertSame(955.5, (float) $item->estimated_euro);
    }

    public function test_unknown_status_is_rejected(): void
    {
        $this->actingAs($this->master)
            ->from(route('admin.clients.index'))
            ->post(route('contracts.store'), $this->validPayload(['status' => 'Qualquer coisa']))
            ->assertSessionHasErrors('status');

        $this->assertSame(0, Contract::count());
    }

    public function test_status_update_does_not_detach_linked_users(): void
    {
        $contract = $this->makeContract();
        $client = User::factory()->create();
        $contract->users()->attach($client->id);

        $this->actingAs($this->master)
            ->patch(route('contracts.status', $contract), [
                'status' => 'Processo FINALIZADO',
                'status_date' => '2026-10-15',
            ])
            ->assertRedirect();

        $this->assertSame('Processo FINALIZADO', $contract->fresh()->status);
        $this->assertSame(1, $contract->users()->count());
    }

    public function test_update_without_user_ids_keeps_existing_links(): void
    {
        $contract = $this->makeContract();
        $client = User::factory()->create();
        $contract->users()->attach($client->id);

        $payload = $this->validPayload();
        unset($payload['user_ids']);

        $this->actingAs($this->master)
            ->put(route('contracts.update', $contract), $payload)
            ->assertRedirect();

        $this->assertSame(1, $contract->users()->count());
    }

    public function test_update_with_explicit_user_ids_replaces_links(): void
    {
        $contract = $this->makeContract();
        $old = User::factory()->create();
        $new = User::factory()->create();
        $contract->users()->attach($old->id);

        $this->actingAs($this->master)
            ->put(route('contracts.update', $contract), $this->validPayload(['user_ids' => [$new->id]]))
            ->assertRedirect();

        $this->assertTrue($contract->users()->where('users.id', $new->id)->exists());
        $this->assertFalse($contract->users()->where('users.id', $old->id)->exists());
    }

    public function test_search_and_status_filter_do_not_leak_rows(): void
    {
        $matching = $this->makeContract(['contract_number' => '500', 'status' => 'Invoice ENVIADA']);
        $this->makeContract(['contract_number' => '501', 'status' => 'Processo FINALIZADO']);
        $this->makeContract(['contract_number' => '502', 'status' => 'Invoice ENVIADA']);

        $response = $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['search' => '500', 'status' => 'Invoice ENVIADA']));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->where('rows.total', 1)
            ->where('rows.data.0.contract_id', $matching->id)
        );
    }

    public function test_bulk_delete_removes_only_selected_contracts(): void
    {
        $keep = $this->makeContract(['contract_number' => '1']);
        $remove = $this->makeContract(['contract_number' => '2']);

        $this->actingAs($this->master)
            ->post(route('contracts.bulk-delete'), ['ids' => [$remove->id]])
            ->assertRedirect();

        $this->assertNotNull($keep->fresh());
        $this->assertNull($remove->fresh());
    }

    public function test_listing_exposes_the_exact_spreadsheet_fields(): void
    {
        $this->makeContract();

        $this->actingAs($this->master)
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Clients/Index')
                ->has('rows.data.0.id')
                ->has('rows.data.0.contract_number')
                ->has('rows.data.0.cumulative_sales_usd')
                ->has('rows.data.0.cumulative_commission_usd')
            );

        $row = collect(app(\App\Services\Contracts\ContractQueryService::class)
            ->paginate(new \Illuminate\Http\Request, 1)
            ->items()
        )->first();

        $this->assertSame([
            'id', 'contract_id', 'item_position', 'date', 'contract_number', 'register_number',
            'exporter', 'importer', 'country', 'product', 'quantity_tons', 'price_per_ton_usd',
            'sales_usd', 'cumulative_sales_usd', 'commission_rate', 'commission_usd',
            'cumulative_commission_usd', 'exchange_rate', 'estimated_euro',
            'estimated_receipt_date', 'seller', 'to_pay_rate', 'to_pay_usd', 'receipt_date',
            'paid_in_date', 'paid_in_brl', 'incident', 'video_sent', 'video_reference',
            'video_date', 'status', 'status_date', 'dhl_date', 'etd_date', 'eta_date',
            'dhl_number', 'observations', 'shipping_company', 'container_number',
        ], collect($row)->keys()->all());
    }

    public function test_delayed_filter_returns_only_overdue_shipments(): void
    {
        $overdue = $this->makeContract([
            'contract_number' => '700',
            'status' => 'A embarcar dia',
            'eta_date' => now()->subDays(3)->toDateString(),
        ]);
        $this->makeContract([
            'contract_number' => '701',
            'status' => 'A embarcar dia',
            'eta_date' => now()->subDays(3)->toDateString(),
            'dhl_number' => 'DHL0001',
        ]);
        $this->makeContract([
            'contract_number' => '702',
            'status' => 'Processo FINALIZADO',
            'eta_date' => now()->subDays(3)->toDateString(),
        ]);
        $this->makeContract([
            'contract_number' => '703',
            'status' => 'A embarcar dia',
            'eta_date' => now()->addDays(30)->toDateString(),
        ]);
        $this->makeContract([
            'contract_number' => '704',
            'status' => 'A embarcar dia',
            'eta_date' => null,
        ]);

        $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['delayed' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rows.total', 1)
                ->where('rows.data.0.contract_id', $overdue->id)
            );
    }

    public function test_delayed_filter_matches_the_stats_card_definition(): void
    {
        $this->makeContract([
            'contract_number' => '800',
            'status' => 'Transbordo até',
            'eta_date' => now()->addDays(5)->toDateString(),
        ]);
        // Status ausente conta como ativo, portanto também como atrasado.
        $this->makeContract([
            'contract_number' => '801',
            'status' => null,
            'eta_date' => now()->addDays(1)->toDateString(),
        ]);

        $response = $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['delayed' => 1]))
            ->assertOk();

        $filteredTotal = $response->assertInertia(fn ($page) => $page->where('rows.total', 2))
            ->viewData('page')['props']['rows']['total'] ?? null;

        $summary = app(\App\Services\Contracts\ContractSummaryService::class)->overview();

        $this->assertSame($summary['delayed_logistics'], (int) $filteredTotal);
    }

    public function test_active_only_filter_includes_contracts_without_status(): void
    {
        $this->makeContract(['contract_number' => '900', 'status' => 'A embarcar dia']);
        $this->makeContract(['contract_number' => '901', 'status' => null]);
        $this->makeContract(['contract_number' => '902', 'status' => 'Processo FINALIZADO']);

        $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['active_only' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('rows.total', 2));
    }

    public function test_status_filter_accepts_multiple_values(): void
    {
        $this->makeContract(['contract_number' => '910', 'status' => 'Invoice ENVIADA']);
        $this->makeContract(['contract_number' => '911', 'status' => 'Transbordo até']);
        $this->makeContract(['contract_number' => '912', 'status' => 'Processo FINALIZADO']);

        $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['status' => ['Invoice ENVIADA', 'Transbordo até']]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('rows.total', 2));
    }

    public function test_structured_filters_target_their_own_columns(): void
    {
        $this->makeContract([
            'contract_number' => '920',
            'country' => 'Egito',
            'date' => '2026-03-10',
        ]);
        $this->makeContract([
            'contract_number' => '921',
            'country' => 'China',
            'date' => '2026-05-20',
        ]);

        $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['contract_number' => '920']))
            ->assertInertia(fn ($page) => $page->where('rows.total', 1)->where('rows.data.0.contract_number', '920'));

        $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['country' => 'Egito']))
            ->assertInertia(fn ($page) => $page->where('rows.total', 1)->where('rows.data.0.country', 'Egito'));

        $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['date_from' => '2026-04-01', 'date_to' => '2026-06-30']))
            ->assertInertia(fn ($page) => $page->where('rows.total', 1)->where('rows.data.0.contract_number', '921'));
    }

    public function test_listing_can_be_sorted_by_date_in_both_directions(): void
    {
        $this->makeContract(['contract_number' => '940', 'date' => '2026-01-05']);
        $this->makeContract(['contract_number' => '941', 'date' => '2026-06-15']);
        $this->makeContract(['contract_number' => '942', 'date' => '2026-03-20']);

        $dates = fn (array $query) => collect(
            app(ContractQueryService::class)
                ->paginate(Request::create('/', 'GET', $query), 10)
                ->items()
        )->pluck('date')->all();

        $this->assertSame(['2026-06-15', '2026-03-20', '2026-01-05'], $dates(['sort_by' => 'date', 'sort_dir' => 'desc']));
        $this->assertSame(['2026-01-05', '2026-03-20', '2026-06-15'], $dates(['sort_by' => 'date', 'sort_dir' => 'asc']));
    }

    public function test_sort_whitelist_ignores_unknown_columns(): void
    {
        $this->makeContract(['contract_number' => '950', 'date' => '2026-02-01']);

        $this->actingAs($this->master)
            ->get(route('admin.clients.index', [
                'sort_by' => 'password; DROP TABLE contracts',
                'sort_dir' => 'asc) --',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.sort_by', 'date')
                ->where('filters.sort_dir', 'desc')
            );

        $this->assertDatabaseHas('contracts', ['contract_number' => '950']);
    }

    public function test_register_number_filter_matches_items(): void
    {
        $this->makeContract(['contract_number' => '930']);
        $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['register_number' => '45885']))
            ->assertInertia(fn ($page) => $page->where('rows.total', 1)->where('rows.data.0.register_number', '45885'));

        $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['register_number' => '99999']))
            ->assertInertia(fn ($page) => $page->where('rows.total', 0));
    }

    public function test_show_payload_carries_every_populated_field_without_phantom_blanks(): void
    {
        $contract = $this->makeContract([
            'contract_number' => '960',
            'status_date' => '2026-04-02',
            'dhl_date' => '2026-04-03',
            'dhl_number' => 'DHL1234567890',
            'etd_date' => '2026-04-04',
            'eta_date' => '2026-05-04',
            'video_sent' => true,
            'video_reference' => '300100/1',
            'video_date' => '2026-04-05',
            'incident' => 'Atraso na descarga',
            'estimated_receipt_date' => '2026-06-01',
            'exchange_rate' => 0.91,
            'receipt_date' => '2026-05-20',
            'paid_in_date' => '2026-05-25',
            'paid_in_brl' => 955.5,
            'observations' => 'Pagamento confirmado',
            'shipping_company' => 'Maersk',
            'container_number' => 'MSMU8204910',
        ]);

        $contract->items()->delete();
        $product = ContractProduct::firstOrCreate(
            ['normalized_name' => 'alhogranulado816'],
            ['name' => 'Alho Granulado 8/16']
        );

        ContractItem::create([
            'contract_id' => $contract->id,
            'position' => 1,
            'register_number' => '55443',
            'product_id' => $product->id,
            'quantity_tons' => 12.5,
            'price_per_ton_usd' => 1800,
            'sales_usd' => 22500,
            'commission_rate' => 3,
            'commission_usd' => 675,
            'exchange_rate' => 0.91,
            'estimated_euro' => 614.25,
            'to_pay_rate' => 0.9,
            'to_pay_usd' => 202.5,
            'etd_date' => '2026-04-06',
            'eta_date' => '2026-05-06',
            'dhl_number' => 'DHL0987654321',
            'status_date' => '2026-04-07',
            'video_date' => '2026-04-08',
            'video_reference' => '300100/2',
            'observations' => 'Item com container próprio',
        ]);

        $payload = $this->actingAs($this->master)
            ->get(route('contracts.show', $contract))
            ->assertOk()
            ->json();

        $expectedContract = [
            'date' => '2026-01-10', 'contract_number' => '960', 'country' => 'China',
            'status' => 'A embarcar dia', 'status_date' => '2026-04-02',
            'dhl_date' => '2026-04-03', 'dhl_number' => 'DHL1234567890',
            'etd_date' => '2026-04-04', 'eta_date' => '2026-05-04',
            'video_reference' => '300100/1', 'video_date' => '2026-04-05',
            'incident' => 'Atraso na descarga', 'estimated_receipt_date' => '2026-06-01',
            'receipt_date' => '2026-05-20', 'paid_in_date' => '2026-05-25',
            'observations' => 'Pagamento confirmado',
            'shipping_company' => 'Maersk', 'container_number' => 'MSMU8204910',
        ];

        foreach ($expectedContract as $field => $value) {
            $this->assertSame($value, $payload[$field], "Campo de contrato '{$field}' veio em branco/divergente.");
        }

        $this->assertTrue($payload['video_sent']);
        $this->assertSame(0.91, (float) $payload['exchange_rate']);
        $this->assertSame(955.5, (float) $payload['paid_in_brl']);

        $item = $payload['items'][0];
        $expectedItem = [
            'register_number' => '55443', 'quantity_tons' => 12.5,
            'price_per_ton_usd' => 1800.0, 'sales_usd' => 22500.0,
            'commission_rate' => 3.0, 'commission_usd' => 675.0,
            'to_pay_rate' => 0.9, 'to_pay_usd' => 202.5,
            'etd_date' => '2026-04-06', 'eta_date' => '2026-05-06',
            'dhl_number' => 'DHL0987654321', 'status_date' => '2026-04-07',
            'video_date' => '2026-04-08', 'video_reference' => '300100/2',
            'observations' => 'Item com container próprio',
        ];

        foreach ($expectedItem as $field => $value) {
            $this->assertNotEmpty($item[$field], "Campo de item '{$field}' veio em branco.");

            // Decimals come back as strings by design (decimal:N cast).
            if (is_numeric($value)) {
                $this->assertEqualsWithDelta((float) $value, (float) $item[$field], 0.001, "Campo de item '{$field}' divergente.");

                continue;
            }

            $this->assertSame($value, $item[$field], "Campo de item '{$field}' divergente.");
        }

        $this->assertSame($product->id, $item['product_id']);
    }

    public function test_show_returns_contract_with_items_and_user_ids(): void
    {
        $contract = $this->makeContract();
        $client = User::factory()->create();
        $contract->users()->attach($client->id);

        $this->actingAs($this->master)
            ->getJson(route('contracts.show', $contract))
            ->assertOk()
            ->assertJsonPath('contract_number', '9999')
            ->assertJsonPath('user_ids.0', $client->id)
            ->assertJsonPath('items.0.product_id', $contract->items()->first()->product_id);
    }

    private function makeContract(array $overrides = []): Contract
    {
        $exporter = Client::firstOrCreate(
            ['name' => 'Golden Spices', 'type' => 'exporter'],
            ['country' => 'China']
        );
        $importer = Client::firstOrCreate(
            ['name' => 'Expin', 'type' => 'importer'],
            ['country' => 'India']
        );
        $product = ContractProduct::firstOrCreate(
            ['normalized_name' => 'cebolachopped'],
            ['name' => 'Cebola Chopped']
        );

        $contract = Contract::create(array_merge([
            'date' => '2026-01-10',
            'contract_number' => '9999',
            'exporter_id' => $exporter->id,
            'importer_id' => $importer->id,
            'country' => 'China',
            'status' => 'A embarcar dia',
        ], $overrides));

        ContractItem::create([
            'contract_id' => $contract->id,
            'position' => 1,
            'register_number' => '45885',
            'product_id' => $product->id,
            'quantity_tons' => 10,
            'price_per_ton_usd' => 2000,
            'sales_usd' => 20000,
            'commission_rate' => 2,
            'commission_usd' => 400,
        ]);

        return $contract;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        $exporter = Client::firstOrCreate(['name' => 'Golden Spices', 'type' => 'exporter'], ['country' => 'China']);
        $importer = Client::firstOrCreate(['name' => 'Expin', 'type' => 'importer'], ['country' => 'India']);

        return array_merge([
            'date' => '2026-01-10',
            'contract_number' => '9999',
            'exporter_id' => $exporter->id,
            'importer_id' => $importer->id,
            'country' => 'China',
            'status' => 'A embarcar dia',
            'exchange_rate' => 0.91,
            'user_ids' => [],
            'items' => [[
                'register_number' => '45885',
                'quantity_tons' => 25,
                'price_per_ton_usd' => 1400,
            ]],
        ], $overrides);
    }
}
