<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ProductTranslationGlossary;
use PHPUnit\Framework\TestCase;

class ProductTranslationGlossaryTest extends TestCase
{
    public function test_translates_common_spice_terms_to_english_and_spanish(): void
    {
        $result = ProductTranslationGlossary::translate('Alho em pó A');

        $this->assertSame('Garlic powder A', $result['en']);
        $this->assertSame('Ajo en polvo A', $result['es']);
    }

    public function test_keeps_unknown_tokens_and_codes(): void
    {
        $result = ProductTranslationGlossary::translate('SnowWhite AA');

        $this->assertSame('SnowWhite AA', $result['en']);
        $this->assertSame('SnowWhite AA', $result['es']);
    }

    public function test_never_returns_empty_translation(): void
    {
        $result = ProductTranslationGlossary::translate('Alpiste');

        $this->assertNotSame('', $result['en']);
        $this->assertNotSame('', $result['es']);
    }
}
