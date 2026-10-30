<?php

declare(strict_types=1);

namespace Hostily\ContaoCaptcha\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Hostily\ContaoCaptcha\HostilyContaoCaptchaBundle;

/**
 * Meldet das Bundle in Contao an (verwaltet auch ohne Contao Manager die "managed edition").
 */
class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(HostilyContaoCaptchaBundle::class)->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}
