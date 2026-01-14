<?php

namespace Tests\Feature;

use App\Filament\Resources\Grids\GridResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GridResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_grid_resource_index_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(GridResource::getUrl('index'))
            ->assertSuccessful();
    }
}
