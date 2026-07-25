<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\ExportProcess;
use App\Models\Product;
use App\Models\User;

class DemoContractsSeeder extends Seeder
{
    public function run(): void
    {
        // Limpar registros demo antigos para garantir estado limpo
        ExportProcess::query()->delete();

        // Atualizar/garantir clientes com papéis corretos
        $clientNortao = Client::where('name', 'like', '%Nortão Agro%')->first();
        if ($clientNortao) {
            $clientNortao->update(['name' => 'Nortão Agro Importações', 'type' => 'importer']);
        }
        $clientAsia = Client::where('name', 'like', '%Asia Herbs%')->first();
        if ($clientAsia) {
            $clientAsia->update(['type' => 'exporter']);
        }

        // Clientes exportadores
        $exp1 = Client::firstOrCreate(
            ['name' => 'JR Spice Brasil Ltda'],
            ['type' => 'exporter', 'country' => 'Brasil']
        );
        $exp2 = Client::firstOrCreate(
            ['name' => 'Asia Herbs Trading Co.'],
            ['type' => 'exporter', 'country' => 'Japão']
        );
        $exp2->update(['type' => 'exporter']);

        // Clientes importadores
        $imp1 = Client::firstOrCreate(
            ['name' => 'Golden Foods Inc.'],
            ['type' => 'importer', 'country' => 'Estados Unidos']
        );
        $imp2 = Client::firstOrCreate(
            ['name' => 'EuroSpice GmbH'],
            ['type' => 'importer', 'country' => 'Alemanha']
        );
        $imp3 = Client::firstOrCreate(
            ['name' => 'Nortão Agro Importações'],
            ['type' => 'importer', 'country' => 'Brasil']
        );
        $imp3->update(['type' => 'importer']);

        // Buscar produtos de Abóbora no banco
        $abobora1 = Product::where('name', 'like', '%Ab%bora Semente SnowWhite 11cm%')->first()
            ?? Product::where('name', 'like', '%Ab%bora%')->first();
        $abobora2 = Product::where('name', 'like', '%Ab%bora semente GWS A%')->first()
            ?? $abobora1;
        $abobora3 = Product::where('name', 'like', '%Ab%bora Semente ShineSkin AA%')->first()
            ?? $abobora1;

        // Vendedor padrão
        $seller = User::first();
        $sellerId = $seller ? $seller->id : null;

        // Garantir que todos os clientes de teste recebam contratos para demonstração
        $importers = [$imp1->id, $imp3->id];

        $contracts = [];

        foreach ($importers as $impId) {
            $contracts[] = [
                'date'                   => '2026-07-15',
                'contract_number'        => 'JRS-2026-' . ($impId === $imp1->id ? '001' : '101'),
                'register_number'        => 'REG-BR-001/2026',
                'exporter_id'            => $exp2->id,
                'importer_id'            => $impId,
                'product_id'             => $abobora1?->id,
                'quantity_tons'          => 25.0,
                'price_per_ton_usd'      => 1850.00,
                'sales_usd'              => 46250.00,
                'annual_sales_usd'       => 185000.00,
                'commission_usd'         => 2312.50,
                'total_commission_usd'   => 4625.00,
                'exchange_rate'          => 5.4500,
                'estimated_euro'         => 42500.00,
                'estimated_receipt_date' => '2026-07-20',
                'seller_id'              => $sellerId,
                'to_pay_usd'             => 2312.50,
                'receipt_date'           => '2026-07-18',
                'paid_in_date'           => '2026-07-22',
                'paid_in_brl'            => 12603.12,
                'incident'               => null,
                'video_sent'             => true,
                'video_date'             => '2026-07-16',
                'status'                 => 'Processo FINALIZADO',
                'status_date'            => '2026-07-22',
                'shipping_company'       => 'Maersk',
                'container_number'       => 'MSMU8204910',
                'dhl_date'               => '2026-07-17',
                'dhl_number'             => 'DHL-4821937465',
                'etd_date'               => '2026-07-17',
                'eta_date'               => '2026-07-20',
                'observations'           => 'Processo concluído com sucesso e entrega realizada dentro do prazo estipulado.',
            ];

            $contracts[] = [
                'date'                   => '2026-07-20',
                'contract_number'        => 'JRS-2026-' . ($impId === $imp1->id ? '042' : '142'),
                'register_number'        => 'REG-BR-042/2026',
                'exporter_id'            => $exp2->id,
                'importer_id'            => $impId,
                'product_id'             => $abobora2?->id,
                'quantity_tons'          => 20.0,
                'price_per_ton_usd'      => 2100.00,
                'sales_usd'              => 42000.00,
                'annual_sales_usd'       => 168000.00,
                'commission_usd'         => 2100.00,
                'total_commission_usd'   => 4200.00,
                'exchange_rate'          => 5.4500,
                'estimated_euro'         => 38600.00,
                'estimated_receipt_date' => '2026-08-20',
                'seller_id'              => $sellerId,
                'to_pay_usd'             => 2100.00,
                'receipt_date'           => null,
                'paid_in_date'           => null,
                'paid_in_brl'            => null,
                'incident'               => null,
                'video_sent'             => true,
                'video_date'             => '2026-07-21',
                'status'                 => 'A embarcar dia',
                'status_date'            => '2026-07-22',
                'shipping_company'       => 'MSC',
                'container_number'       => 'MSCD1928374',
                'dhl_date'               => null,
                'dhl_number'             => null,
                'etd_date'               => '2026-07-28',
                'eta_date'               => '2026-08-15',
                'observations'           => 'Aguardando liberação de embarque no porto de origem.',
            ];

            $contracts[] = [
                'date'                   => '2026-07-10',
                'contract_number'        => 'JRS-2026-' . ($impId === $imp1->id ? '078' : '178'),
                'register_number'        => 'REG-BR-078/2026',
                'exporter_id'            => $exp2->id,
                'importer_id'            => $impId,
                'product_id'             => $abobora3?->id,
                'quantity_tons'          => 15.0,
                'price_per_ton_usd'      => 1950.00,
                'sales_usd'              => 29250.00,
                'annual_sales_usd'       => 117000.00,
                'commission_usd'         => 1462.50,
                'total_commission_usd'   => 2925.00,
                'exchange_rate'          => 5.4500,
                'estimated_euro'         => 26900.00,
                'estimated_receipt_date' => '2026-08-10',
                'seller_id'              => $sellerId,
                'to_pay_usd'             => 1462.50,
                'receipt_date'           => null,
                'paid_in_date'           => null,
                'paid_in_brl'            => null,
                'incident'               => null,
                'video_sent'             => true,
                'video_date'             => '2026-07-12',
                'status'                 => 'Transbordo em curso',
                'status_date'            => '2026-07-18',
                'shipping_company'       => 'Evergreen',
                'container_number'       => 'EGLV4739281',
                'dhl_date'               => '2026-07-14',
                'dhl_number'             => 'DHL-3956812047',
                'etd_date'               => '2026-07-15',
                'eta_date'               => '2026-08-05',
                'observations'           => 'Transbordo operando conforme o cronograma náutico.',
            ];
        }

        $daviUser = User::where('email', 'davi.wordpress@gmail.com')->first();

        foreach ($contracts as $contract) {
            $process = ExportProcess::create($contract);
            if ($daviUser && in_array($process->contract_number, ['JRS-2026-001', 'JRS-2026-042', 'JRS-2026-078'])) {
                $process->users()->sync([$daviUser->id]);
            }
        }

        $this->command->info('✓ Contratos demo de Abóbora criados com sucesso!');
    }
}
