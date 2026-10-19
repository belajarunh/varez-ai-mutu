<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ProductionErrorDisclosureTest extends TestCase
{
    public function test_server_error_does_not_expose_exception_details_when_debug_is_disabled(): void
    {
        config(['app.debug' => false]);

        Route::get('/_test/production-error', static function (): void {
            throw new RuntimeException('Sensitive exception detail');
        });

        $this->get('/_test/production-error')
            ->assertInternalServerError()
            ->assertDontSee('Sensitive exception detail')
            ->assertDontSee(RuntimeException::class);
    }
}
