<x-filament-panels::page>
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    @endpush

    <form wire:submit="runBacktest" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit">
                Run Backtest
            </x-filament::button>
        </div>
    </form>

    @if ($results)
        <x-filament::section>
            <x-slot name="heading">
                Backtest Results
            </x-slot>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div style="background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e5e7eb;">
                    <div style="background: #dbeafe; padding: 0.75rem 1.5rem; border-bottom: 1px solid #e5e7eb;">
                        <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #2563eb;">XIRR (Annual Return)</div>
                    </div>
                    <div style="padding: 1.5rem;">
                        <div style="font-size: 2.25rem; font-weight: 700; color: {{ $results['xirr'] >= 0 ? '#16a34a' : '#dc2626' }};">
                            {{ $results['xirr'] !== null ? number_format($results['xirr'] * 100, 3) . '%' : 'N/A' }}
                        </div>
                    </div>
                </div>

                <div style="background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e5e7eb;">
                    <div style="background: #f3e8ff; padding: 0.75rem 1.5rem; border-bottom: 1px solid #e5e7eb;">
                        <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9333ea;">Total Profit/Loss</div>
                    </div>
                    <div style="padding: 1.5rem;">
                        <div style="font-size: 2.25rem; font-weight: 700; color: {{ $results['total_profit'] >= 0 ? '#16a34a' : '#dc2626' }};">
                            ${{ number_format($results['total_profit'], 3) }}
                        </div>
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; font-size: 0.75rem; color: #6b7280;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                <span>Cash:</span>
                                <span style="font-weight: 600;">${{ number_format($results['net_cash'], 3) }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>Holdings:</span>
                                <span style="font-weight: 600;">${{ number_format($results['holding_value'], 3) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e5e7eb;">
                    <div style="background: #fee2e2; padding: 0.75rem 1.5rem; border-bottom: 1px solid #e5e7eb;">
                        <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #dc2626;">Max Cash Required</div>
                    </div>
                    <div style="padding: 1.5rem;">
                        <div style="font-size: 2.25rem; font-weight: 700; color: #dc2626;">
                            ${{ number_format($results['max_cash_occupied'], 3) }}
                        </div>
                        <div style="margin-top: 0.75rem; font-size: 0.875rem; color: #6b7280;">
                            Peak Capital Needed
                        </div>
                    </div>
                </div>

                <div style="background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e5e7eb;">
                    <div style="background: #dcfce7; padding: 0.75rem 1.5rem; border-bottom: 1px solid #e5e7eb;">
                        <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #16a34a;">Trades Executed</div>
                    </div>
                    <div style="padding: 1.5rem;">
                        <div style="font-size: 2.25rem; font-weight: 700; color: #111827;">
                            {{ $results['trades_count'] }}
                        </div>
                        <div style="margin-top: 0.75rem; font-size: 0.875rem; color: #6b7280;">
                            Final Shares: <span style="font-weight: 600;">{{ number_format($results['final_shares']) }}</span>
                        </div>
                    </div>
                </div>

                <div style="background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e5e7eb;">
                    <div style="background: #fef3c7; padding: 0.75rem 1.5rem; border-bottom: 1px solid #e5e7eb;">
                        <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #d97706;">Final Stock Price</div>
                    </div>
                    <div style="padding: 1.5rem;">
                        <div style="font-size: 2.25rem; font-weight: 700; color: #111827;">
                            ${{ number_format($results['final_price'], 3) }}
                        </div>
                        <div style="margin-top: 0.75rem; font-size: 0.875rem; color: #6b7280;">
                            Position Value
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                 {{-- Chart Container --}}
                 <div
                    wire:key="chart-{{ md5(json_encode($results['chart_data'])) }}"
                    x-data="{
                        init() {
                            let chartData = {{ json_encode($results['chart_data']) }};
                            let annotations = {{ json_encode($results['annotations']) }};
                            
                            // Extract dates for category labels
                            let categories = chartData.map(item => item.x);
                            
                            // Convert annotations to use date strings and points
                            let pointAnnotations = annotations.map(ann => {
                                let dateStr = new Date(ann.x).toISOString().split('T')[0];
                                let index = categories.indexOf(dateStr);
                                
                                // Find the y value (close price) for this date
                                let dataPoint = chartData[index];
                                let yValue = dataPoint ? dataPoint.y[3] : 0; // [3] is close price
                                
                                return {
                                    x: dateStr, // Use date string instead of index
                                    y: yValue,
                                    marker: {
                                        size: 6,
                                        fillColor: ann.borderColor,
                                        strokeColor: '#fff',
                                        strokeWidth: 2,
                                        shape: 'circle'
                                    },
                                    label: {
                                        ...ann.label,
                                        text: ann.label.text,
                                        offsetY: -10
                                    }
                                };
                            });
                            
                            let options = {
                                series: [{
                                    data: chartData.map(item => ({
                                        x: item.x,
                                        y: item.y
                                    }))
                                }],
                                chart: {
                                    type: 'candlestick',
                                    height: 350,
                                    toolbar: {
                                        show: true,
                                        tools: {
                                            zoom: true,
                                            zoomin: true,
                                            zoomout: true,
                                            pan: true,
                                            reset: true
                                        }
                                    }
                                },
                                title: {
                                    text: 'Backtest Simulation',
                                    align: 'left'
                                },
                                xaxis: {
                                    type: 'category',
                                    categories: categories,
                                    tickAmount: 10,
                                    labels: {
                                        rotate: -45,
                                        rotateAlways: false,
                                        hideOverlappingLabels: true,
                                        trim: true,
                                        style: {
                                            fontSize: '11px'
                                        }
                                    }
                                },
                                yaxis: {
                                    tooltip: {
                                        enabled: true
                                    },
                                    labels: {
                                        formatter: function(val) {
                                            return '$' + val.toFixed(3);
                                        }
                                    }
                                },
                                tooltip: {
                                    x: {
                                        show: true
                                    }
                                },
                                annotations: {
                                    points: pointAnnotations
                                }
                            };

                            let chart = new ApexCharts(this.$refs.chart, options);
                            chart.render();
                        }
                    }"
                 >
                    <div x-ref="chart"></div>
                 </div>
            </div>
            
            <div style="margin-top: 2rem;">
                <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: #111827;">Trade Log & Cash Flows</h3>
                <div class="fi-ta-ctn divide-y divide-gray-200 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/10 dark:bg-gray-900 dark:ring-white/10">
                    <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                        <thead class="divide-y divide-gray-200 dark:divide-white/5">
                            <tr class="bg-gray-50 dark:bg-white/5">
                                <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Date</span>
                                </th>
                                <th class="fi-ta-header-cell px-3 py-3.5">
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Type</span>
                                </th>
                                <th class="fi-ta-header-cell px-3 py-3.5">
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Price</span>
                                </th>
                                <th class="fi-ta-header-cell px-3 py-3.5">
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Shares</span>
                                </th>
                                <th class="fi-ta-header-cell px-3 py-3.5">
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Cash Flow</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @foreach ($results['trades'] as $index => $trade)
                                <tr class="fi-ta-row">
                                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                        <div class="fi-ta-col-wrp px-3 py-4">
                                            <div class="fi-ta-text-item text-sm text-gray-950 dark:text-white">
                                                {{ $trade['date'] }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                        <div class="fi-ta-col-wrp px-3 py-4">
                                            <span class="fi-badge uppercase {{ $trade['type'] === 'buy' ? 'fi-color-success' : 'fi-color-danger' }}">
                                                {{ $trade['type'] }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                        <div class="fi-ta-col-wrp px-3 py-4">
                                            <div class="fi-ta-text-item text-sm text-gray-950 dark:text-white">
                                                ${{ number_format($trade['price'], 3) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                        <div class="fi-ta-col-wrp px-3 py-4">
                                            <div class="fi-ta-text-item text-sm text-gray-950 dark:text-white">
                                                {{ number_format($trade['shares']) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                        <div class="fi-ta-col-wrp px-3 py-4">
                                            <div class="fi-ta-text-item text-sm font-mono {{ $results['cash_flows'][$index]['amount'] < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">
                                                ${{ number_format($results['cash_flows'][$index]['amount'], 3) }}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            {{-- Final holding value row --}}
                            @if(count($results['cash_flows']) > count($results['trades']))
                                <tr class="fi-ta-row" style="background-color: #f9fafb;">
                                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                        <div class="fi-ta-col-wrp px-3 py-4">
                                            <div class="fi-ta-text-item text-sm text-gray-950 dark:text-white">
                                                {{ $results['cash_flows'][count($results['cash_flows']) - 1]['date'] }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3" colspan="3">
                                        <div class="fi-ta-col-wrp px-3 py-4">
                                            <span class="fi-badge fi-color-primary">
                                                FINAL POSITION
                                            </span>
                                            <span style="margin-left: 0.5rem; font-size: 0.875rem; color: #6b7280;">
                                                {{ number_format($results['final_shares']) }} shares @ ${{ number_format($results['final_price'], 3) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                        <div class="fi-ta-col-wrp px-3 py-4">
                                            <div class="fi-ta-text-item text-sm font-mono text-success-600 dark:text-success-400" style="font-weight: 600;">
                                                ${{ number_format($results['cash_flows'][count($results['cash_flows']) - 1]['amount'], 3) }}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 1rem; padding: 1rem; background: #eff6ff; border-radius: 0.5rem; border: 1px solid #bfdbfe;">
                    <p style="font-size: 0.875rem; color: #1e40af;">
                        <strong>Note:</strong> Cash flows show the actual money movement for XIRR calculation. 
                        Negative values (red) = money out (buys), Positive values (green) = money in (sells/final position).
                    </p>
                </div>
            </div>

        </x-filament::section>
    @endif
</x-filament-panels::page>
