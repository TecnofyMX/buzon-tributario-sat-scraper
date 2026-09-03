<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Services;

use GuzzleHttp\RequestOptions;
use Symfony\Component\DomCrawler\Crawler;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\InvalidCaptchaException;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\InvalidCredentialsException;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\LoginPageNotLoadedException;
use Tecnofy\BuzonTributarioSatScraper\Internal\FormParser;
use Tecnofy\BuzonTributarioSatScraper\Internal\HttpRequester;
use Tecnofy\BuzonTributarioSatScraper\Internal\Page;
use Tecnofy\BuzonTributarioSatScraper\Url;

final readonly class AuthenticationService
{
    public function __construct(
        private HttpRequester $requester,
        private FormParser $formParser,
        private CaptchaService $captchaService,
        private string $rfc,
        private string $password,
    ) {
    }

    public function login(): Page
    {
        $portal = $this->requester->request('GET', Url::LOGIN_PAGE);
        $loginPage = $this->loadLoginFrame($portal);
        $form = $this->formParser->extract($loginPage, ['form#IDPLogin', 'form']);
        $captcha = $this->captchaService->resolve($loginPage);
        $fields = array_replace($form->fields, [
            'Ecom_User_ID' => $this->rfc,
            'Ecom_Password' => $this->password,
            'userCaptcha' => $captcha,
            'submit' => 'Enviar',
        ]);

        $result = $this->requester->request($form->method, $form->action, [
            RequestOptions::FORM_PARAMS => $fields,
            RequestOptions::HEADERS => [
                'Origin' => $this->origin($form->action),
                'Referer' => $loginPage->uri,
            ],
        ]);
        $this->checkResult($result);

        return $result;
    }

    public function logout(): void
    {
        $this->requester->request('GET', Url::LOGOUT_SATELLITE);
        $this->requester->request('GET', Url::LOGOUT_IDP);
    }

    private function loadLoginFrame(Page $portal): Page
    {
        $crawler = new Crawler($portal->html, $portal->uri);
        $frames = $crawler->filter('iframe#iframetoload, iframe[src*="lanzador"], iframe[src*="login"]');
        if (0 === $frames->count()) {
            throw new LoginPageNotLoadedException('The SAT login iframe was not found.');
        }

        $source = $frames->first()->attr('src');
        if (null === $source || '' === trim($source)) {
            throw new LoginPageNotLoadedException('The SAT login iframe has no source.');
        }

        return $this->requester->request('GET', $this->formParser->resolve($portal->uri, $source));
    }

    private function checkResult(Page $page): void
    {
        $content = $this->normalize(strip_tags($page->html));
        if (str_contains($content, 'captcha incorrect') || str_contains($content, 'codigo captcha incorrect')) {
            throw new InvalidCaptchaException('The SAT rejected the captcha.');
        }

        if (
            str_contains($content, 'contrasena incorrect')
            || str_contains($content, 'usuario o contrasena')
            || str_contains($content, 'credenciales incorrect')
        ) {
            throw new InvalidCredentialsException('The SAT rejected the RFC or password.');
        }

        if (str_contains($page->html, 'name="userCaptcha"') && ! str_contains($page->html, 'SAMLResponse')) {
            throw new InvalidCaptchaException('The SAT returned the login form again.');
        }
    }

    private function origin(string $uri): string
    {
        $parts = parse_url($uri);

        return sprintf('%s://%s', $parts['scheme'] ?? 'https', $parts['host'] ?? 'login.siat.sat.gob.mx');
    }

    private function normalize(string $value): string
    {
        return strtolower(strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']));
    }
}
