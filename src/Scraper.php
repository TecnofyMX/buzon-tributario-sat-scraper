<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper;

use GuzzleHttp\ClientInterface;
use PhpCfdi\ImageCaptchaResolver\CaptchaResolverInterface;
use Tecnofy\BuzonTributarioSatScraper\Internal\HttpRequester;
use Tecnofy\BuzonTributarioSatScraper\Services\AuthenticationService;
use Tecnofy\BuzonTributarioSatScraper\Services\CaptchaService;
use Tecnofy\BuzonTributarioSatScraper\Services\CommunicationParser;
use Tecnofy\BuzonTributarioSatScraper\Services\CommunicationService;
use Tecnofy\BuzonTributarioSatScraper\Services\SsoHandler;
use Throwable;

final class Scraper implements ScraperInterface
{
    public function __construct(
        private AuthenticationService $authenticationService,
        private SsoHandler $ssoHandler,
        private CommunicationService $communicationService,
    ) {
    }

    public static function create(
        ClientInterface $client,
        CaptchaResolverInterface $captchaResolver,
        string $rfc,
        string $password,
    ): self {
        $requester = new HttpRequester($client);
        $ssoHandler = new SsoHandler($requester);

        return new self(
            new AuthenticationService(
                $requester,
                new CaptchaService($captchaResolver),
                $rfc,
                $password,
            ),
            $ssoHandler,
            new CommunicationService($requester, new CommunicationParser(), $ssoHandler),
        );
    }

    public function unreadCommunications(): CommunicationCollection
    {
        $failure = null;
        try {
            $authenticatedPage = $this->authenticationService->login();
            $authenticatedPage = $this->ssoHandler->handle($authenticatedPage);

            return $this->communicationService->collectUnread($authenticatedPage);
        } catch (Throwable $exception) {
            $failure = $exception;
            throw $exception;
        } finally {
            try {
                $this->authenticationService->logout();
            } catch (Throwable $logoutFailure) {
                if (null === $failure) {
                    throw $logoutFailure;
                }
            }
        }
    }
}
