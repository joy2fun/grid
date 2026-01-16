<?php

namespace App\Utilities;

class StockPriceService
{
    /**
     * Fetch real-time stock prices from the API
     *
     * @param  string  ...$codes  Stock codes to fetch (e.g., 'sh601166', 'sz000001')
     * @return array Associative array of stock data indexed by code
     */
    public static function getRealtimePrices(...$codes): array
    {
        if (empty($codes)) {
            return [];
        }

        // Validate and format codes for the API request
        $validatedCodes = [];
        foreach ($codes as $code) {
            if (! is_string($code)) {
                throw new \InvalidArgumentException('Stock code must be a string: '.var_export($code, true));
            }

            // Ensure the code follows the format like sh601166 or sz000001
            if (! preg_match('/^(sh|sz)\d{6}$/', $code)) {
                throw new \InvalidArgumentException("Invalid stock code format: {$code}. Expected format: shXXXXXX or szXXXXXX");
            }
            $validatedCodes[] = $code;
        }

        $apiUrl = 'https://qt.gtimg.cn/?q='.implode(',', $validatedCodes);

        // Make HTTP request to fetch the data
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (compatible; StockPriceBot/1.0)',
            ],
        ]);

        $response = @file_get_contents($apiUrl, false, $context);

        if ($response === false) {
            throw new \RuntimeException('Failed to fetch stock prices from API');
        }

        // Parse the JavaScript response
        return self::parseResponse($response, $validatedCodes);
    }

    /**
     * Parse the JavaScript response to extract stock data
     *
     * @param  string  $response  The raw JavaScript response
     * @param  array  $codes  The requested stock codes
     * @return array Parsed stock data
     */
    private static function parseResponse(string $response, array $codes): array
    {
        $result = [];

        foreach ($codes as $code) {
            // Create the variable name pattern to search for
            $varName = 'v_'.preg_quote($code, '/');

            // Extract the value assigned to this variable
            if (preg_match("/{$varName}=\"([^\"]*)\"/", $response, $matches)) {
                $dataString = $matches[1];

                // Split the data string by '~'
                $dataArray = explode('~', $dataString);

                // Map the data according to the specification
                // Index 5: Open price
                // Index 33: High price
                // Index 34: Low price
                // Index 35: Current/Close price (before first slash)
                $currentPrice = null;
                if (isset($dataArray[35])) {
                    $priceInfo = $dataArray[35];
                    $slashPos = strpos($priceInfo, '/');
                    if ($slashPos !== false) {
                        $currentPrice = substr($priceInfo, 0, $slashPos);
                    } else {
                        $currentPrice = $priceInfo;
                    }
                }

                // Validate numeric values
                $openPrice = self::validateNumericValue($dataArray[5] ?? null);
                $highPrice = self::validateNumericValue($dataArray[33] ?? null);
                $lowPrice = self::validateNumericValue($dataArray[34] ?? null);
                $currentPrice = self::validateNumericValue($currentPrice);

                $result[$code] = [
                    'name' => iconv('gbk', 'utf-8', $dataArray[1] ?? null),
                    'code' => $dataArray[2] ?? $code,
                    'current_price' => $currentPrice,
                    'high_price' => $highPrice,  // High price at index 33
                    'low_price' => $lowPrice,  // Low price at index 34
                    'close_price' => $currentPrice,  // Close price is the same as current
                    'open_price' => $openPrice,  // Open price at index 5
                    'volume' => self::validateNumericValue($dataArray[6] ?? null), // Volume at index 6
                    'timestamp' => self::convertToDatestring($dataArray[30] ?? null),
                ];
            } else {
                // If the code wasn't found in the response, return null for this code
                $result[$code] = null;
            }
        }

        return $result;
    }

    /**
     * Validates and sanitizes numeric values from the API response
     *
     * @param  mixed  $value  The value to validate
     * @return float|null The validated numeric value or null
     */
    private static function validateNumericValue($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Convert to float if it's a valid numeric string
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    public static function convertToDatestring(?string $dateString)
    {
        if ($dateString === null) {
            return null;
        }

        return \DateTime::createFromFormat('YmdHis', $dateString)->format('Y-m-d');
    }
}
