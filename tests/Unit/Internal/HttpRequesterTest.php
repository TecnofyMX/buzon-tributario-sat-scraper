<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Tests\Unit\Internal;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\NetworkException;
use Tecnofy\BuzonTributarioSatScraper\Internal\HttpRequester;
use Tecnofy\BuzonTributarioSatScraper\Tests\TestCase;

final class HttpRequesterTest extends TestCase
{
    public function testNetworkFailureReportsSafeRequestContext(): void
    {
        $request = new Request('POST', 'https://login.siat.sat.gob.mx/nidp/app/login');
        $failure = new ConnectException('Sensitive transport message', $request, null, ['errno' => 28]);
        $requester = new HttpRequester(new Client(['handler' => new MockHandler([$failure])]));

        try {
            $requester->request('POST', 'https://login.siat.sat.gob.mx/nidp/app/login?token=secret');
            self::fail('The request should have failed.');
        } catch (NetworkException $exception) {
            self::assertSame(
                'The SAT POST request to https://login.siat.sat.gob.mx/nidp/app/login failed'
                    . ' with cURL error 28 (request timed out).',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('secret', $exception->getMessage());
            self::assertSame($failure, $exception->getPrevious());
        }
    }
}
