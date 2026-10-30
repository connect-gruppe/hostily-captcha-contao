<?php

declare(strict_types=1);

use Contao\Config;
use Hostily\ContaoCaptcha\Widget\FormHostilyCaptcha;

// Formularfeld fuer den Formulargenerator
$GLOBALS['TL_FFL']['hostilyCaptcha'] = FormHostilyCaptcha::class;

// Optional: das eingebaute Captcha ersetzen. Mitglieder-Registrierung, "Passwort vergessen" und Kommentare
// bekommen ihr Captcha ueber den Feldtyp "captcha", ohne Kernaenderung geht das nur auf diesem Weg.
if (Config::get('hostilyCaptchaReplace')) {
    $GLOBALS['TL_FFL']['captcha'] = FormHostilyCaptcha::class;
}
