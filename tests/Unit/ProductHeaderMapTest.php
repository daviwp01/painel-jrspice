<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ProductHeaderMap;
use PHPUnit\Framework\TestCase;

class ProductHeaderMapTest extends TestCase
{
    public function test_maps_canonical_product_column_without_translation_variants(): void
    {
        $map = ProductHeaderMap::fromLoweredHeaders([
            'produto', 'pais', 'fornecedor', 'data registro', 'ano / mes', 'semana', 'preco',
        ]);

        $this->assertSame(0, $map['product']);
        $this->assertNull($map['product_en']);
        $this->assertNull($map['product_es']);
        $this->assertSame(1, $map['country']);
        $this->assertSame(2, $map['supplier']);
        $this->assertSame(3, $map['date']);
        $this->assertSame(6, $map['price']);
    }

    public function test_detects_optional_translation_columns_and_keeps_canonical_product(): void
    {
        $map = ProductHeaderMap::fromLoweredHeaders([
            'produto', 'pais', 'produto_en', 'produto_es', 'preco', 'product english', 'product spanish',
        ]);

        $this->assertSame(0, $map['product'], 'PRODUTO must not be overridden by translation columns');
        $this->assertNotNull($map['product_en']);
        $this->assertNotNull($map['product_es']);
    }

    public function test_last_matching_header_wins_like_legacy_import(): void
    {
        $map = ProductHeaderMap::fromLoweredHeaders(['data registro', 'data status']);

        $this->assertSame(1, $map['date']);
    }
}
