<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to touch anything but a local MySQL before RefreshDatabase wipes it (D-34):
     * the default .env used to point at production, and phpunit.xml did not pin the host.
     */
    protected function setUpTraits()
    {
        $connection = config('database.connections.'.config('database.default'));
        $host = $connection['host'] ?? null;
        if (! empty($connection['url']) || ! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            throw new RuntimeException("Testes só rodam contra um MySQL local (127.0.0.1). Conexão atual: {$host}");
        }

        return parent::setUpTraits();
    }
}
