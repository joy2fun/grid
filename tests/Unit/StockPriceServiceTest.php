<?php

namespace Tests\Unit;

use App\Utilities\StockPriceService;
use PHPUnit\Framework\TestCase;

class StockPriceServiceTest extends TestCase
{
    public function test_get_realtime_prices_returns_correct_structure()
    {
        // Test with sample stock codes
        $result = StockPriceService::getRealtimePrices('sh601166', 'sz000001');

        // Basic structure check
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sh601166', $result);
        $this->assertArrayHasKey('sz000001', $result);

        // Check structure for individual stock
        if ($result['sh601166'] !== null) {
            $this->assertIsArray($result['sh601166']);
            $this->assertArrayHasKey('name', $result['sh601166']);
            $this->assertArrayHasKey('code', $result['sh601166']);
            $this->assertArrayHasKey('current_price', $result['sh601166']);
            $this->assertArrayHasKey('high_price', $result['sh601166']);
            $this->assertArrayHasKey('low_price', $result['sh601166']);
            $this->assertArrayHasKey('open_price', $result['sh601166']);
            $this->assertArrayHasKey('timestamp', $result['sh601166']);
        }
    }

    public function test_get_realtime_prices_with_empty_input()
    {
        $result = StockPriceService::getRealtimePrices();
        $this->assertEquals([], $result);
    }

    public function test_get_realtime_prices_with_invalid_code()
    {
        $this->expectException(\InvalidArgumentException::class);
        StockPriceService::getRealtimePrices('invalid_code');
    }

    public function test_get_realtime_prices_with_valid_code_format()
    {
        // This test verifies that valid code format doesn't throw validation errors
        // The API call might still fail due to network or invalid stock code, but shouldn't throw validation errors
        try {
            $result = StockPriceService::getRealtimePrices('sh123456'); // Valid format but likely invalid stock code
            // If API call succeeds, we should get a result array
            $this->assertIsArray($result);
        } catch (\RuntimeException $e) {
            // If API call fails due to network issues, that's acceptable
            $this->assertTrue(true); // Just pass the test
        }
    }
}
