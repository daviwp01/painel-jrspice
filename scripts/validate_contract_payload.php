<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;

$master = User::where('is_master', true)->firstOrFail();
auth()->login($master);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$contractFields = [
    'date', 'contract_number', 'exporter_id', 'importer_id', 'country', 'seller_id',
    'status', 'dhl_date', 'dhl_number', 'etd_date', 'eta_date',
    'video_sent', 'video_reference', 'video_date', 'incident', 'estimated_receipt_date',
    'exchange_rate', 'receipt_date', 'paid_in_date', 'paid_in_brl', 'observations',
    'shipping_company', 'container_number',
];

$itemFields = [
    'register_number', 'product_id', 'quantity_tons', 'price_per_ton_usd', 'sales_usd',
    'commission_rate', 'commission_usd', 'to_pay_rate', 'to_pay_usd', 'etd_date',
    'eta_date', 'dhl_number', 'video_date', 'video_reference', 'observations',
];

$formKeys = array_merge($contractFields, ['user_ids', 'items']);

$normalise = function ($value) {
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric($value)) {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }

    return trim((string) $value);
};

$phantomBlanks = [];
$mismatches = [];
$missingFormKeys = [];
$checkedContracts = 0;
$checkedItems = 0;

$ids = App\Models\Contract::orderBy('id')->pluck('id');

foreach ($ids as $id) {
    $contract = App\Models\Contract::with('items')->findOrFail($id);

    $response = $kernel->handle(Request::create('/admin/contracts/'.$id, 'GET'));

    if ($response->getStatusCode() !== 200) {
        $mismatches[] = "contract {$id}: HTTP {$response->getStatusCode()}";

        continue;
    }

    $payload = json_decode($response->getContent(), true);
    $checkedContracts++;

    foreach ($formKeys as $key) {
        if (! array_key_exists($key, $payload)) {
            $missingFormKeys[] = "contract {$id}: campo de formulário '{$key}' ausente no payload";
        }
    }

    foreach ($contractFields as $field) {
        $db = $normalise($contract->getAttribute($field));
        $api = $normalise($payload[$field] ?? null);

        if ($db !== null && $api === null) {
            $phantomBlanks[] = "contract {$id} ({$contract->contract_number}): '{$field}' = {$db} no banco, vazio no form";
        } elseif ($db !== $api) {
            $mismatches[] = "contract {$id} ({$contract->contract_number}): '{$field}' banco={$db} form={$api}";
        }
    }

    foreach ($contract->items->sortBy('position')->values() as $index => $item) {
        $checkedItems++;
        $apiItem = $payload['items'][$index] ?? null;

        if ($apiItem === null) {
            $mismatches[] = "contract {$id}: item position {$item->position} ausente no payload";

            continue;
        }

        foreach ($itemFields as $field) {
            $db = $normalise($item->getAttribute($field));
            $api = $normalise($apiItem[$field] ?? null);

            if ($db !== null && $api === null) {
                $phantomBlanks[] = "contract {$id} item#{$item->id}: '{$field}' = {$db} no banco, vazio no form";
            } elseif ($db !== $api) {
                $mismatches[] = "contract {$id} item#{$item->id}: '{$field}' banco={$db} form={$api}";
            }
        }
    }
}

echo "Contratos verificados: {$checkedContracts}\n";
echo "Itens verificados:     {$checkedItems}\n";
echo 'Campos de form ausentes no payload: '.count($missingFormKeys)."\n";
echo 'Campos em branco fantasma:          '.count($phantomBlanks)."\n";
echo 'Divergências de valor:              '.count($mismatches)."\n";

foreach (array_slice($missingFormKeys, 0, 20) as $line) {
    echo "  [MISSING] {$line}\n";
}
foreach (array_slice($phantomBlanks, 0, 40) as $line) {
    echo "  [BLANK]   {$line}\n";
}
foreach (array_slice($mismatches, 0, 40) as $line) {
    echo "  [DIFF]    {$line}\n";
}

$fill = [];
foreach ($contractFields as $field) {
    $fill[$field] = App\Models\Contract::whereNotNull($field)->where($field, '!=', '')->count();
}
foreach ($itemFields as $field) {
    $fill['items.'.$field] = App\Models\ContractItem::whereNotNull($field)->where($field, '!=', '')->count();
}

echo "\nOcupação real dos campos (de {$checkedContracts} contratos / {$checkedItems} itens):\n";
foreach ($fill as $field => $count) {
    printf("  %-28s %6d\n", $field, $count);
}
