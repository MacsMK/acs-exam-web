<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        OrganicNames.php
 * Ubicación:      database/seeders/Support/OrganicNames.php
 * Requerimiento:  N/A (herramienta de datos de demostración; sin RF asociado)
 * Autor:          Calvo Sandoval Miguel Alexander (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 03/11/2026
 * Descripción:    Catálogo determinista de nombres y apellidos en español
 *                 (con acentos y nombres compuestos) para sembrar datos
 *                 "orgánicos" en demostraciones y pruebas. No usa azar: la
 *                 misma posición siempre devuelve la misma persona, lo que
 *                 vuelve idempotente al OrganicDataSeeder.
 * ============================================================================
 */

namespace Database\Seeders\Support;

use Illuminate\Support\Str;

class OrganicNames
{
    /**
     * Nombres de pila de uso común (incluye acentos y nombres compuestos).
     *
     * @var list<string>
     */
    public const FIRST_NAMES = [
        'Ana Sofía', 'Luis Ángel', 'María Fernanda', 'Carlos', 'Valeria',
        'José Pablo', 'Camila', 'Diego', 'Daniela', 'Andrés',
        'Paola', 'Javier', 'Ximena', 'Emiliano', 'Regina',
        'Mateo', 'Renata', 'Sebastián', 'Natalia', 'Iván',
        'Itzel', 'Rodrigo', 'Abril', 'Héctor', 'Mónica',
        'Óscar', 'Jimena', 'Fernando', 'Alejandra', 'Gael',
    ];

    /**
     * Apellidos de uso común en México (incluye acentos).
     *
     * @var list<string>
     */
    public const LAST_NAMES = [
        'García', 'Hernández', 'Martínez', 'López', 'González',
        'Pérez', 'Rodríguez', 'Sánchez', 'Ramírez', 'Cruz',
        'Flores', 'Gómez', 'Morales', 'Vázquez', 'Jiménez',
        'Reyes', 'Díaz', 'Torres', 'Gutiérrez', 'Ruiz',
        'Mendoza', 'Aguilar', 'Ortiz', 'Castillo', 'Moreno',
        'Romero', 'Álvarez', 'Chávez', 'Rivera', 'Juárez',
        'Domínguez', 'Guerrero', 'Estrada', 'Peña', 'Ibarra',
        'Salazar', 'Cortés', 'Núñez', 'Maldonado', 'Soto',
    ];

    /**
     * Devuelve la persona de la posición indicada: nombre de pila y dos
     * apellidos (que nunca coinciden entre sí).
     *
     * @return array{first_name: string, last_name: string}
     */
    public static function person(int $index): array
    {
        $firstCount = count(self::FIRST_NAMES);
        $lastCount = count(self::LAST_NAMES);

        $firstName = self::FIRST_NAMES[$index % $firstCount];
        $paternal = self::LAST_NAMES[($index * 7) % $lastCount];
        $maternalIndex = ($index * 11 + 3) % $lastCount;

        if (self::LAST_NAMES[$maternalIndex] === $paternal) {
            $maternalIndex = ($maternalIndex + 1) % $lastCount;
        }

        return [
            'first_name' => $firstName,
            'last_name' => $paternal.' '.self::LAST_NAMES[$maternalIndex],
        ];
    }

    /**
     * Convierte un nombre con acentos en un fragmento válido para correo
     * ("Ana Sofía" => "ana.sofia").
     */
    public static function emailSlug(string $text): string
    {
        return Str::slug(Str::ascii($text), '.');
    }
}
