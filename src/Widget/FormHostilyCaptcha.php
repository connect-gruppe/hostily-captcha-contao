<?php

declare(strict_types=1);

namespace Hostily\ContaoCaptcha\Widget;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\Environment;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use Hostily\ContaoCaptcha\Service\Verifier;

/**
 * Formularfeld "hostily Captcha" (Formulargenerator, auf Wunsch auch an Stelle des eingebauten Captchas).
 *
 * Das Widget-Skript legt im Formular das Feld "hostily-captcha-token" an; validate() prueft es beim Dienst.
 * Ohne Site-Key und Secret in den Systemeinstellungen zeigt das Feld nichts und sperrt nichts.
 */
class FormHostilyCaptcha extends Widget
{
    /** @var string */
    protected $strTemplate = 'form_hostily_captcha';

    /** @var string */
    protected $strPrefix = 'widget widget-hostily-captcha';

    /** @var bool Das Token gehoert nicht in die gespeicherten Formulardaten oder die Mail */
    protected $blnSubmitInput = false;

    /** @var bool */
    protected $blnForAttribute = false;

    /** @var bool Das Skript des Dienstes nur einmal je Seite einbinden */
    private static $scriptDone = false;

    public function __construct($arrAttributes = null)
    {
        parent::__construct($arrAttributes);

        // Contao 6 kennt nur noch Twig-Vorlagen, Contao 4.13 bis 5.x nehmen die .html5-Vorlage (eine gleichnamige Twig-Vorlage
        // wuerde dort anders gerendert), darum zwei Namen.
        if (version_compare(ContaoCoreBundle::getVersion(), '6.0.0-dev', '>=')) {
            $this->strTemplate = 'form_hostily_captcha_twig';
        }
    }

    /**
     * Ohne Einrichtung (Site-Key und Secret) erscheint im Frontend nichts, auch keine Beschriftung.
     */
    public function parse($arrAttributes = null)
    {
        if (!$this->asEditorView && !$this->verifier()->isConfigured()) {
            return '';
        }

        return parent::parse($arrAttributes);
    }

    public function validate(): void
    {
        $verifier = $this->verifier();
        if (!$verifier->isConfigured()) {
            return;
        }

        $token = trim((string) Input::post(Verifier::TOKEN_FIELD));
        $result = $verifier->verify($token, $this->contentFields(), (string) Environment::get('ip'));
        if ($result['status'] === 'ok') {
            return;
        }

        $this->class = 'error';
        if ($result['status'] === 'error') {
            $this->addError($GLOBALS['TL_LANG']['ERR']['hostilyCaptchaUnavailable']);
        } elseif ($token === '' || \in_array('token_fehlt', $result['reasons'], true)) {
            $this->addError($GLOBALS['TL_LANG']['ERR']['hostilyCaptchaMissing']);
        } elseif (array_intersect(['token_ungueltig', 'token_abgelaufen', 'token_bereits_verwendet'], $result['reasons']) !== []) {
            $this->addError($GLOBALS['TL_LANG']['ERR']['hostilyCaptchaInvalid']);
        } else {
            $this->addError($GLOBALS['TL_LANG']['ERR']['hostilyCaptchaRejected']);
        }
    }

    /**
     * Die Beschriftung, die Contao selbst dem eingebauten Captcha mitgibt ("Sicherheitsfrage"), passt nicht zum Kaestchen.
     */
    public function generateLabel(): string
    {
        if ($this->strLabel && $this->strLabel === ($GLOBALS['TL_LANG']['MSC']['securityQuestion'] ?? null)) {
            return '';
        }

        return $this->strLabel ? '<label>' . $this->strLabel . '</label>' : '';
    }

    /**
     * Das Element <hostily-captcha> mit dem Skript des Dienstes (leer, solange nicht eingerichtet).
     */
    public function generate(): string
    {
        $verifier = $this->verifier();
        if (!$verifier->isConfigured()) {
            return '';
        }

        $html = \sprintf(
            '<hostily-captcha data-site-key="%s" data-api="%s/api/" data-modus="%s"%s></hostily-captcha>',
            StringUtil::specialchars($verifier->siteKey()),
            StringUtil::specialchars($verifier->serviceUrl()),
            $verifier->mode(),
            $verifier->language() !== '' ? ' data-sprache="' . $verifier->language() . '"' : ''
        );

        if (!self::$scriptDone && !$this->asEditorView) {
            self::$scriptDone = true;
            $html .= \sprintf('<script src="%s/widget/hostily-captcha.js" defer></script>', StringUtil::specialchars($verifier->serviceUrl()));
        }

        return $html;
    }

    private function verifier(): Verifier
    {
        return System::getContainer()->get(Verifier::class);
    }

    /**
     * Die uebrigen Felder des Formulars fuer die Inhaltspruefung des Dienstes (ohne Kennwoerter, Tokens und technische Felder).
     *
     * @return array<string, string>
     */
    private function contentFields(): array
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();
        $fields = [];
        if ($request === null) {
            return $fields;
        }
        foreach ($request->request->all() as $name => $value) {
            if (!\is_string($name) || !\is_string($value) || trim($value) === '' || \count($fields) >= 40) {
                continue;
            }
            if (preg_match('/^(REQUEST_TOKEN|FORM_SUBMIT|MAX_FILE_SIZE)$|pass|pwd|token|captcha|^_/i', $name)) {
                continue;
            }
            $fields[substr($name, 0, 80)] = mb_substr($value, 0, 2000);
        }

        return $fields;
    }
}
