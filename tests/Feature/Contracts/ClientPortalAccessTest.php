<?php

namespace Tests\Feature\Contracts;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\ContractItem;
use App\Models\ContractProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    private Contract $contract;

    private User $linkedClient;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $exporter = Client::create(['name' => 'Golden Spices', 'type' => 'exporter', 'country' => 'China']);
        $importer = Client::create(['name' => 'Expin', 'type' => 'importer', 'country' => 'India']);
        $product = ContractProduct::create(['name' => 'Cebola Chopped', 'normalized_name' => 'cebolachopped']);

        $this->contract = Contract::create([
            'date' => '2026-01-10',
            'contract_number' => '9999',
            'exporter_id' => $exporter->id,
            'importer_id' => $importer->id,
            'country' => 'China',
            'status' => 'Processo FINALIZADO',
        ]);

        ContractItem::create([
            'contract_id' => $this->contract->id,
            'position' => 1,
            'register_number' => '45885',
            'product_id' => $product->id,
            'quantity_tons' => 10,
            'price_per_ton_usd' => 2000,
            'sales_usd' => 20000,
        ]);

        $this->linkedClient = User::factory()->create(['client_id' => $importer->id]);
        $this->contract->users()->attach($this->linkedClient->id);
    }

    public function test_linked_user_sees_the_contract_in_my_products(): void
    {
        $this->actingAs($this->linkedClient)
            ->get(route('my-products.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MyProducts/Index')
                ->where('exportProcesses.total', 1)
                ->where('exportProcesses.data.0.contract_number', '9999')
            );
    }

    public function test_unlinked_user_sees_an_empty_portal(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('my-products.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('exportProcesses.total', 0));
    }

    public function test_linked_user_can_open_the_contract_detail(): void
    {
        $this->actingAs($this->linkedClient)
            ->get(route('my-products.show', $this->contract))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MyProducts/Show')
                ->where('process.contract_number', '9999')
                ->where('process.items.0.register_number', '45885')
            );
    }

    public function test_unlinked_user_cannot_open_the_contract_detail(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('my-products.show', $this->contract))
            ->assertForbidden();
    }

    public function test_linked_user_can_download_document_even_without_client_id_match(): void
    {
        // Regression: authorization must follow the pivot, never users.client_id.
        $this->linkedClient->forceFill(['client_id' => null])->save();

        $document = ContractDocument::create([
            'contract_id' => $this->contract->id,
            'name' => 'invoice.pdf',
            'file_path' => 'contracts/'.$this->contract->id.'/invoice.pdf',
            'file_type' => 'document',
            'uploaded_by' => $this->linkedClient->id,
        ]);

        Storage::put($document->file_path, 'pdf-bytes');

        $this->actingAs($this->linkedClient)
            ->get(route('my-products.download-document', $document))
            ->assertOk();
    }

    public function test_unlinked_user_cannot_download_document(): void
    {
        $document = ContractDocument::create([
            'contract_id' => $this->contract->id,
            'name' => 'invoice.pdf',
            'file_path' => 'contracts/'.$this->contract->id.'/invoice.pdf',
            'file_type' => 'document',
            'uploaded_by' => $this->linkedClient->id,
        ]);

        Storage::put($document->file_path, 'pdf-bytes');

        $this->actingAs(User::factory()->create())
            ->get(route('my-products.download-document', $document))
            ->assertForbidden();
    }

    public function test_unlinked_user_cannot_upload_documents(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('my-products.upload-document', $this->contract), [
                'file' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertSame(0, ContractDocument::count());
    }

    public function test_user_can_review_only_finalized_contracts(): void
    {
        $this->contract->update(['status' => 'A embarcar dia']);

        $this->actingAs($this->linkedClient)
            ->post(route('reviews.store', $this->contract), ['rating' => 5])
            ->assertSessionHas('error');

        $this->contract->update(['status' => 'Processo FINALIZADO']);

        $this->actingAs($this->linkedClient)
            ->post(route('reviews.store', $this->contract), ['rating' => 5, 'comment' => 'Ótimo'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('contract_reviews', [
            'contract_id' => $this->contract->id,
            'user_id' => $this->linkedClient->id,
            'rating' => 5,
        ]);
    }
}
