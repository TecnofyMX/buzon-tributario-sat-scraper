# tecnofy/buzon-tributario-sat-scraper

Librería PHP para recolectar los metadatos de **Mis notificaciones** y los mensajes no leídos de
**Mis comunicados** del Buzón Tributario del SAT México.

> La documentación está en español porque es el idioma natural de las personas usuarias de esta herramienta.

## Alcance y seguridad

La versión `0.x` inicia sesión con RFC, Contraseña y captcha, consulta las carpetas **Pendientes** y **Notificados**, recorre su paginación y devuelve únicamente:

- folio del acto administrativo;
- autoridad emisora;
- acto administrativo;
- fecha de aviso;
- estado (`pending` o `notified`).

También consulta **Mis comunicados** sin expandir sus elementos y devuelve la fecha, hora y asunto de los mensajes
que permanecen bajo **Mensajes no leídos**.

La librería **no abre actos pendientes**, no envía e.firma, no genera acuses, no descarga documentos y no sigue enlaces de Documento, Ver, Aceptar o Acuse. Abrir un acto pendiente puede tener efectos jurídicos; esa operación está deliberadamente fuera del alcance.

## Instalación

```shell
composer require tecnofy/buzon-tributario-sat-scraper
```

Requiere PHP 8.4 o posterior, cURL y un resolvedor compatible con [`tecnofy/image-captcha-resolver`](https://github.com/tecnofy/image-captcha-resolver).

## Uso básico

```php
<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use GuzzleHttp\RequestOptions;
use Tecnofy\BuzonTributarioSatScraper\HttpClientFactory;
use Tecnofy\BuzonTributarioSatScraper\Scraper;
use tecnofy\ImageCaptchaResolver\Resolvers\ConsoleResolver;

$client = HttpClientFactory::create([
    // Algunos entornos antiguos del SAT podrían necesitar esta opción:
    'curl' => [CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1'],
    // Conserve la validación TLS activa siempre que sea posible.
    RequestOptions::VERIFY => true,
]);

$scraper = Scraper::create(
    $client,
    new ConsoleResolver(),
    'TU_RFC',
    'TU_CONTRASEÑA',
);

$notifications = $scraper->notifications();

foreach ($notifications as $notification) {
    printf(
        "%s | %s | %s | %s\n",
        $notification->status->value,
        $notification->folio,
        $notification->noticeDate,
        $notification->administrativeAct,
    );
}
```

`NotificationCollection::pending()` y `NotificationCollection::notified()` crean colecciones filtradas sin modificar la original.

Los comunicados no leídos se consultan en una sesión independiente:

```php
$communications = $scraper->unreadCommunications();

foreach ($communications as $communication) {
    printf("%s | %s\n", $communication->receivedAt, $communication->subject);
}
```

## Errores

Las fallas del SAT se expresan mediante excepciones específicas bajo `Tecnofy\BuzonTributarioSatScraper\Exceptions`: red, captcha, credenciales, SSO, página inesperada, paginación y estructura no reconocida. Los mensajes no incluyen RFC, contraseña, captcha, cookies ni HTML autenticado.

## Desarrollo

```shell
composer install
composer dev:test
```

El proyecto utiliza PHPUnit, PHPStan, PHP_CodeSniffer, PHP-CS-Fixer y Composer Normalize. También se incluye un manifiesto Phive para instalar las herramientas como PHAR cuando ése sea el flujo preferido.

Las pruebas de integración reales son manuales y requieren que la persona propietaria de la cuenta capture sus credenciales. Nunca deben incorporarse credenciales, cookies ni HTML sin sanitizar al repositorio.

## Compatibilidad

La rama inicial se prueba con PHP 8.2, 8.3, 8.4 y 8.5 y sigue Versionado Semántico.

## Licencia

MIT. Consulte [LICENSE](LICENSE).
