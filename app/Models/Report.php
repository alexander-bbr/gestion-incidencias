<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Report
{
    public function __construct(private PDO $db)
    {
    }

    public function data(): array
    {
        return [
            'statuses' => $this->db->query(
                'SELECT e.nombre AS label, e.color, COUNT(i.id_incidencia) AS total
                 FROM estado e LEFT JOIN incidencia i ON i.id_estado = e.id_estado
                 GROUP BY e.id_estado ORDER BY e.id_estado'
            )->fetchAll(),
            'priorities' => $this->db->query(
                'SELECT p.nombre AS label, p.color, COUNT(i.id_incidencia) AS total
                 FROM prioridad p LEFT JOIN incidencia i ON i.id_prioridad = p.id_prioridad
                 GROUP BY p.id_prioridad ORDER BY p.nivel'
            )->fetchAll(),
            'categories' => $this->db->query(
                'SELECT c.nombre AS label, COUNT(i.id_incidencia) AS total
                 FROM categoria c LEFT JOIN incidencia i ON i.id_categoria = c.id_categoria
                 GROUP BY c.id_categoria ORDER BY total DESC, c.nombre LIMIT 5'
            )->fetchAll(),
            'resolution' => $this->db->query(
                'SELECT DATE_FORMAT(fecha_resolucion, "%Y-%m") AS month,
                        AVG(TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_resolucion)) AS hours
                 FROM incidencia WHERE fecha_resolucion IS NOT NULL
                 GROUP BY month ORDER BY month DESC LIMIT 12'
            )->fetchAll(),
        ];
    }

    public function createExcelFile(array $data): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('La extensión PHP zip es necesaria para exportar a Excel.');
        }

        $sheets = [
            ['Estados', 'Estado', 'Incidencias', $data['statuses'], 'total'],
            ['Prioridades', 'Prioridad', 'Incidencias', $data['priorities'], 'total'],
            ['Categorías', 'Categoría', 'Incidencias', $data['categories'], 'total'],
            ['Resolución', 'Mes', 'Horas promedio', $data['resolution'], 'hours'],
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
            $rows = [[$sheet[1], $sheet[2]]];
            foreach ($sheet[3] as $entry) {
                $rows[] = [$entry[$sheet[0] === 'Resolución' ? 'month' : 'label'], $entry[$sheet[4]]];
            }
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
                $reference = chr(65 + $columnIndex) . ($rowIndex + 1);
                if ($columnIndex === 1 && $rowIndex > 0) {
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
}
