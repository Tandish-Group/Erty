<?php

namespace Tests\Unit;

use App\Http\Middleware\PreventBackHistory;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PreventBackHistoryTest extends TestCase
{
    public function test_normal_and_streamed_responses_keep_their_content_and_receive_cache_headers(): void
    {
        foreach ([new Response('page'), new StreamedResponse(static function () { echo 'export'; })] as $response) {
            $response->headers->set('Content-Disposition', 'attachment; filename="ledger.csv"');
            $result = (new PreventBackHistory)->handle(Request::create('/'), fn () => $response);

            $this->assertSame($response, $result);
            $this->assertTrue($result->headers->hasCacheControlDirective('no-store'));
            $this->assertSame('no-cache', $result->headers->get('Pragma'));
            $this->assertSame('Fri, 01 Jan 1990 00:00:00 GMT', $result->headers->get('Expires'));
            $this->assertSame('attachment; filename="ledger.csv"', $result->headers->get('Content-Disposition'));
        }
    }
}
