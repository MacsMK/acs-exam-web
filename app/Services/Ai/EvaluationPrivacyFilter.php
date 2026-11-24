<?php

declare(strict_types=1);

/**
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * Proyecto: Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo: EvaluationPrivacyFilter.php
 * Ubicación: app/Services/Ai/EvaluationPrivacyFilter.php
 * Requerimiento: RNF-PRI-001, WEB-RNF-003
 * Autor: Codex (implementación local)
 * Versión: 1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción: Minimiza identificadores de la copia que se envía al evaluador externo.
 */

namespace App\Services\Ai;

use RuntimeException;

final class EvaluationPrivacyFilter
{
    private const PERSONAL_PATTERNS = [
        '~(?<![\p{L}\p{N}._%+\-])[\p{L}\p{N}.!#$%&\'*+/=?^_\x60{|}\-]+@[\p{L}\p{N}\-]+(?:\.[\p{L}\p{N}\-]+)+~iu'
            => 'persona@example.invalid',
        '~(?<![\p{L}\p{N}])[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z\d]\d(?![\p{L}\p{N}])~iu'
            => 'XXXX000000HXXXXX00',
        '~(?<![\p{L}\p{N}])[A-ZÑ&]{3,4}\d{6}[A-Z\d]{3}(?![\p{L}\p{N}])~iu'
            => 'XXXX000000XXX',
        '~(\b(?:matr[ií]cula|n[uú]mero de estudiante)[ \t]*[:=][ \t]*)[\p{L}\d_-]{3,}(?=\W|\z)~iu'
            => '${1}MATRICULA000',
        '~(\b(?:tel[eé]fono|celular|m[oó]vil|whatsapp|tel\.)(?:[ \t]*[:=])?[ \t]*)[+]?\d(?:[ \t().\-]*\d){9,14}(?!\d)~iu'
            => '${1}0000000000',
        '~(?<![\p{L}\p{N}])(?:\+52[ \t.-]*)?(?:\(\d{2,3}\)|\d{2,3})[ \t.-]+\d{3,4}[ \t.-]+\d{4}(?!\d)~u'
            => '0000000000',
        '~(\b(?:[Mm]e llamo|[Mm]i nombre es|[Nn]ombre(?: completo)?[ \t]*:)[ \t]+)\p{Lu}[\p{L}\p{M}\'’\-]*(?:[ \t]+\p{Lu}[\p{L}\p{M}\'’\-]*){0,4}~u'
            => '${1}Persona',
    ];

    /**
     * Sustituye identificadores reconocibles conservando el resto del texto o código.
     *
     * @param string $text Texto o código pedagógico.
     * @param array<int|string, string> $accountIdentifiers Identificadores de participantes conocidos.
     * @return string Copia con identificadores sustituidos.
     * @throws RuntimeException Si la minimización falla y el envío debe omitirse.
     */
    public static function minimize(string $text, array $accountIdentifiers = []): string
    {
        foreach ($accountIdentifiers as $identifier => $replacement) {
            if ((string) $identifier === '') {
                continue;
            }

            $pattern = '~(?<![\p{L}\p{N}])'.preg_quote((string) $identifier, '~').'(?![\p{L}\p{N}])~iu';
            $text = self::replace($pattern, $replacement, $text);
        }

        foreach (self::PERSONAL_PATTERNS as $pattern => $replacement) {
            $text = self::replace($pattern, $replacement, $text);
        }

        return $text;
    }

    /**
     * Reemplaza coincidencias y cancela la evaluación externa al fallar.
     *
     * @param string $pattern Patrón de identificador.
     * @param string $replacement Sustituto no identificable.
     * @param string $text Texto en proceso de minimización.
     * @return string Texto seguro para aplicar el siguiente patrón.
     */
    private static function replace(string $pattern, string $replacement, string $text): string
    {
        $result = preg_replace($pattern, $replacement, $text);
        if ($result === null) {
            throw new RuntimeException('No se pudo minimizar el texto antes de enviarlo al evaluador.');
        }

        return $result;
    }
}
