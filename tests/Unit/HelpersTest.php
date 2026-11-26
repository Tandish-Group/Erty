<?php

namespace Tests\Unit;

use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_money_uses_the_given_or_the_installation_currency(): void
    {
        config(['property.currency' => 'UGX']);
        $this->assertSame('UGX', default_currency());
        $this->assertSame('UGX 12,500.00', money(12500));
        $this->assertSame('KES 1,234', money('1234.4', 'KES', 0));
        $this->assertSame('UGX 0.00', money(null));
    }

    public function test_file_size_reads_like_a_person_would_say_it(): void
    {
        $this->assertSame('0 B', file_size(0));
        $this->assertSame('512 B', file_size(512));
        $this->assertSame('1.5 KB', file_size(1536));
        $this->assertSame('2 MB', file_size(2 * 1024 * 1024));
    }

    public function test_exchange_rates_are_optional_and_never_break_totals(): void
    {
        config(['services.exchange.exchange_rate_api' => null, 'services.exchange.exchange_rate_api_key' => null, 'property.currency' => 'KES']);
        $rates = app(ExchangeRateService::class);
        $this->assertSame([], $rates->rates());
        $this->assertSame(1.0, $rates->rate('USD'));
        $this->assertSame(250.0, $rates->toDefault(250, 'USD'));

        config(['services.exchange.exchange_rate_api' => 'https://rates.test/v6', 'services.exchange.exchange_rate_api_key' => 'k', 'cache.default' => 'array']);
        Http::fake(['rates.test/*' => Http::response(['conversion_rates' => ['KES' => 1, 'USD' => 0.0077, 'BAD' => 0]])]);
        $this->assertSame(['KES' => 1.0, 'USD' => 0.0077], $rates->rates());
        $this->assertEqualsWithDelta(1.0, $rates->toDefault(0.0077, 'USD'), 0.0001);
    }
}
