<?php

namespace App\Services;

/**
 * Lecture et génération de classeurs Excel .xlsx sans dépendance externe
 * (format OOXML = archive ZIP de documents XML). Les fichiers .xls
 * (ancien format binaire) ne sont pas pris en charge : les convertir en
 * .xlsx (Enregistrer sous) ou en CSV avant import.
 */
class XlsxService
{
    public static function isSupported(string $extension): bool
    {
        return strtolower($extension) === 'xlsx';
    }

    /** Retourne la grille de la première feuille : tableau de lignes (chaînes). */
    public static function read(string $path): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("Impossible d'ouvrir le classeur Excel (format non pris en charge ? Utilisez .xlsx ou le CSV).");
        }

        try {
            $shared = self::sharedStrings($zip);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        } finally {
            $zip->close();
        }

        if ($xml === false) {
            throw new \RuntimeException('Feuille « sheet1 » introuvable dans le classeur Excel.');
        }

        $sheet = simplexml_load_string($xml);
        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            $colIdx = 0;
            foreach ($row->c as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $idx = $ref !== ''
                    ? self::columnIndex((string) preg_replace('/\d+$/', '', $ref))
                    : $colIdx;
                $colIdx++;
                $type = (string) ($cell['t'] ?? '');
                $value = '';

                if ($type === 's') {
                    $si = (int) ($cell->v ?? -1);
                    $value = $shared[$si] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = self::nodeText($cell->is);
                } else {
                    $value = (string) ($cell->v ?? '');
                }

                $cells[$idx] = $value;
            }

            if ($cells === []) {
                continue;
            }

            $line = [];
            $max = max(array_keys($cells));
            for ($i = 0; $i <= $max; $i++) {
                $line[] = trim((string) ($cells[$i] ?? ''));
            }

            if (implode('', $line) !== '') {
                $rows[] = $line;
            }
        }

        return $rows;
    }

    /** Construit un classeur .xlsx minimal (valeurs texte) sur le disque. */
    public static function writeTemplate(string $path, array $header, array $rows): void
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $sheetRows = [$header, ...$rows];
        $lines = [];
        foreach ($sheetRows as $r) {
            $cells = '';
            foreach ($r as $v) {
                $cells .= '<c t="inlineStr"><is><t xml:space="preserve">'.$escape($v).'</t></is></c>';
            }
            $lines[] = '<row>'.$cells.'</row>';
        }

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Import" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.implode('', $lines).'</sheetData></worksheet>';

        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer le classeur Excel.');
        }
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
    }

    private static function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $sst = simplexml_load_string($xml);
        $strings = [];

        foreach ($sst->si as $si) {
            $strings[] = self::nodeText($si);
        }

        return $strings;
    }

    /** Texte d'un nœud <si> : <t> direct ou concaténation des <r><t> (rich text). */
    private static function nodeText($node): string
    {
        $text = '';
        if (isset($node->t)) {
            $text = (string) $node->t;
        } elseif (isset($node->r)) {
            foreach ($node->r as $run) {
                $text .= (string) $run->t;
            }
        }

        return $text;
    }

    private static function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
