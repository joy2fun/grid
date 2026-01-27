<?php

namespace Tests\Feature;

use App\Filament\Resources\Trades\Pages\ManageTrades;
use App\Jobs\ImportTradeImageJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ManageTradesBackgroundImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_import_background_dispatches_jobs(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ManageTrades::class)
            ->callAction('bulkImportBackground', [
                'images' => ['image1.jpg', 'image2.jpg'],
                'fallback_code' => '601166',
            ])
            ->assertHasNoFormErrors();

        Queue::assertPushed(ImportTradeImageJob::class, 2);
        Queue::assertPushed(function (ImportTradeImageJob $job) {
            return $job->imagePath === 'image1.jpg' && $job->fallbackCode === '601166';
        });
        Queue::assertPushed(function (ImportTradeImageJob $job) {
            return $job->imagePath === 'image2.jpg' && $job->fallbackCode === '601166';
        });
    }
}
