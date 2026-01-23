<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/grid-trading', \App\Mcp\Servers\GridTradingServer::class)
    ->middleware(['auth:sanctum', 'throttle:60,1']);
