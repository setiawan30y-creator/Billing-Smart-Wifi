<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Pembaca CSV/XLSX & penulis XLSX sederhana (tanpa dependensi tambahan).
 * XLSX: hanya sheet pertama, nilai teks/angka/boolean (formula dibaca hasil cache-nya).
 * @2026-Alifiandra
 */
class Spreadsheet
{
    /** @return array<int, array<int, string>> baris → kolom (string) */
    public static function read(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'csv', 'txt' => self::readCsv($path),
            'xlsx' => self::readXlsx($path),
            default => throw new RuntimeException('Format file tidak didukung. Gunakan .xlsx atau .csv.'),
        };
    }

    protected static function readCsv(string $path): array
    {
        $content = file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // BOM
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $first = strtok($content, "\n");
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $content);
        rewind($fh);
        $rows = [];
        while (($r = fgetcsv($fh, 0, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn ($v) => trim((string) $v), $r);
        }
        fclose($fh);

        return $rows;
    }

    protected static function readXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File XLSX tidak dapat dibuka.');
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = simplexml_load_string($xml, options: LIBXML_NONET);
            foreach ($sx->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string) $si->t;
                } else {
                    $t = '';
                    foreach ($si->r as $r) {
                        $t .= (string) $r->t;
                    }
                    $shared[] = $t;
                }
            }
        }

        $sheetPath = 'xl/worksheets/sheet1.xml';
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb && $rels) {
            $wbx = simplexml_load_string($wb, options: LIBXML_NONET);
            $first = $wbx->sheets->sheet[0] ?? null;
            if ($first) {
                $rid = (string) $first->attributes('r', true)->id;
                $rx = simplexml_load_string($rels, options: LIBXML_NONET);
                foreach ($rx->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = ltrim((string) $rel['Target'], '/');
                        $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                    }
                }
            }
        }

        $xml = $zip->getFromName($sheetPath);
        $zip->close();
        if ($xml === false) {
            throw new RuntimeException('Sheet pertama tidak ditemukan di file XLSX.');
        }

        $sx = simplexml_load_string($xml, options: LIBXML_NONET);
        $rows = [];
        foreach ($sx->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = self::colIndex(preg_replace('/\d+/', '', $ref));
                $type = (string) $c['t'];
                $v = match ($type) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => (string) ($c->is->t ?? ''),
                    'b' => ((string) $c->v) === '1' ? '1' : '0',
                    default => (string) $c->v,
                };
                $cells[$col] = trim($v);
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $line = [];
                for ($i = 0; $i <= $max; $i++) {
                    $line[] = $cells[$i] ?? '';
                }
                $rows[] = $line;
            } else {
                $rows[] = [];
            }
        }

        return $rows;
    }

    protected static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    /** Konversi nomor seri tanggal Excel → Y-m-d. */
    public static function excelDate(string $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }
        $days = (int) floor((float) $value);

        return gmdate('Y-m-d', ($days - 25569) * 86400);
    }

    /** Tulis XLSX sederhana (sheet tunggal, semua sel teks). */
    public static function writeXlsx(array $rows, string $sheetName = 'Data'): string
    {
        $esc = fn ($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $col = function (int $i) {
            $s = '';
            for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
                $s = chr(65 + ($i - 1) % 26).$s;
            }

            return $s;
        };
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach (array_values($rows) as $ri => $row) {
            $sheet .= '<row r="'.($ri + 1).'">';
            foreach (array_values($row) as $ci => $val) {
                $ref = $col($ci).($ri + 1);
                $style = $ri === 0 ? ' s="1"' : '';
                $sheet .= '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$esc($val).'</t></is></c>';
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$esc($sheetName).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf/><xf fontId="1" applyFont="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return $tmp;
    }
}
