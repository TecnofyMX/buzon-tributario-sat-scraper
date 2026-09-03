<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper;

final class Url
{
    public const LOGIN_PAGE = 'https://wwwmat.sat.gob.mx/personas/iniciar-sesion';

    public const BUZON_HOME = 'https://wwwmat.sat.gob.mx/buzon';

    public const COMMUNICATIONS = 'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-comunicados/';

    public const LOGOUT_SATELLITE = 'https://wwwmat.sat.gob.mx/personas/cerrar-sesion';

    public const LOGOUT_IDP = 'https://login.siat.sat.gob.mx/nidp/app/logout';

    private function __construct()
    {
    }
}
