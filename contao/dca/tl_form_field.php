<?php

declare(strict_types=1);

// Feldtyp "hostily Captcha": nur Typ und Beschriftung (das Feld hat einen festen Namen, das Token wird nicht gespeichert).
$GLOBALS['TL_DCA']['tl_form_field']['palettes']['hostilyCaptcha'] =
    '{type_legend},type,label;{expert_legend:hide},class;{template_legend:hide},customTpl;{invisible_legend:hide},invisible';
