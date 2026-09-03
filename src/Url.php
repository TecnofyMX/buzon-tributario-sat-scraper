<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper;

final class Url
{
    public const LOGIN_APP = 'https://login.siat.sat.gob.mx/nidp/app';

    public const LOGIN_PAGE = 'https://login.siat.sat.gob.mx/nidp/app/login';

    public const COMMUNICATIONS = 'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-comunicados/';

    public const LOGOUT_SATELLITE = 'https://wwwmat.sat.gob.mx/personas/cerrar-sesion';

    public const LOGOUT_IDP = 'https://login.siat.sat.gob.mx/nidp/app/logout';

    private function __construct()
    {
    }
}
