<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Exchange rates against the installation currency (default_currency()), from an
 * ExchangeRate-API compatible endpoint: GET {EXCHANGE_RATE_API}/{EXCHANGE_RATE_API_KEY}/latest/{BASE}.
 *
 * Optional. Without configuration, or while the API is unreachable, every rate is 1, so totals that mix
 * currencies are summed as they are instead of failing.
 */
class ExchangeRateService
{
    /** @return array<string, float> currency code => units per 1 of the base currency */
    public function rates(): array
    {
        $url = rtrim((string) config('services.exchange.exchange_rate_api'), '/');
        $key = (string) config('services.exchange.exchange_rate_api_key');
        if ($url === '' || $key === '') {
            return [];
        }

        $base = default_currency();
        $cacheKey = "exchange_rates:{$base}";
        if (($cached = Cache::get($cacheKey)) !== null) {
            return $cached;
        }

        try {
            $response = Http::timeout(5)->get("{$url}/{$key}/latest/{$base}");
            $rates = $response->successful() ? (array) $response->json('conversion_rates', []) : [];
        } catch (\Throwable $e) {
            $rates = [];
            Log::warning('Exchange rates unavailable: ' . $e->getMessage());
        }

        $rates = array_map('floatval', array_filter($rates, fn ($rate) => is_numeric($rate) && $rate > 0));
        // Keep good rates for an hour; retry a failed lookup after five minutes.
        Cache::put($cacheKey, $rates, $rates ? now()->addHour() : now()->addMinutes(5));

        return $rates;
    }

    /** Units of $currency per 1 of the installation currency (1 when unknown). */
    public function rate(?string $currency): float
    {
        if (!$currency || $currency === default_currency()) {
            return 1.0;
        }

        return $this->rates()[$currency] ?? 1.0;
    }

    /** An amount in $currency, expressed in the installation currency. */
    public function toDefault(int|float|string|null $amount, ?string $currency): float
    {
        return (float) $amount / $this->rate($currency);
    }
}
