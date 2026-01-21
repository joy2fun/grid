<?php

namespace Tests\Feature;

use App\Filament\Resources\Trades\TradeResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_trade_resource_index_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(TradeResource::getUrl('index'))
            ->assertSuccessful();
    }
}
