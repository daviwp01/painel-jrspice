<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Http\Requests\StoreContractDocumentRequest;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\ContractItem;
use App\Models\ContractProduct;
use App\Models\User;
use App\Services\Contracts\ContractDocumentService;
use App\Support\ContractDocumentRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Tests\TestCase;

class ContractDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    private Contract $contract;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('contracts');

        $exporter = Client::create(['name' => 'Golden Spices', 'type' => 'exporter', 'country' => 'China']);
        $importer = Client::create(['name' => 'Expin', 'type' => 'importer', 'country' => 'India']);
        $product = ContractProduct::create(['name' => 'Cebola Chopped', 'normalized_name' => 'cebolachopped']);

        $this->contract = Contract::create([
            'date' => '2026-01-10',
            'contract_number' => '7777',
            'exporter_id' => $exporter->id,
            'importer_id' => $importer->id,
            'country' => 'China',
            'status' => 'A embarcar dia',
        ]);

        ContractItem::create([
            'contract_id' => $this->contract->id,
            'position' => 1,
            'register_number' => '45886',
            'product_id' => $product->id,
            'quantity_tons' => 5,
            'price_per_ton_usd' => 1500,
            'sales_usd' => 7500,
        ]);

        $this->master = User::factory()->create(['is_master' => true]);
    }

    public function test_master_uploads_a_document_to_the_contracts_disk(): void
    {
        $file = UploadedFile::fake()->create('invoice.pdf', 64, 'application/pdf');

        $this->actingAs($this->master)
            ->post(route('contracts.documents.store', $this->contract), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $document = ContractDocument::sole();

        $this->assertSame('invoice.pdf', $document->name);
        $this->assertSame('document', $document->file_type);
        $this->assertSame($this->master->id, $document->uploaded_by);
        $this->assertSame(64 * 1024, $document->size_bytes);

        Storage::disk('contracts')->assertExists($document->file_path);
        $this->assertStringContainsString(
            sprintf('contracts/%d', $this->contract->id),
            $document->file_path,
        );
    }

    public function test_documents_are_classified_by_extension(): void
    {
        $mapping = [
            'tour.mp4' => 'video',
            'photo.jpg' => 'image',
            'planilha.xlsx' => 'spreadsheet',
            'invoice.pdf' => 'document',
        ];

        foreach (array_keys($mapping) as $name) {
            $this->actingAs($this->master)
                ->post(route('contracts.documents.store', $this->contract), [
                    'file' => UploadedFile::fake()->create($name, 4),
                ])
                ->assertRedirect();
        }

        foreach ($mapping as $name => $type) {
            $this->assertDatabaseHas('contract_documents', ['name' => $name, 'file_type' => $type]);
        }
    }

    public function test_rejects_unsupported_extension(): void
    {
        $this->actingAs($this->master)
            ->post(route('contracts.documents.store', $this->contract), [
                'file' => UploadedFile::fake()->create('payload.exe', 4, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, ContractDocument::count());
    }

    public function test_rejects_missing_file(): void
    {
        $this->actingAs($this->master)
            ->post(route('contracts.documents.store', $this->contract))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, ContractDocument::count());
    }

    public function test_size_rule_rejects_anything_over_five_gigabytes(): void
    {
        $this->assertSame(5 * 1024 * 1024 * 1024, ContractDocumentRules::MAX_BYTES);
        $this->assertSame('5 GB', ContractDocumentRules::humanMaxSize());

        $rules = (new StoreContractDocumentRequest())->rules();
        $this->assertContains('max:'.ContractDocumentRules::MAX_KILobytes, $rules['file']);

        $oversized = new class (realpath(__FILE__), 'tour.mp4', 'video/mp4', 0, true) extends SymfonyUploadedFile {
            public function getSize(): int
            {
                return ContractDocumentRules::MAX_BYTES + 1;
            }
        };

        $validator = Validator::make(
            ['file' => $oversized],
            ['file' => ['max:'.ContractDocumentRules::MAX_KILobytes]],
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('file', $validator->errors()->toArray());
    }

    public function test_master_deletes_document_and_its_file(): void
    {
        $document = app(ContractDocumentService::class)->upload(
            $this->contract,
            UploadedFile::fake()->create('invoice.pdf', 64, 'application/pdf'),
            $this->master,
        );

        Storage::disk('contracts')->assertExists($document->file_path);

        $this->actingAs($this->master)
            ->delete(route('contracts.documents.destroy', $document))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('contract_documents', ['id' => $document->id]);
        Storage::disk('contracts')->assertMissing($document->file_path);
    }

    public function test_delete_removes_file_row_and_the_contract_directory(): void
    {
        $service = app(ContractDocumentService::class);
        $document = $service->upload(
            $this->contract,
            UploadedFile::fake()->create('invoice.pdf', 64, 'application/pdf'),
            $this->master,
        );

        $directory = sprintf('contracts/%d', $this->contract->id);

        Storage::disk('contracts')->assertExists($document->file_path);
        $this->assertContains($directory, Storage::disk('contracts')->allDirectories());

        $service->delete($document);

        $this->assertDatabaseMissing('contract_documents', ['id' => $document->id]);
        Storage::disk('contracts')->assertMissing($document->file_path);
        $this->assertNotContains($directory, Storage::disk('contracts')->allDirectories());
        $this->assertSame([], Storage::disk('contracts')->files($directory));
        // A pasta pai ('contracts') também some quando fica vazia.
        $this->assertNotContains('contracts', Storage::disk('contracts')->allDirectories());
    }

    public function test_delete_of_a_document_whose_file_is_already_gone_still_frees_the_row(): void
    {
        $document = $this->uploadSample('invoice.pdf');

        Storage::disk('contracts')->delete($document->file_path);

        $this->actingAs($this->master)
            ->delete(route('contracts.documents.destroy', $document))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('contract_documents', ['id' => $document->id]);
    }

    public function test_delete_keeps_files_of_other_contracts(): void
    {
        $service = app(ContractDocumentService::class);

        $mine = $service->upload(
            $this->contract,
            UploadedFile::fake()->create('invoice.pdf', 8, 'application/pdf'),
            $this->master,
        );

        $sibling = Contract::create([
            'date' => '2026-01-11',
            'contract_number' => '8888',
            'exporter_id' => $this->contract->exporter_id,
            'importer_id' => $this->contract->importer_id,
            'country' => 'China',
        ]);

        $other = $service->upload(
            $sibling,
            UploadedFile::fake()->create('packing.pdf', 8, 'application/pdf'),
            $this->master,
        );

        $service->delete($mine);

        $this->assertDatabaseMissing('contract_documents', ['id' => $mine->id]);
        $this->assertDatabaseHas('contract_documents', ['id' => $other->id]);
        Storage::disk('contracts')->assertExists($other->file_path);
    }

    public function test_preview_and_download_return_404_once_the_document_is_deleted(): void
    {
        $document = $this->uploadSample('invoice.pdf', $this->master);
        $path = $document->file_path;

        Storage::disk('contracts')->put($path, 'pdf-bytes');

        $this->actingAs($this->master)
            ->delete(route('contracts.documents.destroy', $document))
            ->assertSessionHas('success');

        Storage::disk('contracts')->assertMissing($path);

        $client = User::factory()->create(['is_master' => false]);
        $this->contract->users()->attach($client->id);

        $this->actingAs($this->master)
            ->get(route('my-products.preview-document', $document->id))
            ->assertNotFound();

        $this->actingAs($this->master)
            ->get(route('my-products.download-document', $document->id))
            ->assertNotFound();
    }

    public function test_delete_ignores_paths_that_would_escape_the_disk(): void
    {
        $document = ContractDocument::create([
            'contract_id' => $this->contract->id,
            'name' => 'malicioso.pdf',
            'file_path' => '../../.env',
            'file_type' => 'document',
            'size_bytes' => 10,
            'uploaded_by' => $this->master->id,
        ]);

        app(ContractDocumentService::class)->delete($document);

        $this->assertDatabaseMissing('contract_documents', ['id' => $document->id]);
        $this->assertFileExists(base_path('.env'));
    }

    public function test_show_returns_documents_for_the_slideover(): void
    {
        $document = $this->uploadSample('invoice.pdf', $this->master);

        $payload = $this->actingAs($this->master)
            ->getJson(route('contracts.show', $this->contract))
            ->assertOk()
            ->json();

        $this->assertCount(1, $payload['documents']);
        $this->assertSame($document->id, $payload['documents'][0]['id']);
        $this->assertSame('invoice.pdf', $payload['documents'][0]['name']);
        $this->assertSame(64 * 1024, $payload['documents'][0]['size_bytes']);
        $this->assertSame($this->master->name, $payload['documents'][0]['uploader']);
    }

    public function test_client_portal_lists_documents_without_upload_capability(): void
    {
        $client = User::factory()->create(['is_master' => false]);
        $this->contract->users()->attach($client->id);

        $document = $this->uploadSample('invoice.pdf', $this->master);

        $this->actingAs($client)
            ->get(route('my-products.show', $this->contract))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MyProducts/Show')
                ->where('process.documents.0.id', $document->id)
                ->where('process.documents.0.size_bytes', 64 * 1024)
                ->where('process.documents.0.uploader', $this->master->name)
            );

        $this->actingAs($client)
            ->post(route('contracts.documents.store', $this->contract), [
                'file' => UploadedFile::fake()->create('mine.pdf', 4),
            ])
            ->assertForbidden();
    }

    private function uploadSample(string $name, ?User $uploader = null): ContractDocument
    {
        $uploader ??= $this->master;

        return ContractDocument::create([
            'contract_id' => $this->contract->id,
            'name' => $name,
            'file_path' => sprintf('contracts/%d/%s', $this->contract->id, $name),
            'file_type' => ContractDocumentRules::typeFor(strtolower(pathinfo($name, PATHINFO_EXTENSION))),
            'size_bytes' => 64 * 1024,
            'uploaded_by' => $uploader->id,
        ]);
    }
}
