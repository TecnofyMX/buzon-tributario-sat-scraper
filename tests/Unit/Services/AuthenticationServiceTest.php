<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Tests\Unit\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PhpCfdi\ImageCaptchaResolver\CaptchaAnswer;
use PhpCfdi\ImageCaptchaResolver\CaptchaAnswerInterface;
use PhpCfdi\ImageCaptchaResolver\CaptchaImageInterface;
use PhpCfdi\ImageCaptchaResolver\CaptchaResolverInterface;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\InvalidCredentialsException;
use Tecnofy\BuzonTributarioSatScraper\Internal\HttpRequester;
use Tecnofy\BuzonTributarioSatScraper\Services\AuthenticationService;
use Tecnofy\BuzonTributarioSatScraper\Services\CaptchaService;
use Tecnofy\BuzonTributarioSatScraper\Tests\TestCase;

final class AuthenticationServiceTest extends TestCase
{
    public function testInvalidCredentialsAreReportedWithoutSensitiveResponseContent(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], '<html><body>Aplicación inicializada</body></html>'),
            new Response(200, [], self::fixture('login-form.html')),
            new Response(200, [], '<html><body>Usuario o contraseña incorrectos: PRIVATE-RFC</body></html>'),
        ]))]);
        $requester = new HttpRequester($client);
        $resolver = new class implements CaptchaResolverInterface {
            public function resolve(CaptchaImageInterface $image): CaptchaAnswerInterface
            {
                return new CaptchaAnswer('ABCDE');
            }
        };
        $service = new AuthenticationService(
            $requester,
            new CaptchaService($resolver),
            'PRIVATE-RFC',
            'PRIVATE-PASSWORD',
        );

        try {
            $service->login();
            self::fail('Invalid credentials must raise an exception.');
        } catch (InvalidCredentialsException $exception) {
            self::assertStringNotContainsString('PRIVATE-RFC', $exception->getMessage());
            self::assertStringNotContainsString('PRIVATE-PASSWORD', $exception->getMessage());
        }
    }
}
