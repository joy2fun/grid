<?php

namespace App\Console\Commands;

use App\Models\DayPrice;
use App\Models\PriceAlert;
use App\Services\BarkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPriceAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-price-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and trigger price alerts';

    /**
     * Execute the console command.
     */
    public function handle(BarkService $barkService): int
    {
        $this->info('Checking price alerts...');

        $today = now()->format('Y-m-d');

        // Get all active alerts
        $alerts = PriceAlert::active()->with('stock')->get();

        $triggeredCount = 0;

        foreach ($alerts as $alert) {
            $this->line("Checking alert #{$alert->id} for {$alert->stock->code}...");

            // Check if notification was already sent today
            if (! $alert->isDueForNotification()) {
                $this->line("Alert #{$alert->id} already notified today, skipping.");

                continue;
            }

            // Get today's price for the stock
            $todayPrice = DayPrice::where('stock_id', $alert->stock_id)
                ->whereDate('date', $today)
                ->first();

            if (! $todayPrice) {
                $this->line("No price data today for {$alert->stock->code}, skipping.");

                continue;
            }

            $currentPrice = $todayPrice->close_price;
            $this->line("  Current price: {$currentPrice}, Threshold: {$alert->threshold_value}, Type: {$alert->threshold_type}");

            // Check if price has crossed the threshold
            if ($alert->shouldTrigger($currentPrice)) {
                $this->info("Alert #{$alert->id} triggered for {$alert->stock->code}!");
                $this->line("  Type: {$alert->threshold_type}, Threshold: {$alert->threshold_value}, Current: {$currentPrice}");

                // Send Bark notification
                $title = "价格提醒: {$alert->stock->name} ({$alert->stock->code})";
                $body = $alert->threshold_type === 'rise'
                    ? "价格已上涨至 {$currentPrice} (达到阈值 {$alert->threshold_value})"
                    : "价格已下跌至 {$currentPrice} (低于阈值 {$alert->threshold_value})";

                $sent = $barkService->send($title, $body);

                if ($sent) {
                    $this->line('  Bark notification sent successfully.');
                    $alert->update(['last_notified_at' => now()]);
                    $triggeredCount++;
                } else {
                    $this->error('  Failed to send Bark notification.');
                    Log::warning('Failed to send Bark notification for price alert', [
                        'alert_id' => $alert->id,
                        'stock_code' => $alert->stock->code,
                    ]);
                }
            }
        }

        $this->info("Price alerts check completed. Triggered: {$triggeredCount}");

        return 0;
    }
}
