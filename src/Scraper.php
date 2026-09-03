<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper;

use GuzzleHttp\ClientInterface;
use PhpCfdi\ImageCaptchaResolver\CaptchaResolverInterface;
use Tecnofy\BuzonTributarioSatScraper\Internal\FormParser;
use Tecnofy\BuzonTributarioSatScraper\Internal\HttpRequester;
use Tecnofy\BuzonTributarioSatScraper\Services\AuthenticationService;
use Tecnofy\BuzonTributarioSatScraper\Services\CaptchaService;
use Tecnofy\BuzonTributarioSatScraper\Services\CommunicationParser;
use Tecnofy\BuzonTributarioSatScraper\Services\CommunicationService;
use Tecnofy\BuzonTributarioSatScraper\Services\NotificationParser;
use Tecnofy\BuzonTributarioSatScraper\Services\NotificationService;
use Tecnofy\BuzonTributarioSatScraper\Services\SsoHandler;
use Throwable;

final class Scraper implements ScraperInterface
{
    public function __construct(
        private AuthenticationService $authenticationService,
        private SsoHandler $ssoHandler,
        private NotificationService $notificationService,
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
        $formParser = new FormParser();

        return new self(
            new AuthenticationService(
                $requester,
                $formParser,
                new CaptchaService($captchaResolver),
                $rfc,
                $password,
            ),
            new SsoHandler($requester, $formParser),
            new NotificationService($requester, new NotificationParser($formParser)),
            new CommunicationService($requester, new CommunicationParser()),
        );
    }

    public function notifications(): NotificationCollection
    {
        $failure = null;
        try {
            $authenticatedPage = $this->authenticationService->login();
            $buzonPage = $this->ssoHandler->handle($authenticatedPage);

            return $this->notificationService->collect($buzonPage);
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

    public function unreadCommunications(): CommunicationCollection
    {
        $failure = null;
        try {
            $authenticatedPage = $this->authenticationService->login();
            $buzonPage = $this->ssoHandler->handle($authenticatedPage);

            return $this->communicationService->collectUnread($buzonPage);
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
