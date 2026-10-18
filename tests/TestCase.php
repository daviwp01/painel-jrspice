<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A suíte nunca depende das chaves do .env: reCAPTCHA fica desligado
        // por padrão. Quem testa a integração liga na mão, com Http::fake
        // (ver RecaptchaProtectionTest) — assim os testes continuam herméticos
        // mesmo com as chaves preenchidas em desenvolvimento.
        config([
            'recaptcha.enabled' => false,
            'recaptcha.site_key' => null,
            'recaptcha.secret_key' => null,
        ]);
    }
}
