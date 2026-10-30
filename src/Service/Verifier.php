<?php

declare(strict_types=1);

namespace Hostily\ContaoCaptcha\Service;

use Contao\Config;
use Contao\CoreBundle\Monolog\ContaoContext;
use Psr\Log\LoggerInterface;

/**
 * Prueft ein Token beim Dienst (POST <Dienst>/api/v1/pruefen, Protokoll: dienst/docs/protokoll.md).
 *
 * Ergebnis: ['status' => 'ok'|'rejected'|'error', 'reasons' => string[]]. 'error' gibt es nur bei
 * "Bei Ausfall: blockieren"; bei "durchlassen" (Vorgabe) ist ein Ausfall oder ein Konfigurationsfehler 'ok'
 * mit dem Grund 'service' (und einem Eintrag im Contao-Systemlog).
 *
 * Einstellungen aus den Systemeinstellungen (localconfig): hostilyCaptchaSiteKey, ...Secret, ...ServiceUrl,
 * ...Mode, ...Language, ...OnError.
 */
class Verifier
{
    public const VERSION = '0.1.0';
    public const TOKEN_FIELD = 'hostily-captcha-token';
    public const DEFAULT_URL = 'https://captcha.hostily.de';

    private const CONFIG_ERRORS = ['invalid-input-secret', 'missing-input-secret', 'bad-request'];

    /** @var LoggerInterface|null */
    private $logger;

    /** @var array<string, array{status: string, reasons: array<int, string>}> Ergebnisse dieser Anfrage je Token */
    private $verified = [];

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function isConfigured(): bool
    {
        return $this->setting('SiteKey') !== '' && $this->setting('Secret') !== '' && $this->serviceUrl() !== '';
    }

    /**
     * Dienst-Adresse ohne Schraegstrich am Ende; nur http(s).
     */
    public function serviceUrl(): string
    {
        $url = rtrim($this->setting('ServiceUrl'), '/');
        if ($url === '') {
            $url = self::DEFAULT_URL;
        }

        return preg_match('#^https?://[^\s/]+#i', $url) === 1 ? $url : '';
    }

    public function siteKey(): string
    {
        return $this->setting('SiteKey');
    }

    public function mode(): string
    {
        return $this->setting('Mode') === 'unsichtbar' ? 'unsichtbar' : 'sichtbar';
    }

    /**
     * Sprache des Widgets: "de", "en" oder leer (dann entscheidet das Widget selbst).
     * "automatisch" nimmt die Sprache der Seite.
     */
    public function language(): string
    {
        $lang = $this->setting('Language');
        if ($lang === '' || $lang === 'auto') {
            $lang = strtolower(substr(str_replace('_', '-', (string) ($GLOBALS['TL_LANGUAGE'] ?? '')), 0, 2));
        }

        return \in_array($lang, ['de', 'en'], true) ? $lang : '';
    }

    /**
     * @param array<string, string> $fields Inhaltsfelder (leer = keine Inhaltspruefung)
     *
     * @return array{status: string, reasons: array<int, string>}
     */
    public function verify(string $token, array $fields, string $ip): array
    {
        // Ein leeres Token wird trotzdem gesendet: ist der Dienst erreichbar, ist die Einsendung abgelehnt; ist er es
        // nicht, gilt "Bei Ausfall" (sonst sperrt ein nicht ladendes Widget alle Besucher aus).
        if ($token !== '' && isset($this->verified[$token])) {
            return $this->verified[$token];
        }
        $result = $this->ask($token, $fields, $ip);
        if ($token !== '') {
            // Ein Token gilt nur einmal, derselbe Aufruf kann aber mehrfach pruefen.
            if (\count($this->verified) >= 50) {
                $this->verified = [];
            }
            $this->verified[$token] = $result;
        }

        return $result;
    }

    /**
     * @param array<string, string> $fields
     *
     * @return array{status: string, reasons: array<int, string>}
     */
    private function ask(string $token, array $fields, string $ip): array
    {
        $body = [
            'secret' => $this->setting('Secret'),
            'response' => $token,
            'remoteip' => $ip,
        ];
        if ($fields !== []) {
            $body['formular'] = $fields;
        }

        try {
            [$code, $raw] = $this->post($this->serviceUrl() . '/api/v1/pruefen', (string) json_encode($body));
            $data = json_decode($raw, true);
        } catch (\Throwable $e) {
            return $this->failure('service unreachable: ' . $e->getMessage());
        }

        if ($code !== 200 || !\is_array($data) || !isset($data['success'])) {
            return $this->failure('unexpected answer from service (HTTP ' . $code . ')');
        }
        $codes = [];
        if (isset($data['error-codes']) && \is_array($data['error-codes'])) {
            foreach ($data['error-codes'] as $c) {
                if (\is_string($c)) {
                    $codes[] = (string) preg_replace('/[^a-z0-9_-]/i', '', $c);
                }
            }
        }
        if (array_intersect($codes, self::CONFIG_ERRORS) !== []) {
            return $this->failure('service rejected the configuration: ' . implode(',', $codes));
        }
        if ($data['success'] === true) {
            return ['status' => 'ok', 'reasons' => []];
        }
        $reasons = [];
        if (isset($data['gruende']) && \is_array($data['gruende'])) {
            foreach ($data['gruende'] as $r) {
                if (\is_string($r)) {
                    $reasons[] = substr((string) preg_replace('/[^a-z0-9_:.-]/i', '', $r), 0, 60);
                }
            }
        }

        return ['status' => 'rejected', 'reasons' => $reasons];
    }

    /**
     * @return array{0: int, 1: string} HTTP-Status und Antworttext; wirft bei Netzwerkfehlern
     */
    private function post(string $url, string $json): array
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'User-Agent: hostily-captcha-contao/' . self::VERSION];

        if (\function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 8,
            ]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                throw new \RuntimeException($err !== '' ? $err : 'curl failed');
            }

            return [$code, (string) $raw];
        }

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $json,
            'timeout' => 8,
            'follow_location' => 0,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false || !isset($http_response_header[0])) {
            throw new \RuntimeException('request failed');
        }
        preg_match('#\s(\d{3})\s#', $http_response_header[0] . ' ', $m);

        return [(int) ($m[1] ?? 0), $raw];
    }

    /**
     * Dienst- oder Konfigurationsproblem: durchlassen (Vorgabe) oder blockieren, immer protokollieren.
     *
     * @return array{status: string, reasons: array<int, string>}
     */
    private function failure(string $why): array
    {
        $this->log('hostily Captcha: ' . substr($why, 0, 300));
        if ($this->setting('OnError') === 'block') {
            return ['status' => 'error', 'reasons' => ['service']];
        }

        return ['status' => 'ok', 'reasons' => ['service']];
    }

    private function log(string $message): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]);

            return;
        }
        error_log($message);
    }

    private function setting(string $name): string
    {
        $value = Config::get('hostilyCaptcha' . $name);

        return \is_string($value) ? trim($value) : '';
    }
}
