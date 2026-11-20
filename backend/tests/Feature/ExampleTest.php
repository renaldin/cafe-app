<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_returns_ok(): void
    {
        $this->get('/')->assertOk()->assertJsonPath('status', 'ok');
    }
}
