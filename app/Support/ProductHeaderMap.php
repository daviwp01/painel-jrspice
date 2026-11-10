<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Maps spreadsheet header labels to product import columns.
 *
 * PRODUTO_EN / PRODUTO_ES are optional: when present their values fill the
 * product translation columns; when absent the import behaves exactly as
 * before. The canonical PRODUTO column is never overridden by the
 * translation variants.
 */
final class ProductHeaderMap
{
    /**
     * @param  array<int|string, string|null>  $headers  already lowercased/trimmed; keys may be int indexes or column letters
     * @return array{product: int|string|null, product_en: int|string|null, product_es: int|string|null, country: int|string|null, supplier: int|string|null, price: int|string|null, date: int|string|null, safra: int|string|null}
     */
    public static function fromLoweredHeaders(array $headers): array
    {
        $map = [
            'product' => null,
            'product_en' => null,
            'product_es' => null,
            'country' => null,
            'supplier' => null,
            'price' => null,
            'date' => null,
            'safra' => null,
        ];

        foreach ($headers as $key => $raw) {
            if (! is_string($raw)) {
                continue;
            }

            $val = trim($raw);

            if (self::matchesTranslation($val, 'en')) {
                $map['product_en'] = $key;

                continue;
            }

            if (self::matchesTranslation($val, 'es')) {
                $map['product_es'] = $key;

                continue;
            }

            if (str_contains($val, 'produto') || str_contains($val, 'product')) {
                $map['product'] = $key;
            }

            if (str_contains($val, 'safra') || str_contains($val, 'harvest')) {
                $map['safra'] = $key;
            } elseif (str_contains($val, 'mes') && $map['safra'] === null && ! str_contains($val, 'ano')) {
                $map['safra'] = $key;
            }

            if (str_contains($val, 'pais') || str_contains($val, 'país') || str_contains($val, 'country') || str_contains($val, 'origem')) {
                $map['country'] = $key;
            }

            if (str_contains($val, 'fornecedor') || str_contains($val, 'supplier')) {
                $map['supplier'] = $key;
            }

            if (str_contains($val, 'preco') || str_contains($val, 'preço') || str_contains($val, 'price') || str_contains($val, 'valor')) {
                $map['price'] = $key;
            }

            if (str_contains($val, 'data') || str_contains($val, 'date')) {
                $map['date'] = $key;
            }
        }

        return $map;
    }

    private static function matchesTranslation(string $header, string $lang): bool
    {
        $normalized = strtolower(preg_replace('/[\s_\-]+/', '_', $header) ?? $header);

        $isProductish = str_contains($normalized, 'produto') || str_contains($normalized, 'product');
        if (! $isProductish) {
            return false;
        }

        if ($lang === 'en') {
            return str_ends_with($normalized, '_en')
                || str_contains($normalized, '_english');
        }

        return str_ends_with($normalized, '_es')
            || str_contains($normalized, '_espanol')
            || str_contains($normalized, '_español')
            || str_contains($normalized, '_spanish');
    }
}
