<?php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
    ->addLegend('hostily_captcha_legend', 'chmod_legend', PaletteManipulator::POSITION_BEFORE, true)
    ->addField(
        ['hostilyCaptchaSiteKey', 'hostilyCaptchaSecret', 'hostilyCaptchaServiceUrl', 'hostilyCaptchaMode', 'hostilyCaptchaHoldTime', 'hostilyCaptchaLanguage', 'hostilyCaptchaOnError', 'hostilyCaptchaReplace'],
        'hostily_captcha_legend',
        PaletteManipulator::POSITION_APPEND
    )
    ->applyToPalette('default', 'tl_settings')
;

$GLOBALS['TL_DCA']['tl_settings']['fields']['hostilyCaptchaSiteKey'] = [
    'inputType' => 'text',
    'eval' => ['maxlength' => 64, 'tl_class' => 'w50', 'spellcheck' => false, 'decodeEntities' => true],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['hostilyCaptchaSecret'] = [
    'inputType' => 'text',
    'eval' => ['maxlength' => 128, 'tl_class' => 'w50', 'hideInput' => true, 'spellcheck' => false, 'decodeEntities' => true],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['hostilyCaptchaServiceUrl'] = [
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'rgxp' => 'url', 'tl_class' => 'long', 'placeholder' => 'https://captcha.hostily.de', 'decodeEntities' => true],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['hostilyCaptchaMode'] = [
    'inputType' => 'select',
    'default' => 'sichtbar',
    'options' => ['portal', 'sichtbar', 'unsichtbar', 'halten', 'schieben'],
    'reference' => &$GLOBALS['TL_LANG']['tl_settings']['hostilyCaptchaModes'],
    'eval' => ['tl_class' => 'w50'],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['hostilyCaptchaHoldTime'] = [
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'minval' => 800, 'maxval' => 3000, 'maxlength' => 4, 'placeholder' => '1500', 'tl_class' => 'w50'],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['hostilyCaptchaLanguage'] = [
    'inputType' => 'select',
    'options' => ['auto', 'de', 'en'],
    'reference' => &$GLOBALS['TL_LANG']['tl_settings']['hostilyCaptchaLanguages'],
    'eval' => ['tl_class' => 'w50'],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['hostilyCaptchaOnError'] = [
    'inputType' => 'select',
    'options' => ['pass', 'block'],
    'reference' => &$GLOBALS['TL_LANG']['tl_settings']['hostilyCaptchaOnErrors'],
    'eval' => ['tl_class' => 'w50'],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['hostilyCaptchaReplace'] = [
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
];
