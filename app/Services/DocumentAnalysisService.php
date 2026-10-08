<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use ZipArchive;

class DocumentAnalysisService
{
    private const FIELD_ALIASES = [
        'rut' => ['rut', 'run', 'documento', 'documento_identidad'],
        'nombre' => ['nombre', 'nombres', 'name'],
        'numero_dau' => ['numero_dau', 'numero dau', 'n° dau', 'n dau', 'dau'],
        'apellido_paterno' => ['apellido_paterno', 'apellido paterno', 'primer_apellido', 'a paterno', 'apaterno'],
        'apellido_materno' => ['apellido_materno', 'apellido materno', 'segundo_apellido', 'a materno', 'amaterno'],
        'hora_cierre_dau' => ['hora_cierre_dau', 'hora cierre dau', 'hora de cierre dau', 'hora cierre'],
        'sexo' => ['sexo', 'genero', 'género'],
        'domicilio' => ['domicilio', 'direccion', 'dirección'],
        'atencion' => ['atencion', 'atención'],
        'consultorio' => ['consultorio'],
        'reporte' => ['reporte'],
        'ges' => ['ges'],
        'prevision' => ['prevision', 'previsión'],
        'tipo_fonasa' => ['tipo_fonasa', 'tipo fonasa', 'fonasa'],
        'forma_pago' => ['forma_pago', 'forma pago'],
        'servicio_egreso' => ['servicio_egreso', 'servicio egre', 'servicio egreso'],
        'establecimiento_emision' => ['establecimiento_emision', 'establecimiento origen', 'establecimiento de origen'],
        'establecimiento_destino' => ['establecimiento_destino', 'establecimiento destino', 'destino'],
        'desc_cie10' => ['desc_cie10', 'desc cie10', 'desc.cie10', 'cie10'],
        'edad' => ['edad'],
        'ingreso' => ['ingreso', 'fecha ingreso paciente'],
        'id_patologia' => ['id_patologia', 'patologia_id'],
        'id_prioridad' => ['id_prioridad', 'prioridad_id'],
        'id_tipo_registro' => ['id_tipo_registro', 'tipo_registro_id'],
        'tipo_tratamiento' => ['tipo_tratamiento', 'tratamiento'],
        'fecha_ingreso' => ['fecha_ingreso', 'fecha ingreso'],
        'fecha_limite' => ['fecha_limite', 'fecha limite', 'fecha límite'],
        'estado' => ['estado', 'status'],
        'observaciones' => ['observaciones', 'observacion', 'comentarios', 'comentario'],
    ];

    public function analyze(string $path, string $originalName, ?string $mimeType = null): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $type = $this->documentType($extension, $mimeType);
        $extraction = $this->extract($path, $extension);
        $rows = $this->mapRows($extraction['content']);
        $fields = $rows[0] ?? [];
        $validatedRows = [];
        $rowErrors = [];

        foreach ($rows as $index => $row) {
            $validation = Validator::make($row, [
                'rut' => ['nullable', 'string', 'max:20'],
                'numero_dau' => ['nullable', 'string', 'max:50'],
                'nombre' => ['nullable', 'string', 'max:100'],
                'apellido_paterno' => ['nullable', 'string', 'max:100'],
                'apellido_materno' => ['nullable', 'string', 'max:100'],
                'hora_cierre_dau' => ['nullable', 'date'],
                'sexo' => ['nullable', 'string', 'max:20'],
                'domicilio' => ['nullable', 'string', 'max:255'],
                'atencion' => ['nullable', 'string', 'max:100'],
                'consultorio' => ['nullable', 'string', 'max:100'],
                'reporte' => ['nullable', 'string', 'max:100'],
                'ges' => ['nullable', 'string', 'max:100'],
                'prevision' => ['nullable', 'string', 'max:100'],
                'tipo_fonasa' => ['nullable', 'string', 'max:100'],
                'forma_pago' => ['nullable', 'string', 'max:100'],
                'servicio_egreso' => ['nullable', 'string', 'max:100'],
                'establecimiento_emision' => ['nullable', 'string', 'max:255'],
                'establecimiento_destino' => ['nullable', 'string', 'max:255'],
                'desc_cie10' => ['nullable', 'string', 'max:255'],
                'edad' => ['nullable', 'integer', 'min:0', 'max:150'],
                'ingreso' => ['nullable', 'date'],
                'id_patologia' => ['nullable', 'integer'],
                'id_prioridad' => ['nullable', 'integer'],
                'id_tipo_registro' => ['nullable', 'integer'],
                'tipo_tratamiento' => ['nullable', 'string', 'max:100'],
                'fecha_ingreso' => ['nullable', 'date'],
                'fecha_limite' => ['nullable', 'date'],
                'estado' => ['nullable', 'string', 'max:50'],
                'observaciones' => ['nullable', 'string', 'max:5000'],
            ]);

            if ($validation->passes() && ! empty($row['rut']) && empty($row['nombre'])) {
                $rowErrors[$index + 2]['nombre'][] = 'El nombre es obligatorio para crear el paciente.';

                continue;
            }

            if ($validation->passes()) {
                $validatedRows[] = $validation->validated();
            } else {
                $rowErrors[$index + 2] = $validation->errors()->toArray();
            }
        }

        $errors = $rowErrors;
        if ($extraction['errors'] !== []) {
            $errors['_archivo'] = $extraction['errors'];
        }
        $isValid = $errors === [] && $validatedRows !== [];

        return [
            'tipo_documento' => $type,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'campos_detectados' => array_keys($fields),
            'datos' => $isValid ? ($validatedRows[0] ?? []) : [],
            'filas' => $isValid ? $validatedRows : [],
            'cantidad_filas' => count($rows),
            'datos_ignorados' => $this->ignoredFields($extraction['content']),
            'errores' => $errors,
            'puede_importar' => $isValid && $fields !== [],
        ];
    }

    private function documentType(string $extension, ?string $mimeType): string
    {
        return match (true) {
            in_array($extension, ['xls', 'xlsx', 'csv'], true) => 'hoja_calculo',
            in_array($extension, ['doc', 'docx'], true) => 'documento_word',
            $extension === 'pdf' => 'pdf',
            in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'tif', 'tiff'], true) => 'imagen',
            str_starts_with((string) $mimeType, 'image/') => 'imagen',
            default => 'texto',
        };
    }

    private function extract(string $path, string $extension): array
    {
        if (in_array($extension, ['csv', 'txt'], true)) {
            return ['content' => (string) file_get_contents($path), 'errors' => []];
        }

        if ($extension === 'docx') {
            return $this->extractZipXml($path, 'word/document.xml', 'No se pudo leer el contenido Word.');
        }

        if ($extension === 'xlsx') {
            return $this->extractXlsx($path);
        }

        return [
            'content' => '',
            'errors' => ["No hay un lector configurado para archivos .{$extension}; el archivo no se considera importable."],
        ];
    }

    private function extractZipXml(string $path, string $entry, string $error): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true || ($xml = $zip->getFromName($entry)) === false) {
            return ['content' => '', 'errors' => [$error]];
        }

        $zip->close();

        return ['content' => trim(strip_tags((string) $xml)), 'errors' => []];
    }

    private function extractXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return ['content' => '', 'errors' => ['No se pudo abrir el archivo Excel.']];
        }

        $sharedStrings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sharedDocument = new \DOMDocument;
            if ($sharedDocument->loadXML($xml)) {
                foreach ($sharedDocument->getElementsByTagName('si') as $item) {
                    $value = '';
                    foreach ($item->getElementsByTagName('t') as $text) {
                        $value .= $text->textContent;
                    }
                    $sharedStrings[] = trim($value);
                }
            }
        }

        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === '') {
            return ['content' => '', 'errors' => ['El archivo Excel no contiene una primera hoja legible.']];
        }

        $sheetDocument = new \DOMDocument;
        if (! $sheetDocument->loadXML($sheet)) {
            return ['content' => '', 'errors' => ['El archivo Excel contiene una hoja no legible.']];
        }

        $rows = [];
        foreach ($sheetDocument->getElementsByTagName('row') as $row) {
            $values = [];
            foreach ($row->getElementsByTagName('c') as $cell) {
                $reference = $cell->getAttribute('r');
                preg_match('/([A-Z]+)\d+/i', $reference, $match);
                $column = $this->columnNumber($match[1] ?? 'A');
                $type = $cell->getAttribute('t');
                $value = '';

                if ($type === 'inlineStr') {
                    foreach ($cell->getElementsByTagName('t') as $text) {
                        $value .= $text->textContent;
                    }
                } else {
                    $valueNode = $cell->getElementsByTagName('v')->item(0);
                    $value = $valueNode?->textContent ?? '';
                    if ($type === 's') {
                        $value = $sharedStrings[(int) $value] ?? $value;
                    }
                }

                $values[$column] = trim($value);
            }

            if ($values !== []) {
                ksort($values);
                $rows[] = $values;
            }
        }

        if ($rows === []) {
            return ['content' => '', 'errors' => ['El archivo Excel no contiene filas con datos.']];
        }

        $width = max(array_map('count', $rows));
        $lines = array_map(function (array $row) use ($width): string {
            $row = array_pad($row, $width, '');

            return implode(',', array_map(fn ($value) => str_getcsv((string) $value)[0] ?? '', $row));
        }, $rows);

        return ['content' => implode("\n", $lines), 'errors' => []];
    }

    private function columnNumber(string $column): int
    {
        $number = 0;
        foreach (str_split(strtoupper($column)) as $character) {
            $number = ($number * 26) + (ord($character) - 64);
        }

        return max(0, $number - 1);
    }

    private function mapRows(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        if (isset($lines[0]) && str_contains($lines[0], ',')) {
            $headers = str_getcsv(array_shift($lines));
            $rows = [];
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }

                $values = str_getcsv($line);
                $fields = [];
                foreach ($headers as $index => $header) {
                    $key = $this->canonicalKey($header);
                    if ($key !== null && isset($values[$index]) && trim($values[$index]) !== '') {
                        $fields[$key] = $this->normalizeFieldValue($key, trim($values[$index]));
                    }
                }
                $rows[] = $fields;
            }

            return $rows;
        }

        $fields = [];
        foreach ($lines as $line) {
            if (! preg_match('/^\s*([^:;=\t]+)\s*[:;=\t]\s*(.+?)\s*$/u', $line, $match)) {
                continue;
            }

            $key = $this->canonicalKey($match[1]);
            if ($key !== null) {
                $fields[$key] = $this->normalizeFieldValue($key, trim($match[2]));
            }
        }

        return $fields === [] ? [] : [$fields];
    }

    private function ignoredFields(string $content): array
    {
        $ignored = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        if (isset($lines[0]) && str_contains($lines[0], ',')) {
            foreach (str_getcsv($lines[0]) as $header) {
                if ($this->canonicalKey($header) === null) {
                    $ignored[] = trim($header);
                }
            }

            return array_values(array_unique(array_filter($ignored)));
        }

        foreach ($lines as $line) {
            if (preg_match('/^\s*([^:;=\t]+)\s*[:;=\t]/u', $line, $match) && $this->canonicalKey($match[1]) === null) {
                $ignored[] = trim($match[1]);
            }
        }

        return array_values(array_unique($ignored));
    }

    private function canonicalKey(string $key): ?string
    {
        $normalized = $this->normalizeHeader($key);
        foreach (self::FIELD_ALIASES as $field => $aliases) {
            $normalizedAliases = array_map(fn (string $alias): string => $this->normalizeHeader($alias), $aliases);
            if ($normalized === $this->normalizeHeader($field) || in_array($normalized, $normalizedAliases, true)) {
                return $field;
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = Str::ascii(mb_strtolower(trim($header)));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';

        return trim($normalized, '_');
    }

    private function normalizeFieldValue(string $key, string $value): string
    {
        if ($key === 'rut') {
            return $this->normalizeRut($value);
        }

        if ($key === 'hora_cierre_dau') {
            return $this->normalizeDateTimeValue($value);
        }

        if (! in_array($key, ['fecha_ingreso', 'fecha_limite'], true)) {
            return $value;
        }

        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $value, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
        }

        if (is_numeric($value) && (float) $value > 0 && (float) $value < 100000) {
            $date = new \DateTimeImmutable('1899-12-30');

            return $date->modify('+'.(int) $value.' days')->format('Y-m-d');
        }

        return $value;
    }

    private function normalizeDateTimeValue(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})(?:[ T]+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $m[3], $m[2], $m[1], $m[4] ?? 0, $m[5] ?? 0, $m[6] ?? 0);
        }

        if (is_numeric($value) && (float) $value > 0 && (float) $value < 100000) {
            $seconds = (int) round(((float) $value) * 86400);

            return (new \DateTimeImmutable('1899-12-30 00:00:00'))->modify("+{$seconds} seconds")->format('Y-m-d H:i:s');
        }

        return $value;
    }

    public function normalizeRut(string $rut): string
    {
        $value = strtoupper(trim($rut));
        $body = preg_replace('/[^0-9]/', '', preg_replace('/[-].*$/', '', $value)) ?? '';
        $providedCheckDigit = preg_match('/[- ]([0-9K])$/i', $value, $match) ? strtoupper($match[1]) : null;

        if ($body === '') {
            return $value;
        }

        $calculated = $this->rutCheckDigit($body);
        $checkDigit = $providedCheckDigit === $calculated ? $providedCheckDigit : $calculated;

        return number_format((int) $body, 0, '', '.').'-'.$checkDigit;
    }

    private function rutCheckDigit(string $body): string
    {
        $sum = 0;
        $multiplier = 2;
        for ($index = strlen($body) - 1; $index >= 0; $index--) {
            $sum += ((int) $body[$index]) * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }

        $remainder = 11 - ($sum % 11);

        return $remainder === 11 ? '0' : ($remainder === 10 ? 'K' : (string) $remainder);
    }
}
