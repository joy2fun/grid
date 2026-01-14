<?php

namespace App\Utilities;

class Helper
{

    /**
     * Calculate the Extended Internal Rate of Return (XIRR)
     *
     * @param array $cashFlows Array of cash flows (positive for inflows, negative for outflows)
     * @param array $dates Array of dates corresponding to each cash flow
     * @param float $guess Optional initial guess for the rate (default 0.1 = 10%)
     * @return float|null The calculated XIRR or null if calculation fails
     */
    public static function calculateXIRR($cashFlows, $dates, $guess = 0.1)
    {
        // Validate inputs
        if (count($cashFlows) !== count($dates) || count($cashFlows) < 2) {
            return null;
        }

        // Check if there's at least one positive and one negative cash flow
        $hasPositive = false;
        $hasNegative = false;

        foreach ($cashFlows as $cf) {
            if ($cf > 0) $hasPositive = true;
            if ($cf < 0) $hasNegative = true;
        }

        if (!$hasPositive || !$hasNegative) {
            return null; // Cannot calculate XIRR without both inflows and outflows
        }

        // Convert dates to timestamps if they're not already
        $timestamps = [];
        foreach ($dates as $date) {
            if (is_string($date)) {
                $timestamps[] = strtotime($date);
            } elseif ($date instanceof \DateTime) {
                $timestamps[] = $date->getTimestamp();
            } else {
                $timestamps[] = $date;
            }
        }

        // Sort cash flows, timestamps, and dates together by timestamp while maintaining their relationship
        array_multisort($timestamps, SORT_ASC, $cashFlows, $dates);

        // Define precision early for use in special cases
        $precision = 1e-6;

        // Special case: if all dates are the same, the XIRR equation becomes CF₁ + CF₂ + ... + CFₙ = 0
        $uniqueTimestamps = array_unique($timestamps);
        if (count($uniqueTimestamps) === 1) {
            // When all dates are the same, the equation is simply the sum of cash flows = 0
            // If the sum is not zero, there's no solution, so return null
            $sumCashFlows = array_sum($cashFlows);
            if (abs($sumCashFlows) > $precision) {
                return null; // No solution exists
            }
            // If sum is zero, any rate satisfies the equation, so return 0
            return 0.0;
        }

        // Additional validation: check for extremely close dates that might cause numerical issues
        $minTimeDiff = PHP_INT_MAX;
        for ($i = 1; $i < count($timestamps); $i++) {
            $timeDiff = abs($timestamps[$i] - $timestamps[$i-1]);
            if ($timeDiff > 0 && $timeDiff < $minTimeDiff) {
                $minTimeDiff = $timeDiff;
            }
        }

        // If the minimum time difference is less than 1 minute, warn about potential precision issues
        // Though we still proceed with the calculation
        if ($minTimeDiff < 60) {
            // Dates are extremely close - may affect numerical precision
            // We continue with the calculation but note that results may be less accurate
        }

        // Newton-Raphson method to solve for XIRR
        $rate = $guess;
        $maxIterations = 100;

        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            $npv = 0;
            $derivative = 0;

            $firstDate = $timestamps[0];

            for ($i = 0; $i < count($cashFlows); $i++) {
                $daysDiff = ($timestamps[$i] - $firstDate) / (60 * 60 * 24); // Days between dates
                $yearsDiff = $daysDiff / 365.0; // Convert to years

                // Handle edge case where rate is too low to prevent division by zero
                if ($rate <= -1) {
                    $rate = -0.999999; // Close to -100% but not equal to -1
                }

                $denominator = pow(1 + $rate, $yearsDiff);

                // If denominator is too close to zero, the rate is invalid
                if (abs($denominator) < $precision) {
                    return null;
                }

                $npv += $cashFlows[$i] / $denominator;

                // Derivative: d/dr [CF/(1+r)^t] = -t*CF/(1+r)^(t+1)
                if ($yearsDiff != 0) {
                    $currentDerivative = (-$yearsDiff * $cashFlows[$i]) / pow(1 + $rate, $yearsDiff + 1);

                    // Check if the derivative is too small to avoid division by zero in Newton-Raphson step
                    if (abs($currentDerivative) < $precision) {
                        $currentDerivative = $precision * ($currentDerivative >= 0 ? 1 : -1);
                    }

                    $derivative += $currentDerivative;
                }
                // When yearsDiff is 0, the derivative contribution is 0
                // (the derivative of CF/(1+r)^0 = CF is 0)
            }

            if (abs($npv) < $precision) {
                break;
            }

            // If derivative is too close to zero, Newton-Raphson method fails
            if (abs($derivative) < $precision) {
                return null; // Cannot continue with Newton-Raphson
            }

            $newRate = $rate - ($npv / $derivative);

            // Prevent the rate from going to -100% or lower which causes mathematical errors
            if ($newRate <= -1) {
                $newRate = max($rate - 0.000001, -0.999999); // Small adjustment, but bounded
            }

            if (abs($newRate - $rate) < $precision) {
                $rate = $newRate;
                break;
            }

            $rate = $newRate;

            // Add bounds checking to prevent extreme values
            if ($rate < -0.999999) { // Just below -100%
                $rate = -0.999999;
            } elseif ($rate > 100) { // Very high rates are unrealistic
                return null;
            }
        }

        // Verify the solution is reasonable
        if ($rate <= -1 || abs($rate) > 100) {
            return null;
        }

        return $rate;
    }
}
