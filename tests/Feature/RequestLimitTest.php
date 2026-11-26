<?php

namespace Tests\Feature;

use App\Http\Middleware\DynamicThrottle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RequestLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'throttle.signed_in' => [3, 1], 'throttle.guest' => [2, 1]]);
        Cache::flush();
    }

    private function hit(string $uri, ?int $userId = null): int
    {
        $request = Request::create($uri, 'GET', server: ['REMOTE_ADDR' => '10.0.0.5']);
        $request->setUserResolver(fn () => $userId ? (new User)->forceFill(['id' => $userId]) : null);
        return (new DynamicThrottle)->handle($request, fn () => response('ok'))->getStatusCode();
    }

    public function test_people_at_one_office_address_each_have_their_own_limit(): void
    {
        foreach (range(1, 3) as $i) $this->assertSame(200, $this->hit('/dashboard', 1));
        $this->assertSame(429, $this->hit('/dashboard', 1));
        $this->assertSame(200, $this->hit('/dashboard', 2), 'a colleague on the same address is not blocked');
    }

    public function test_sign_in_stays_limited_per_address(): void
    {
        config(['throttle.rate_limits' => ['login' => [2, 1]]]);
        $this->assertSame(200, $this->hit('/login'));
        $this->assertSame(200, $this->hit('/login'));
        $this->assertSame(429, $this->hit('/login'));
    }

    public function test_wildcard_paths_match(): void
    {
        config(['throttle.rate_limits' => ['password/reset*' => [1, 1]]]);
        $this->assertSame(200, $this->hit('/password/reset/abc'));
        $this->assertSame(429, $this->hit('/password/reset/def'));
    }
}
