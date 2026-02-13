<div class="mobile-quick-links">
    <style>
        .mobile-quick-links {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .mobile-quick-links a {
            color: rgb(107 114 128); /* text-gray-500 */
            transition: color 0.2s;
        }

        .dark .mobile-quick-links a {
            color: rgb(156 163 175); /* text-gray-400 */
        }

        .mobile-quick-links a:hover {
            color: rgb(55 65 81); /* text-gray-700 */
        }

        .dark .mobile-quick-links a:hover {
            color: rgb(229 231 235); /* text-gray-200 */
        }

        @media (min-width: 1024px) {
            .mobile-quick-links {
                display: none !important;
            }
        }
    </style>

    <a href="{{ \Filament\Pages\Dashboard::getUrl() }}" title="Dashboard">
        <x-filament::icon
            icon="heroicon-o-home"
            style="width: 1.5rem; height: 1.5rem;"
        />
    </a>
    <a href="{{ \App\Filament\Resources\Grids\GridResource::getUrl() }}" title="Grids">
        <x-filament::icon
            icon="heroicon-o-squares-2x2"
            style="width: 1.5rem; height: 1.5rem;"
        />
    </a>
    <a href="{{ \App\Filament\Resources\PriceAlerts\PriceAlertResource::getUrl() }}" title="Alerts">
        <x-filament::icon
            icon="heroicon-o-bell"
            style="width: 1.5rem; height: 1.5rem;"
        />
    </a>
    <a href="{{ \App\Filament\Resources\Stocks\StockResource::getUrl() }}" title="Stocks">
        <x-filament::icon
            icon="heroicon-o-chart-bar"
            style="width: 1.5rem; height: 1.5rem;"
        />
    </a>
    <a href="{{ \App\Filament\Resources\Holdings\HoldingResource::getUrl() }}" title="Holdings">
        <x-filament::icon
            icon="heroicon-o-currency-dollar"
            style="width: 1.5rem; height: 1.5rem;"
        />
    </a>
</div>
