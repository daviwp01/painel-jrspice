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
        // Limpar registros demo antigos para evitar duplicatas e limpar IDs órfãos
        ExportProcess::whereIn('contract_number', ['JRS-2025-001', 'JRS-2025-042', 'JRS-2025-078', 'JRS-2025-061'])->delete();

        // Clientes exportadores
        $exp1 = Client::firstOrCreate(
            ['name' => 'JR Spice Brasil Ltda'],
            ['type' => 'exporter', 'country' => 'Brasil']
        );
        $exp2 = Client::firstOrCreate(
            ['name' => 'Nortão Agro Exportações'],
            ['type' => 'exporter', 'country' => 'Brasil']
        );

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
            ['name' => 'Asia Herbs Trading Co.'],
            ['type' => 'importer', 'country' => 'Japão']
        );

        // Buscar IDs de produtos reais cadastrados no banco
        $productIds = Product::pluck('id')->toArray();
        $p1 = $productIds[0] ?? null;
        $p2 = $productIds[1] ?? $p1;
        $p3 = $productIds[2] ?? $p1;
        $p4 = $productIds[3] ?? $p1;

        // Buscar IDs de vendedores (users) reais cadastrados no banco
        $userIds = User::pluck('id')->toArray();
        $u1 = $userIds[0] ?? null;
        $u2 = $userIds[1] ?? $u1;

        $contracts = [
            // Contrato 1 — Processo FINALIZADO
            [
                'date'                   => '2025-01-15',
                'contract_number'        => 'JRS-2025-001',
                'register_number'        => 'REG-BR-001/2025',
                'exporter_id'            => $exp1->id,
                'importer_id'            => $imp1->id,
                'product_id'             => $p1,
                'quantity_tons'          => 20.0,
                'price_per_ton_usd'      => 1850.00,
                'sales_usd'              => 37000.00,
                'annual_sales_usd'       => 148000.00,
                'commission_usd'         => 1850.00,
                'total_commission_usd'   => 3700.00,
                'exchange_rate'          => 5.1200,
                'estimated_euro'         => 34259.00,
                'estimated_receipt_date' => '2025-03-10',
                'seller_id'              => $u2,
                'to_pay_usd'             => 1850.00,
                'receipt_date'           => '2025-03-08',
                'paid_in_date'           => '2025-03-12',
                'paid_in_brl'            => 9500.00,
                'incident'               => null,
                'video_sent'             => true,
                'video_date'             => '2025-01-20',
                'status'                 => 'Processo FINALIZADO',
                'status_date'            => '2025-03-12',
                'shipping_company'       => 'Maersk',
                'container_number'       => 'MSMU8204910',
                'dhl_date'               => '2025-02-10',
                'dhl_number'             => 'DHL-4821937465',
                'etd_date'               => '2025-02-15',
                'eta_date'               => '2025-03-05',
                'observations'           => 'Contrato concluído com sucesso. Cliente satisfeito com a qualidade do produto.',
            ],
            // Contrato 2 — Invoice ENVIADA
            [
                'date'                   => '2025-03-22',
                'contract_number'        => 'JRS-2025-042',
                'register_number'        => 'REG-BR-042/2025',
                'exporter_id'            => $exp1->id,
                'importer_id'            => $imp2->id,
                'product_id'             => $p2,
                'quantity_tons'          => 15.5,
                'price_per_ton_usd'      => 2100.00,
                'sales_usd'              => 32550.00,
                'annual_sales_usd'       => 130200.00,
                'commission_usd'         => 1627.50,
                'total_commission_usd'   => 3255.00,
                'exchange_rate'          => 5.0800,
                'estimated_euro'         => 30327.00,
                'estimated_receipt_date' => '2025-05-20',
                'seller_id'              => $u1,
                'to_pay_usd'             => 1627.50,
                'receipt_date'           => null,
                'paid_in_date'           => null,
                'paid_in_brl'            => null,
                'incident'               => null,
                'video_sent'             => true,
                'video_date'             => '2025-03-28',
                'status'                 => 'Invoice ENVIADA',
                'status_date'            => '2025-04-30',
                'shipping_company'       => 'MSC',
                'container_number'       => 'MSCD1928374',
                'dhl_date'               => '2025-04-05',
                'dhl_number'             => 'DHL-3956812047',
                'etd_date'               => '2025-04-10',
                'eta_date'               => '2025-05-12',
                'observations'           => 'Invoice enviada. Aguardando confirmação de pagamento do cliente EuroSpice.',
            ],
            // Contrato 3 — A embarcar
            [
                'date'                   => '2025-05-10',
                'contract_number'        => 'JRS-2025-078',
                'register_number'        => 'REG-BR-078/2025',
                'exporter_id'            => $exp2->id,
                'importer_id'            => $imp3->id,
                'product_id'             => $p3,
                'quantity_tons'          => 8.0,
                'price_per_ton_usd'      => 3200.00,
                'sales_usd'              => 25600.00,
                'annual_sales_usd'       => 76800.00,
                'commission_usd'         => 1280.00,
                'total_commission_usd'   => 2560.00,
                'exchange_rate'          => 5.2400,
                'estimated_euro'         => 23703.00,
                'estimated_receipt_date' => '2025-07-30',
                'seller_id'              => $u2,
                'to_pay_usd'             => 1280.00,
                'receipt_date'           => null,
                'paid_in_date'           => null,
                'paid_in_brl'            => null,
                'incident'               => null,
                'video_sent'             => false,
                'video_date'             => null,
                'status'                 => 'A embarcar dia',
                'status_date'            => '2025-06-01',
                'shipping_company'       => 'Evergreen',
                'container_number'       => 'EGLV4739281',
                'dhl_date'               => null,
                'dhl_number'             => null,
                'etd_date'               => '2025-06-15',
                'eta_date'               => '2025-07-20',
                'observations'           => 'Aguardando liberação aduaneira para embarque. Documentação completa e aprovada.',
            ],
            // Contrato 4 — Incidente / Só faltando comissão
            [
                'date'                   => '2025-04-05',
                'contract_number'        => 'JRS-2025-061',
                'register_number'        => 'REG-BR-061/2025',
                'exporter_id'            => $exp2->id,
                'importer_id'            => $imp1->id,
                'product_id'             => $p4,
                'quantity_tons'          => 25.0,
                'price_per_ton_usd'      => 1750.00,
                'sales_usd'              => 43750.00,
                'annual_sales_usd'       => 175000.00,
                'commission_usd'         => 2187.50,
                'total_commission_usd'   => 4375.00,
                'exchange_rate'          => 5.0500,
                'estimated_euro'         => 40742.00,
                'estimated_receipt_date' => '2025-06-15',
                'seller_id'              => $u1,
                'to_pay_usd'             => 2187.50,
                'receipt_date'           => null,
                'paid_in_date'           => null,
                'paid_in_brl'            => null,
                'incident'               => 'Greve portuária em Santos — atraso estimado de 12 dias.',
                'video_sent'             => true,
                'video_date'             => '2025-04-12',
                'status'                 => 'Só faltando comissão',
                'status_date'            => '2025-05-28',
                'shipping_company'       => 'Hapag lloyd',
                'container_number'       => 'HLCU9283745',
                'dhl_date'               => '2025-04-20',
                'dhl_number'             => 'DHL-7741029385',
                'etd_date'               => '2025-04-25',
                'eta_date'               => '2025-05-28',
                'observations'           => 'Incidente de greve portuária registrado. Cliente notificado. Prazo reajustado em comum acordo.',
            ],
        ];

        foreach ($contracts as $contract) {
            ExportProcess::create($contract);
        }

        $this->command->info('✓ Clientes e contratos demo criados/atualizados com sucesso!');
    }
}
