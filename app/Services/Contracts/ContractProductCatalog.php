<?php

namespace App\Services\Contracts;

use App\Models\ContractProduct;
use Illuminate\Support\Str;

final class ContractProductCatalog
{
    public function resolve(?string $name): ?ContractProduct
    {
        $clean = $this->clean($name);
        if ($clean === null) {
            return null;
        }

        $normalized = $this->normalize($clean);

        return ContractProduct::query()
            ->where('normalized_name', $normalized)
            ->first()
            ?? ContractProduct::create([
                'name' => $clean,
                'normalized_name' => $normalized,
            ]);
    }

    public function clean(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/u', ' ', $name));

        return $clean === '' ? null : $clean;
    }

    public function normalize(string $name): string
    {
        $normalized = Str::ascii(mb_strtolower($name));
        $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized);

        return (string) $normalized;
    }
}
