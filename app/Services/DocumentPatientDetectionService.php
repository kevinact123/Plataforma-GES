<?php

namespace App\Services;

use App\Models\DocumentoGeneral;
use App\Models\Paciente;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

class DocumentPatientDetectionService
{
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'tif', 'tiff'];

    public function __construct(private readonly DocumentAnalysisService $analysis) {}

    /**
     * @return array{status:string,rut:?string,nombre:?string,paciente:?array,message:string}
     */
    public function detect(DocumentoGeneral $documento): array
    {
        $path = Storage::disk('local')->path((string) $documento->ruta_archivo);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! is_file($path)) {
            return $this->result('sin_datos', null, null, null, 'No se encontró el archivo del documento.');
        }

        $text = $this->extractText($path, $extension);

        if ($text === null || trim($text) === '') {
            $needsOcr = $extension === 'pdf' || in_array($extension, self::IMAGE_EXTENSIONS, true);

            return $this->result(
                $needsOcr ? 'ocr_no_disponible' : 'sin_datos',
                null,
                null,
                null,
                $needsOcr
                    ? 'No fue posible leer el texto del documento escaneado (OCR no disponible o sin texto legible).'
                    : 'No se pudo leer texto del documento.'
            );
        }

        $rut = $this->findRut($text);
        if ($rut === null) {
            return $this->result('sin_datos', null, null, null, 'No se detectó un RUT válido en el documento.');
        }

        $paciente = $this->findPatient($rut);
        if ($paciente) {
            return $this->result('registrado', $rut, $this->fullName($paciente), $this->patientData($paciente),
                'El paciente ya está registrado en la base de datos.');
        }

        return $this->result('no_registrado', $rut, $this->findName($text, $rut), null,
            'El paciente no está agregado a la base de datos.');
    }

    public function findPatient(string $rut): ?Paciente
    {
        $normalized = $this->analysis->normalizeRut($rut);
        $body = preg_replace('/\D/', '', explode('-', $normalized)[0]);

        return Paciente::query()->get(['id_paciente', 'rut', 'nombre', 'apellido_paterno', 'apellido_materno'])
            ->first(function (Paciente $p) use ($normalized, $body): bool {
                $stored = $this->analysis->normalizeRut((string) $p->rut);

                return $stored === $normalized
                    || preg_replace('/\D/', '', explode('-', $stored)[0]) === $body;
            });
    }

    public function patientData(Paciente $p): array
    {
        return ['id_paciente' => $p->id_paciente, 'rut' => $p->rut, 'nombre' => $this->fullName($p)];
    }

    private function fullName(Paciente $p): string
    {
        return trim(implode(' ', array_filter([$p->nombre, $p->apellido_paterno, $p->apellido_materno])));
    }

    private function result(string $status, ?string $rut, ?string $nombre, ?array $paciente, string $message): array
    {
        return compact('status', 'rut', 'nombre', 'paciente', 'message');
    }

    private function extractText(string $path, string $extension): ?string
    {
        if (in_array($extension, ['txt', 'csv'], true)) {
            return (string) file_get_contents($path);
        }

        if ($extension === 'docx') {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                return null;
            }
            $xml = $zip->getFromName('word/document.xml') ?: '';
            $zip->close();

            return html_entity_decode(strip_tags(str_replace(['</w:p>', '</w:tc>'], "\n", $xml)));
        }

        if ($extension === 'pdf') {
            $text = $this->run(config('services.ocr.pdftotext_path'), [$path, '-']);
            if ($text !== null && trim($text) !== '') {
                return $text;
            }

            return $this->ocrPdf($path);
        }

        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return $this->ocrImage($path);
        }

        return null;
    }

    private function ocrImage(string $path): ?string
    {
        return $this->run(config('services.ocr.tesseract_path'), [$path, 'stdout', '-l', config('services.ocr.lang', 'spa')]);
    }

    private function ocrPdf(string $path): ?string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ocr_'.uniqid();
        if (! @mkdir($dir)) {
            return null;
        }

        try {
            $this->run(config('services.ocr.pdftoppm_path'), ['-r', '200', '-l', '3', '-png', $path, $dir.DIRECTORY_SEPARATOR.'p']);
            $text = '';
            foreach (glob($dir.DIRECTORY_SEPARATOR.'p*.png') ?: [] as $image) {
                $text .= "\n".($this->ocrImage($image) ?? '');
            }

            return $text;
        } finally {
            foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    private function run(?string $binary, array $args): ?string
    {
        if (! $binary || ! is_file($binary)) {
            return null;
        }

        try {
            $process = new Process(array_merge([$binary], $args));
            $process->setTimeout(120);
            $process->run();

            return $process->isSuccessful() ? $process->getOutput() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function findRut(string $text): ?string
    {
        if (! preg_match_all('/\b(\d{1,2}(?:[.\s]?\d{3}){2}\s?-\s?[\dkK])\b|\b(\d{7,8}\s?-\s?[\dkK])\b/', $text, $matches)) {
            return null;
        }

        foreach (array_merge($matches[1], $matches[2]) as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $clean = strtoupper(preg_replace('/[.\s]/', '', $candidate));
            [$body, $dv] = explode('-', $clean);
            if ($this->checkDigit($body) === $dv) {
                return $this->analysis->normalizeRut($clean);
            }
        }

        return null;
    }

    private function checkDigit(string $body): string
    {
        $sum = 0;
        $factor = 2;
        foreach (array_reverse(str_split($body)) as $digit) {
            $sum += (int) $digit * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $dv = 11 - ($sum % 11);

        return $dv === 11 ? '0' : ($dv === 10 ? 'K' : (string) $dv);
    }

    private function findName(string $text, string $rut): ?string
    {
        $namePattern = "[A-ZÁÉÍÓÚÑa-záéíóúñ'][A-ZÁÉÍÓÚÑa-záéíóúñ' ]{4,}";

        if (preg_match('/(?:nombre(?:\s+completo)?|paciente|nombres?\s+y\s+apellidos?)\s*[:\-]\s*('.$namePattern.')/iu', $text, $m)) {
            return $this->cleanName($m[1]);
        }

        $lines = preg_split('/\R/', $text);
        $body = preg_replace('/\D/', '', explode('-', $rut)[0]);
        foreach ($lines as $i => $line) {
            if (! str_contains(preg_replace('/\D/', '', $line), $body)) {
                continue;
            }
            foreach ([$i, $i - 1, $i + 1] as $idx) {
                $candidate = trim(preg_replace('/[^A-Za-zÁÉÍÓÚÑáéíóúñ\' ]/u', ' ', $lines[$idx] ?? ''));
                if (preg_match('/^'.$namePattern.'$/u', $candidate) && str_word_count($candidate) >= 2) {
                    return $this->cleanName($candidate);
                }
            }
        }

        return null;
    }

    private function cleanName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name));
    }
}
