<?php

namespace Tests\Feature;

use App\Filament\Resources\Grids\Pages\EditGrid;
use App\Filament\Resources\Grids\RelationManagers\TradesRelationManager;
use App\Models\Grid;
use App\Models\Stock;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TradesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Grid $grid;

    protected Stock $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->stock = Stock::factory()->create();
        $this->grid = Grid::factory()->create([
            'stock_id' => $this->stock->id,
        ]);
    }

    public function test_can_load_relation_manager(): void
    {
        $this->actingAs($this->user);

        Livewire::test(TradesRelationManager::class, [
            'ownerRecord' => $this->grid,
            'pageClass' => EditGrid::class,
        ])->assertSuccessful();
    }

    public function test_table_is_not_paginated(): void
    {
        $this->actingAs($this->user);

        Trade::factory()->count(50)->create([
            'grid_id' => $this->grid->id,
            'stock_id' => $this->stock->id,
        ]);

        Livewire::test(TradesRelationManager::class, [
            'ownerRecord' => $this->grid,
            'pageClass' => EditGrid::class,
        ])
            ->assertCanSeeTableRecords($this->grid->trades);
    }

    public function test_can_create_trade_with_correct_defaults(): void
    {
        $this->actingAs($this->user);

        Livewire::test(TradesRelationManager::class, [
            'ownerRecord' => $this->grid,
            'pageClass' => EditGrid::class,
        ])
            ->callTableAction('create', data: [
                'type' => 'buy',
                'price' => 10.5,
                'quantity' => 100,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('trades', [
            'grid_id' => $this->grid->id,
            'stock_id' => $this->stock->id,
            'type' => 'buy',
            'price' => 10.5,
            'quantity' => 100,
        ]);

        $trade = Trade::latest()->first();
        $this->assertNotNull($trade->executed_at);
        $this->assertEquals(now()->toDateString(), $trade->executed_at->toDateString());
    }
}
