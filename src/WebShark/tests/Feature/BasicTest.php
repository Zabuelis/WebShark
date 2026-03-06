<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasicTest extends TestCase
{
    use RefreshDatabase;

    public function test_that_true_is_true()
    {
        $this->assertTrue(true);
    }
}
