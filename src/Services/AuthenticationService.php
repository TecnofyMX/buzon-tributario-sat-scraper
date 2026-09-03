<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Services;

use GuzzleHttp\RequestOptions;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\InvalidCaptchaException;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\InvalidCredentialsException;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\LoginPageNotLoadedException;
use Tecnofy\BuzonTributarioSatScraper\Internal\HttpRequester;
use Tecnofy\BuzonTributarioSatScraper\Internal\Page;
use Tecnofy\BuzonTributarioSatScraper\Url;

final class AuthenticationService
{
    public function __construct(
        private HttpRequester $requester,
        private CaptchaService $captchaService,
        private string $rfc,
        private string $password,
    ) {
    }

    public function login(): Page
    {
        $this->requester->request('GET', Url::LOGIN_APP, [
            RequestOptions::QUERY => ['sid' => 1],
        ]);
        $loginPage = $this->requester->request('POST', Url::LOGIN_PAGE, $this->loginRequestOptions());
        if (! str_contains($loginPage->html, 'divCaptcha')) {
            throw new LoginPageNotLoadedException('The SAT login page does not contain the captcha form.');
        }

        $captcha = $this->captchaService->resolve($loginPage);
        $options = $this->loginRequestOptions();
        $options[RequestOptions::FORM_PARAMS] = [
            'Ecom_User_ID' => $this->rfc,
            'Ecom_Password' => $this->password,
            'userCaptcha' => $captcha,
            'submit' => 'Enviar',
        ];
        $result = $this->requester->request('POST', Url::LOGIN_PAGE, $options);
        $this->checkResult($result);

        return $result;
    }

    public function logout(): void
    {
        $this->requester->request('GET', Url::LOGOUT_SATELLITE);
        $this->requester->request('GET', Url::LOGOUT_IDP);
    }

    /** @return array<string, mixed> */
    private function loginRequestOptions(): array
    {
        return [
            RequestOptions::QUERY => [
                'id' => 'ptsc-ciec',
                'sid' => 1,
                'option' => 'credential',
            ],
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Origin' => 'https://login.siat.sat.gob.mx',
                'Referer' => Url::LOGIN_PAGE,
            ],
        ];
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

    private function normalize(string $value): string
    {
        return strtolower(strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']));
    }
}
