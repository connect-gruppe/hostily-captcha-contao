<?php

declare(strict_types=1);

namespace Hostily\ContaoCaptcha;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * hostily Captcha fuer Contao: Formularfeld "hostily Captcha" (Formulargenerator) und Einstellungen in den Systemeinstellungen.
 */
class HostilyContaoCaptchaBundle extends Bundle
{
    /**
     * Das Bundle liegt im Hauptverzeichnis des Pakets (dort sucht Contao den Ordner "contao/"), nicht in src/.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
