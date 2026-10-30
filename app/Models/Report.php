<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Report
{
    public function __construct(private PDO $db)
    {
    }

    public function data(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $statusRange = $this->rangeCondition('i', 'status', $dateFrom, $dateTo);
        $priorityRange = $this->rangeCondition('i', 'priority', $dateFrom, $dateTo);
        $categoryRange = $this->rangeCondition('i', 'category', $dateFrom, $dateTo);
        $resolutionRange = $this->rangeCondition('i', 'resolution', $dateFrom, $dateTo);
        $recordRange = $this->rangeCondition('i', 'records', $dateFrom, $dateTo);

        $statuses = $this->db->prepare(
            'SELECT e.nombre AS label, e.color, COUNT(i.id_incidencia) AS total
             FROM estado e LEFT JOIN incidencia i ON i.id_estado = e.id_estado ' . $statusRange['sql'] . '
             GROUP BY e.id_estado ORDER BY e.id_estado'
        );
        $statuses->execute($statusRange['parameters']);

        $priorities = $this->db->prepare(
            'SELECT p.nombre AS label, p.color, COUNT(i.id_incidencia) AS total
             FROM prioridad p LEFT JOIN incidencia i ON i.id_prioridad = p.id_prioridad ' . $priorityRange['sql'] . '
             GROUP BY p.id_prioridad ORDER BY p.nivel'
        );
        $priorities->execute($priorityRange['parameters']);

        $categories = $this->db->prepare(
            'SELECT c.nombre AS label, COUNT(i.id_incidencia) AS total
             FROM categoria c LEFT JOIN incidencia i ON i.id_categoria = c.id_categoria ' . $categoryRange['sql'] . '
             GROUP BY c.id_categoria ORDER BY total DESC, c.nombre LIMIT 5'
        );
        $categories->execute($categoryRange['parameters']);

        $resolution = $this->db->prepare(
            'SELECT DATE_FORMAT(fecha_resolucion, "%Y-%m") AS month,
                    AVG(TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_resolucion)) AS hours
             FROM incidencia i WHERE fecha_resolucion IS NOT NULL ' . $resolutionRange['sql'] . '
             GROUP BY month ORDER BY month'
        );
        $resolution->execute($resolutionRange['parameters']);

        $records = $this->db->prepare(
            'SELECT i.id_incidencia, i.codigo, i.titulo, c.nombre AS category, p.nombre AS priority,
                    s.nombre AS status,
                    CONCAT(reporter.nombre, " ", reporter.apellido) AS reporter,
                    COALESCE(CONCAT(technician.nombre, " ", technician.apellido), "") AS technician,
                    i.fecha_creacion, COALESCE(i.fecha_asignacion, "") AS fecha_asignacion,
                    COALESCE(i.fecha_resolucion, "") AS fecha_resolucion,
                    COALESCE(i.fecha_cierre, "") AS fecha_cierre,
                    COALESCE(i.solucion, "") AS solucion
             FROM incidencia i
             JOIN categoria c ON c.id_categoria = i.id_categoria
             JOIN prioridad p ON p.id_prioridad = i.id_prioridad
             JOIN estado s ON s.id_estado = i.id_estado
             JOIN usuario reporter ON reporter.id_usuario = i.id_reportante
             LEFT JOIN usuario technician ON technician.id_usuario = i.id_tecnico
             WHERE 1 = 1 ' . $recordRange['sql'] . '
             ORDER BY i.fecha_creacion DESC, i.id_incidencia DESC'
        );
        $records->execute($recordRange['parameters']);

        return [
            'statuses' => $statuses->fetchAll(),
            'priorities' => $priorities->fetchAll(),
            'categories' => $categories->fetchAll(),
            'resolution' => $resolution->fetchAll(),
            'records' => $records->fetchAll(),
        ];
    }

    public function createExcelFile(array $data): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('La extensión PHP zip es necesaria para exportar a Excel.');
        }

        $sheets = [
            ['Estados', ['Estado', 'Incidencias'], array_map(
                static fn(array $row): array => [$row['label'], (int) $row['total']],
                $data['statuses']
            )],
            ['Prioridades', ['Prioridad', 'Incidencias'], array_map(
                static fn(array $row): array => [$row['label'], (int) $row['total']],
                $data['priorities']
            )],
            ['Categorías', ['Categoría', 'Incidencias'], array_map(
                static fn(array $row): array => [$row['label'], (int) $row['total']],
                $data['categories']
            )],
            ['Resolución', ['Mes', 'Horas promedio'], array_map(
                static fn(array $row): array => [$row['month'], round((float) $row['hours'], 1)],
                $data['resolution']
            )],
            ['Registros', [
                'ID', 'Código', 'Título', 'Categoría', 'Prioridad', 'Estado', 'Reportante',
                'Técnico', 'Fecha creación', 'Fecha asignación', 'Fecha resolución',
                'Fecha cierre', 'Solución',
            ], array_map(
                static function (array $row): array {
                    $row['id_incidencia'] = (int) $row['id_incidencia'];
                    return array_values($row);
                },
                $data['records']
            )],
        ];
        $path = tempnam(sys_get_temp_dir(), 'incidencias-');
        if ($path === false) {
            throw new \RuntimeException('No se pudo preparar el archivo de Excel.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            unlink($path);
            throw new \RuntimeException('No se pudo generar el archivo de Excel.');
        }

        $overrides = '';
        $relationships = '';
        $sheetNames = [];
        foreach ($sheets as $index => $sheet) {
            $number = $index + 1;
            $sheetNames[] = '<sheet name="' . $this->xml($sheet[0]) . '" sheetId="' . $number . '" r:id="rId' . $number . '"/>';
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $number . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $relationships .= '<Relationship Id="rId' . $number . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $number . '.xml"/>';
            $rows = array_merge([$sheet[1]], $sheet[2]);
            $zip->addFromString('xl/worksheets/sheet' . $number . '.xml', $this->worksheetXml($rows));
        }

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $overrides . '</Types>');
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
            . implode('', $sheetNames) . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relationships . '</Relationships>');
        if (!$zip->close()) {
            unlink($path);
            throw new \RuntimeException('No se pudo finalizar el archivo de Excel.');
        }
        return $path;
    }

    private function worksheetXml(array $rows): string
    {
        $xmlRows = '';
        foreach ($rows as $rowIndex => $row) {
            $xmlCells = '';
            foreach ($row as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1) . ($rowIndex + 1);
                if ($rowIndex > 0 && (is_int($value) || is_float($value))) {
                    $xmlCells .= '<c r="' . $reference . '" t="n"><v>' . (float) $value . '</v></c>';
                } else {
                    $xmlCells .= '<c r="' . $reference . '" t="inlineStr"><is><t>'
                        . $this->xml((string) $value) . '</t></is></c>';
                }
            }
            $xmlRows .= '<row r="' . ($rowIndex + 1) . '">' . $xmlCells . '</row>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . $xmlRows . '</sheetData></worksheet>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function rangeCondition(string $alias, string $prefix, ?string $dateFrom, ?string $dateTo): array
    {
        $conditions = [];
        $parameters = [];
        if ($dateFrom !== null) {
            $conditions[] = $alias . '.fecha_creacion >= :' . $prefix . '_from';
            $parameters[$prefix . '_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== null) {
            $conditions[] = $alias . '.fecha_creacion < DATE_ADD(:' . $prefix . '_to, INTERVAL 1 DAY)';
            $parameters[$prefix . '_to'] = $dateTo;
        }
        if (!$conditions) {
            return ['sql' => '', 'parameters' => []];
        }
        return [
            'sql' => ' AND ' . implode(' AND ', $conditions),
            'parameters' => $parameters,
        ];
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }
        return $name;
    }
}
