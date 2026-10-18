<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\ContractProduct;
use App\Services\Contracts\ContractQueryService;
use App\Models\User;
use App\Support\ContractStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    private Client $exporter;

    private Client $importer;

    private ContractProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->master = User::factory()->master()->create();
        $this->exporter = Client::create(['name' => 'Golden Spices', 'type' => 'exporter', 'country' => 'China']);
        $this->importer = Client::create(['name' => 'Expin', 'type' => 'importer', 'country' => 'India']);
        $this->product = ContractProduct::create(['name' => 'Cebola Chopped', 'normalized_name' => 'cebolachopped']);
    }

    public function test_page_size_defaults_to_fifty(): void
    {
        $this->seedContracts(60);

        $this->actingAs($this->master)
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Clients/Index')
                ->where('filters.per_page', '50')
                ->count('rows.data', 50)
                ->where('rows.total', 60)
            );
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function pageSizes(): iterable
    {
        yield 'minimal' => ['25', 25];
        yield 'default' => ['50', 50];
        yield 'large' => ['100', 100];
        yield 'bigger' => ['200', 200];
        // 500 e "Todos" pedem mais linhas do que as 220 semeadas: a página
        // devolve o que existe, sem nunca estourar o teto.
        yield 'bigger than data' => ['500', 220];
        yield 'all' => [ContractQueryService::PER_PAGE_ALL, 220];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pageSizes')]
    public function test_requested_page_size_is_honoured(string $option, int $expectedRows): void
    {
        $this->seedContracts(220);

        $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['per_page' => $option]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.per_page', $option)
                ->count('rows.data', $expectedRows)
                ->where('rows.total', 220)
            );
    }

    public function test_unknown_page_size_falls_back_to_the_default(): void
    {
        $this->seedContracts(60);

        $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['per_page' => '9999']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.per_page', (string) ContractQueryService::DEFAULT_PER_PAGE)
                ->count('rows.data', ContractQueryService::DEFAULT_PER_PAGE)
            );
    }

    public function test_page_size_never_exceeds_the_hard_ceiling(): void
    {
        $this->assertSame(5000, ContractQueryService::PER_PAGE_CEILING);
        $this->assertLessThan(
            PHP_INT_MAX,
            ContractQueryService::PER_PAGE_CEILING,
            '"Todos" precisa ser um teto, nunca um scan sem limite',
        );
    }

    public function test_page_size_is_remembered_per_user(): void
    {
        $this->seedContracts(30);

        $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['per_page' => '100']))
            ->assertOk()
            ->assertCookie('contract_filters');

        $saved = [(string) $this->master->id => ['per_page' => '100']];

        $this->withCookie('contract_filters', json_encode($saved, JSON_UNESCAPED_UNICODE))
            ->actingAs($this->master)
            ->get(route('admin.clients.index'))
            ->assertRedirect(route('admin.clients.index', ['per_page' => '100']));

        $this->withCookie('contract_filters', json_encode($saved, JSON_UNESCAPED_UNICODE))
            ->actingAs($this->master)
            ->get(route('admin.clients.index', ['per_page' => '100']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.per_page', '100')
                ->count('rows.data', 30)
            );
    }

    public function test_clearing_filters_keeps_the_page_size(): void
    {
        $this->seedContracts(60);

        $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['status' => ContractStatus::FINISHED, 'per_page' => '25']))
            ->assertOk();

        $this->actingAs($this->master)
            ->get(route('admin.clients.index', ['clear_filters' => 1, 'per_page' => '25']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.per_page', '25')
                ->where('filters.active_count', 0)
                ->count('rows.data', 25)
            );
    }

    public function test_page_size_survives_together_with_real_filters(): void
    {
        $this->seedContracts(60);

        $expected = [(string) $this->master->id => [
            'status' => ['Invoice ENVIADA'],
            'per_page' => '100',
            'sort_by' => 'date',
            'sort_dir' => 'asc',
        ]];

        $this->withCookie('contract_filters', json_encode($expected, JSON_UNESCAPED_UNICODE))
            ->actingAs($this->master)
            ->get(route('admin.clients.index'))
            ->assertRedirect(route('admin.clients.index', [
                'status' => ['Invoice ENVIADA'],
                'per_page' => '100',
                'sort_by' => 'date',
                'sort_dir' => 'asc',
            ]));
    }

    private function seedContracts(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $contract = Contract::create([
                'date' => sprintf('2026-01-%02d', ($i % 28) + 1),
                'contract_number' => (string) (9000 + $i),
                'exporter_id' => $this->exporter->id,
                'importer_id' => $this->importer->id,
                'country' => 'China',
            ]);

            ContractItem::create([
                'contract_id' => $contract->id,
                'position' => 1,
                'register_number' => (string) (40000 + $i),
                'product_id' => $this->product->id,
                'quantity_tons' => 10,
                'price_per_ton_usd' => 2000,
                'sales_usd' => 20000,
            ]);
        }
    }
}
