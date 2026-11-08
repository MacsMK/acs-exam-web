<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GradeSheetExportService.php
 * Ubicación:      app/Services/GradeSheetExportService.php
 * Requerimiento:  RF-REP-014, RF-REP-015, RF-CAL-021, RW-28, RW-38 (MPW-V2 §6.11)
 * Autor:          Equipo de Ingeniería (A-Code Solutions)
 * Versión:        1.0.0
 * Descripción:    Servicio especializado en la generación estandarizada de hojas
 *                 de cálculo (.xlsx) con paleta institucional A-Code para
 *                 sábanas de calificaciones de periodos escolares.
 * ============================================================================
 */

namespace App\Services;

use App\Models\EvaluationPeriod;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GradeSheetExportService
{
    // Paleta de Colores Institucionales A-Code Solutions (§8.1.1, MC-V2)
    public const COLOR_PRIMARY = '142B4A';   // Azul Marino Oficial
    public const COLOR_TECH    = '2563A6';   // Azul Tecnológico
    public const COLOR_ACCENT  = '16A6A1';   // Verde Éxito / Aprobado
    public const COLOR_WARNING = 'F4B942';   // Ámbar Advertencia
    public const COLOR_DANGER  = 'DC2626';   // Rojo Peligro / No Aprobado
    public const COLOR_SURFACE = 'E9F0F5';   // Gris Superficie Metadatos
    public const COLOR_ZEBRA   = 'F8FAFC';   // Fila Alternada Cebra
    public const COLOR_WHITE   = 'FFFFFF';   // Blanco
    public const COLOR_BORDER  = 'CBD5E1';   // Bordes de Retícula

    public function __construct(
        private readonly GradeCalculatorService $gradeCalculator,
    ) {
    }

    /**
     * Genera y descarga en streaming la sábana de notas en formato Excel (.xlsx).
     */
    public function exportPeriodToXlsx(Group $group, EvaluationPeriod $period, User $teacher): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($period->name, 0, 31));

        $weights = $this->parseFormulaWeights($period->formula);

        // 1. Bloque Institucional y Metadatos Normativos (RF-REP-015, MC-V2)
        $this->applyInstitutionHeader($sheet, $group, $period, $teacher);

        // 2. Fila de Encabezados de Columnas
        $startRow = $this->applyTableHeaders($sheet, $weights);

        // 3. Volcado de Estudiantes y Calificaciones (sin N+1)
        $lastRow = $this->populateStudentRows($sheet, $group, $period, $startRow);

        // 4. Bloque de Estadísticas Globales del Grupo (RF-REP-011)
        $this->applySummaryStatistics($sheet, $startRow, $lastRow);

        // 5. Ajuste automático de anchos
        $this->autoFitColumns($sheet);

        $sanitizedGroup = preg_replace('/[^A-Za-z0-9_-]/', '_', $group->name);
        $sanitizedPeriod = preg_replace('/[^A-Za-z0-9_-]/', '_', $period->name);
        $fileName = sprintf('Sabana_%s_%s_%s.xlsx', $sanitizedGroup, $sanitizedPeriod, now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Dibuja el membrete corporativo y los metadatos institucionales requeridos por RF-REP-015.
     */
    private function applyInstitutionHeader($sheet, Group $group, EvaluationPeriod $period, User $teacher): void
    {
        // Fila 1: Título Institucional
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'A-CODE SOLUTIONS — SÁBANA OFICIAL DE EVALUACIÓN ACADÉMICA');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_PRIMARY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Fila 2: Docente Titular
        $sheet->mergeCells('A2:I2');
        $sheet->setCellValue('A2', sprintf('Docente Titular: %s | Correo Institucional: %s', $teacher->full_name, $teacher->email));
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TECH]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // Metadatos Académicos Detallados (Filas 3 a 7)
        $metaData = [
            3 => sprintf('Asignatura: %s | Grupo: %s | Folio Oficial: %s', $group->subject ?? 'Tronco Común', $group->name, $group->access_folio),
            4 => sprintf('Periodo Evaluado: %s | Vigencia: %s al %s | Fórmula: %s', $period->name, $period->starts_at->format('d/m/Y'), $period->ends_at->format('d/m/Y'), $period->formula),
            5 => 'Escala Oficial de Evaluación: 0–100 sin decimales (Aprobatoria: 60.00) (RF-REP-015, RF-CAL-028)',
            6 => sprintf('Fecha y Hora de Emisión: %s (Hora Oficial del Servidor) (RF-REP-015)', now()->format('Y-m-d H:i:s T')),
            7 => 'Regla de Integridad: Los valores «Pendiente» indican ausencia de evaluación y no se computan como cero (RF-CAL-021).',
        ];

        foreach ($metaData as $row => $text) {
            $sheet->mergeCells("A{$row}:I{$row}");
            $sheet->setCellValue("A{$row}", $text);
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['size' => 9, 'italic' => ($row >= 5), 'color' => ['rgb' => self::COLOR_PRIMARY]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_SURFACE]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(18);
        }
    }

    /**
     * Dibuja los encabezados de la tabla con los pesos porcentuales de los rubros.
     *
     * @param array<string, float> $weights
     */
    private function applyTableHeaders($sheet, array $weights): int
    {
        $headers = [
            'A9' => 'No.',
            'B9' => 'ID Matrícula',
            'C9' => 'Estudiante (Apellidos, Nombres)',
            'D9' => sprintf('Exámenes (%g%%)', $weights['exams']),
            'E9' => sprintf('Tareas (%g%%)', $weights['homework']),
            'F9' => sprintf('Proyectos (%g%%)', $weights['project']),
            'G9' => sprintf('Otros (%g%%)', $weights['other']),
            'H9' => 'Calificación Final',
            'I9' => 'Estatus Normativo',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $sheet->getStyle('A9:I9')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_PRIMARY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDER]]],
        ]);
        $sheet->getRowDimension(9)->setRowHeight(26);

        return 10;
    }

    /**
     * Vuelca las filas de alumnos con sus calificaciones y formato condicional (sin N+1).
     */
    private function populateStudentRows($sheet, Group $group, EvaluationPeriod $period, int $startRow): int
    {
        $students = $group->students()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $savedGrades = $period->studentGrades->keyBy('student_id');

        // Pre-carga agregada de promedios de exámenes reales (sin N+1)
        $examIds = $group->exams()->pluck('acs_exams.id');
        $examAverages = StudentAttempt::query()
            ->whereIn('exam_id', $examIds)
            ->where('group_id', $group->id)
            ->whereNotNull('final_score')
            ->groupBy('student_id')
            ->selectRaw('student_id, AVG(final_score) as avg_score')
            ->pluck('avg_score', 'student_id')
            ->map(fn ($score) => round((float) $score, 2));

        $currentRow = $startRow;
        $index = 1;

        foreach ($students as $student) {
            $grade = $savedGrades->get($student->id);
            $suggestedExam = $examAverages->get($student->id);
            $exams = $grade?->exams_component ?? $suggestedExam;
            $hw = $grade?->homework_component;
            $proj = $grade?->project_component;
            $other = $grade?->other_component;
            $final = $grade?->final_grade;

            $sheet->setCellValue("A{$currentRow}", $index++);
            $sheet->setCellValueExplicit("B{$currentRow}", (string) $student->id, DataType::TYPE_STRING);
            $sheet->setCellValue("C{$currentRow}", sprintf('%s, %s', $student->last_name, $student->first_name));

            // Componente Exámenes
            $this->setComponentCellValue($sheet, "D{$currentRow}", $exams !== null ? (float) $exams : null);

            // Componente Tareas
            $this->setComponentCellValue($sheet, "E{$currentRow}", $hw !== null ? (float) $hw : null);

            // Componente Proyectos
            $this->setComponentCellValue($sheet, "F{$currentRow}", $proj !== null ? (float) $proj : null);

            // Componente Otros
            $this->setComponentCellValue($sheet, "G{$currentRow}", $other !== null ? (float) $other : null);

            // Calificación Final y Estatus
            if ($final !== null) {
                $finalNumeric = (float) $final;
                $sheet->setCellValue("H{$currentRow}", $finalNumeric);
                $sheet->getStyle("H{$currentRow}")->getNumberFormat()->setFormatCode('0.00');

                if ($finalNumeric >= 60.0) {
                    $sheet->setCellValue("I{$currentRow}", 'Aprobado');
                    $sheet->getStyle("I{$currentRow}")->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_ACCENT);
                } else {
                    $sheet->setCellValue("I{$currentRow}", 'No Aprobado');
                    $sheet->getStyle("I{$currentRow}")->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_DANGER);
                }
            } else {
                $sheet->setCellValueExplicit("H{$currentRow}", 'Pendiente', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("I{$currentRow}", 'Pendiente', DataType::TYPE_STRING);
                $sheet->getStyle("H{$currentRow}:I{$currentRow}")->getFont()->getColor()->setRGB(self::COLOR_WARNING);
            }

            // Fondo Cebra
            $bgColor = ($currentRow % 2 === 0) ? self::COLOR_ZEBRA : self::COLOR_WHITE;
            $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDER]]],
            ]);

            $sheet->getStyle("A{$currentRow}:B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$currentRow}:H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("I{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $currentRow++;
        }

        return max($startRow, $currentRow - 1);
    }

    /**
     * Inserta un valor numérico formateado o la leyenda literal «Pendiente» sin coaccionar a 0 (RF-CAL-021).
     */
    private function setComponentCellValue($sheet, string $cellCoordinate, float|int|string|null $value): void
    {
        if ($value !== null && is_numeric($value)) {
            $sheet->setCellValue($cellCoordinate, (float) $value);
            $sheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode('0.00');
        } else {
            $sheet->setCellValueExplicit($cellCoordinate, 'Pendiente', DataType::TYPE_STRING);
            $sheet->getStyle($cellCoordinate)->getFont()->setItalic(true)->getColor()->setRGB('94A3B8');
        }
    }

    /**
     * Aplica el bloque de resumen estadístico grupal.
     */
    private function applySummaryStatistics($sheet, int $startRow, int $lastRow): void
    {
        $summaryRow = $lastRow + 2;

        $sheet->mergeCells("A{$summaryRow}:G{$summaryRow}");
        $sheet->setCellValue("A{$summaryRow}", 'PROMEDIO GLOBAL DEL GRUPO (CALIFICACIONES DEFINITIVAS, RF-REP-011):');
        $sheet->setCellValue("H{$summaryRow}", sprintf('=AVERAGE(H%d:H%d)', $startRow, $lastRow));
        $sheet->getStyle("H{$summaryRow}")->getNumberFormat()->setFormatCode('0.00');

        $sheet->getStyle("A{$summaryRow}:I{$summaryRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::COLOR_PRIMARY]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_SURFACE]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::COLOR_TECH]]],
        ]);
        $sheet->getStyle("A{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("H{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($summaryRow)->setRowHeight(22);
    }

    /**
     * Ajuste automático de anchos de columnas.
     */
    private function autoFitColumns($sheet): void
    {
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Extrae los pesos porcentuales legibles de la fórmula.
     *
     * @return array<string, float>
     */
    private function parseFormulaWeights(string $formula): array
    {
        $weights = [
            'exams' => 0.0,
            'homework' => 0.0,
            'project' => 0.0,
            'other' => 0.0,
        ];

        foreach (explode('+', str_replace(' ', '', $formula)) as $term) {
            if ($term === '') {
                continue;
            }

            [$component, $weight] = array_pad(explode('*', $term), 2, '0');
            if (isset($weights[$component])) {
                $weights[$component] = round(((float) $weight) * 100, 1);
            }
        }

        return $weights;
    }
}
