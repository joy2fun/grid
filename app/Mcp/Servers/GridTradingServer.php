<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;

class GridTradingServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Grid Trading Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        This server provides tools for managing grid trading operations, including saving and querying trades, stocks, and historical prices.

        Use the `save-trades` tool to record trade executions. The tool will automatically create stock records if they don't exist.

        Use the `query-trades` tool to analyze trading history. You can filter by time range, stock code/name, trade side (buy/sell), and grid ID. The tool returns a summary with buy/sell statistics and a detailed trade list for analysis.

        Use the `query-stocks` tool to query stock information. You can filter by code, name, price range, rise percentage, and whether the stock has trades. The tool returns a formatted list of stocks with current prices and statistics.

        Use the `query-day-prices` tool to query historical daily price data (OHLCV). You can filter by stock code/name, date range, close price range, and volume. The tool returns price statistics and formatted OHLCV data for technical analysis.
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        \App\Mcp\Tools\SaveTradesTool::class,
        \App\Mcp\Tools\QueryTradesTool::class,
        \App\Mcp\Tools\QueryStocksTool::class,
        \App\Mcp\Tools\QueryDayPricesTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
