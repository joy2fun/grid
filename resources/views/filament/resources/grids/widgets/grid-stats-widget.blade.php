<x-filament-widgets::widget>
    @php
        $results = $metrics;
    @endphp

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
        <div style="background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e5e7eb;" class="dark:bg-gray-900 dark:border-white/10">
            <div style="background: #dbeafe; padding: 0.75rem 1.5rem; border-bottom: 1px solid #e5e7eb;" class="dark:bg-blue-900/40 dark:border-white/10">
                <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #2563eb;" class="dark:text-blue-400">XIRR (Annual Return)</div>
            </div>
            <div style="padding: 1.5rem;">
                <div style="font-size: 2.25rem; font-weight: 700; color: {{ ($results['xirr'] ?? 0) >= 0 ? '#16a34a' : '#dc2626' }};">
                    {{ $results['xirr'] !== null ? number_format($results['xirr'] * 100, 3) . '%' : 'N/A' }}
                </div>
            </div>
        </div>

        <div style="background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e5e7eb;" class="dark:bg-gray-900 dark:border-white/10">
            <div style="background: #f3e8ff; padding: 0.75rem 1.5rem; border-bottom: 1px solid #e5e7eb;" class="dark:bg-purple-900/40 dark:border-white/10">
                <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9333ea;" class="dark:text-purple-400">Total Profit/Loss</div>
            </div>
            <div style="padding: 1.5rem;">
                <div style="font-size: 2.25rem; font-weight: 700; color: {{ $results['total_profit'] >= 0 ? '#16a34a' : '#dc2626' }};">
                    ¥{{ number_format($results['total_profit'], 0) }}
                </div>
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; font-size: 0.75rem; color: #6b7280;" class="dark:border-white/10 dark:text-gray-400">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                        <span>Cash:</span>
                        <span style="font-weight: 600;">¥{{ number_format($results['net_cash'], 3) }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Holdings:</span>
                        <span style="font-weight: 600;">¥{{ number_format($results['holding_value'], 3) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div style="background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e5e7eb;" class="dark:bg-gray-900 dark:border-white/10">
            <div style="background: #fee2e2; padding: 0.75rem 1.5rem; border-bottom: 1px solid #e5e7eb;" class="dark:bg-red-900/40 dark:border-white/10">
                <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #dc2626;" class="dark:text-red-400">Max Cash Required</div>
            </div>
            <div style="padding: 1.5rem;">
                <div style="font-size: 2.25rem; font-weight: 700; color: #dc2626;">
                    ¥{{ number_format($results['max_cash_occupied'], 0) }}
                </div>
                <div style="margin-top: 0.75rem; font-size: 0.875rem; color: #6b7280;" class="dark:text-gray-400">
                    Peak Capital Needed
                </div>
            </div>
        </div>

        <div style="background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e5e7eb;" class="dark:bg-gray-900 dark:border-white/10">
            <div style="background: #dcfce7; padding: 0.75rem 1.5rem; border-bottom: 1px solid #e5e7eb;" class="dark:bg-green-900/40 dark:border-white/10">
                <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #16a34a;" class="dark:text-green-400">Status</div>
            </div>
            <div style="padding: 1.5rem;">
                <div style="font-size: 1.5rem; font-weight: 700; color: #111827;" class="dark:text-white">
                    {{ $results['trades_count'] }} Trades
                </div>
                <div style="margin-top: 0.75rem; font-size: 0.875rem; color: #6b7280;" class="dark:text-gray-400">
                    Final Shares: <span style="font-weight: 600;">{{ number_format($results['final_shares']) }}</span>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
