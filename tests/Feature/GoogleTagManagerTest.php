<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleTagManagerTest extends TestCase
{
    use RefreshDatabase;

    private const CONTAINER = 'GTM-5BTMPRFS';

    public function test_development_does_not_render_the_tag_manager(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('googletagmanager.com', $html);
        $this->assertStringNotContainsString(self::CONTAINER, $html);
    }

    public function test_testing_environment_does_not_render_the_tag_manager(): void
    {
        $this->assertSame('testing', app()->environment());

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false);
    }

    public function test_production_renders_the_snippet_as_high_as_possible_in_the_head(): void
    {
        $html = $this->asProduction()->get('/login')->assertOk()->getContent();

        $headStart = strpos($html, '<head>');
        $snippet = strpos($html, 'googletagmanager.com/gtm.js');
        $charset = strpos($html, '<meta charset="utf-8">');

        $this->assertNotFalse($headStart, 'a página precisa ter <head>');
        $this->assertNotFalse($snippet, 'o snippet do GTM deve estar no HTML');
        $this->assertGreaterThan($headStart, $snippet, 'o GTM deve estar dentro do <head>');
        $this->assertLessThan($charset, $snippet, 'o GTM deve vir antes de qualquer outra tag do <head>');

        // O script monta a URL dinamicamente e recebe o ID como argumento.
        $this->assertStringContainsString("'dataLayer','".self::CONTAINER."'", $html);
        $this->assertStringContainsString("'gtm.start'", $html);
        $this->assertStringContainsString("new Date().getTime(),event:'gtm.js'", $html);
    }

    public function test_production_renders_the_noscript_fallback_right_after_body(): void
    {
        $html = $this->asProduction()->get('/login')->assertOk()->getContent();

        $bodyStart = strpos($html, '<body');
        $noscript = strpos($html, 'googletagmanager.com/ns.html?id='.self::CONTAINER);

        $this->assertNotFalse($bodyStart, 'a página precisa ter <body>');
        $this->assertNotFalse($noscript, 'o fallback noscript deve estar no HTML');
        $this->assertGreaterThan($bodyStart, $noscript, 'o fallback deve vir depois da abertura do <body>');
        $this->assertLessThan(400, $noscript - $bodyStart, 'o fallback deve ser a primeira coisa do <body>');
    }

    public function test_production_uses_a_custom_container_when_configured(): void
    {
        config(['app.gtm_container_id' => 'GTM-TESTE123']);

        $html = $this->asProduction()->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString("'dataLayer','GTM-TESTE123'", $html);
        $this->assertStringContainsString('ns.html?id=GTM-TESTE123', $html);
        $this->assertStringNotContainsString(self::CONTAINER, $html);
    }

    public function test_production_without_a_container_id_renders_nothing(): void
    {
        config(['app.gtm_container_id' => '']);

        $this->asProduction()
            ->get('/login')
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false);
    }

    private function asProduction(): static
    {
        $this->app['env'] = 'production';

        return $this;
    }
}
