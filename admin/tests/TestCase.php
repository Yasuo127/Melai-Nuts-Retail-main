<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Feature tests render Blade layouts that call @vite; they must not need `npm run build` first.
        $this->withoutVite();
    }
}
