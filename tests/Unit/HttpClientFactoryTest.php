<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Tests\Unit;

use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\RequestOptions;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\HttpClientInvalidOption;
use Tecnofy\BuzonTributarioSatScraper\HttpClientFactory;
use Tecnofy\BuzonTributarioSatScraper\Tests\TestCase;

final class HttpClientFactoryTest extends TestCase
{
    public function testDefaultOptionsContainCookieJar(): void
    {
        $options = (new HttpClientFactory())->buildOptions();

        self::assertInstanceOf(CookieJarInterface::class, $options[RequestOptions::COOKIES]);
        self::assertSame(120, $options[RequestOptions::TIMEOUT]);
        self::assertTrue($options[RequestOptions::VERIFY]);
    }

    public function testRedirectsCannotBeDisabled(): void
    {
        $this->expectException(HttpClientInvalidOption::class);
        HttpClientFactory::create([RequestOptions::ALLOW_REDIRECTS => false]);
    }
}
