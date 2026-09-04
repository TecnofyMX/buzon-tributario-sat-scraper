<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper;

final class Url
{
    public const LOGIN_APP = 'https://login.siat.sat.gob.mx/nidp/app';

    public const LOGIN_PAGE = 'https://login.siat.sat.gob.mx/nidp/app/login';

    public const COMMUNICATIONS = 'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-comunicados/';

    public const COMMUNICATIONS_FRAME = 'https://aplicacionesc.mat.sat.gob.mx/WebComunicados/Comunicados.aspx';

    public const LOGOUT_SATELLITE = 'https://wwwmat.sat.gob.mx/cs/Satellite'
        . '?childpagename=Common/Logic/COMMON_Logout'
        . '&packedargs=locale=1462228413195&pagename=TySWrapper';

    public const CLOSE_SESSION = 'https://wwwmat.sat.gob.mx/app/seg/cerrarSesion';

    public const LOGOUT_IDP = 'https://login.siat.sat.gob.mx/nidp/app/plogout';

    private function __construct()
    {
    }
}
