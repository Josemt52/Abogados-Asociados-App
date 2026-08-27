<?php

namespace App\Services;

use Illuminate\Support\Str;

class ExpedienteHeaderParser
{
    /** @var array<string, string> */
    private array $labelPatterns = [
        'numero' => '(?:(?:N[°º]|NRO\.?|N[ÚU]MERO)\s*(?:DE\s+)?EXPEDIENTE|EXP(?:EDIENTE)?(?:\s+JUDICIAL)?\.?\s*(?:N(?:RO|ÚMERO)?\.?|N[°º])?)',
        'materia' => 'MATERIA',
        'juzgado' => '(?:JUZGADO|ÓRGANO\s+JURISDICCIONAL)',
        'especialista' => 'ESPECIALISTA(?:\s+(?:LEGAL|DE\s+CAUSA))?',
        'tercero' => 'TERCER(?:O|OS|A|AS)(?:\s*\([A-Z]+\))?',
        'demandado' => '(?:DEMANDAD(?:O|OS|A|AS)(?:\s*\([A-Z]+\))?|PARTE\s+DEMANDADA)',
        'demandante' => '(?:DEMANDANTE(?:S|\(S\))?|PARTE\s+DEMANDANTE)',
    ];

    /**
     * @return array{fields: array<string, mixed>, confidence: float, field_confidence: array<string, float>}
     */
    public function parse(string $text, string $method = 'docx_text', ?float $ocrConfidence = null): array
    {
        $lines = $this->lines($text);
        $values = [
            'numero' => [],
            'materia' => [],
            'juzgado' => [],
            'especialista' => [],
            'tercero' => [],
            'demandado' => [],
            'demandante' => [],
        ];

        foreach ($lines as $index => $line) {
            foreach ($this->labelPatterns as $field => $pattern) {
                $match = [];

                if (preg_match('/^\s*'.$pattern.'(?=\s|[:\-–—.]|$)\s*(?:[:\-–—.]\s*)?(.*)$/iu', $line, $match) !== 1) {
                    continue;
                }

                $value = $this->cleanValue((string) ($match[1] ?? ''));

                if ($value === '' && isset($lines[$index + 1]) && ! $this->startsWithLabel($lines[$index + 1])) {
                    $value = $this->cleanValue($lines[$index + 1]);
                }

                if ($field === 'numero') {
                    $value = $this->extractCaseNumber($value);
                }

                if ($value !== '') {
                    foreach ($this->splitMultipleValues($field, $value) as $item) {
                        $values[$field][] = $item;
                    }
                }

                if (in_array($field, ['tercero', 'demandado', 'demandante'], true)) {
                    for ($cursor = $index + 1; $cursor < min(count($lines), $index + 11); $cursor++) {
                        $continuation = $lines[$cursor];

                        if ($this->startsWithLabel($continuation) || $this->startsDocumentBody($continuation)) {
                            break;
                        }

                        foreach ($this->splitMultipleValues($field, $this->cleanValue($continuation)) as $item) {
                            $values[$field][] = $item;
                        }
                    }
                }

                break;
            }
        }

        $number = $values['numero'][0] ?? null;
        $fields = [
            'numero' => $number,
            'materia' => $values['materia'][0] ?? null,
            'juzgado' => $values['juzgado'][0] ?? null,
            'especialista' => $values['especialista'][0] ?? null,
            'tercero' => array_values(array_unique($values['tercero'])),
            'demandado' => array_values(array_unique($values['demandado'])),
            'demandante' => array_values(array_unique($values['demandante'])),
        ];

        $fieldConfidence = [];
        foreach ($fields as $field => $value) {
            $present = is_array($value) ? $value !== [] : filled($value);
            $fieldConfidence[$field] = $present ? ($field === 'numero' ? $this->numberConfidence((string) $value) : 0.9) : 0.0;
        }

        $requiredCoverage = collect(['materia', 'juzgado', 'especialista', 'demandado', 'demandante'])
            ->filter(fn (string $field): bool => $fieldConfidence[$field] > 0)
            ->count() / 5;
        $partyBonus = $fieldConfidence['tercero'] > 0 ? 0.05 : 0.0;
        $sourceConfidence = str_contains($method, 'ocr')
            ? max(0.0, min(1.0, $ocrConfidence ?? 0.5))
            : 1.0;
        $confidence = ($fieldConfidence['numero'] * 0.55)
            + ($requiredCoverage * 0.35)
            + $partyBonus
            + ($sourceConfidence * 0.05);

        // Un OCR inseguro no puede volverse "confiable" solo porque produjo
        // texto con la forma esperada. En documentos digitales no se aplica
        // este límite porque la lectura proviene directamente del Word.
        if (str_contains($method, 'ocr')) {
            $confidence = min($confidence, $sourceConfidence);
        }

        return [
            'fields' => $fields,
            'confidence' => round(max(0.0, min(1.0, $confidence)), 4),
            'field_confidence' => $fieldConfidence,
        ];
    }

    /** @return list<string> */
    private function lines(string $text): array
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $lines = preg_split('/\n+/u', $text) ?: [];

        return array_values(array_filter(array_map(function (string $line): string {
            return trim(preg_replace('/[\t ]+/u', ' ', $line) ?? $line);
        }, $lines), fn (string $line): bool => $line !== ''));
    }

    private function startsWithLabel(string $line): bool
    {
        foreach ($this->labelPatterns as $pattern) {
            if (preg_match('/^\s*'.$pattern.'\b/iu', $line) === 1) {
                return true;
            }
        }

        return false;
    }

    private function cleanValue(string $value): string
    {
        $value = preg_replace('/^[\s:;.,\-–—°º]+/u', '', trim($value)) ?? trim($value);

        return Str::limit(trim($value), 5000, '');
    }

    private function startsDocumentBody(string $line): bool
    {
        return preg_match(
            '/^(?:RESOLUCI[ÓO]N|AUTO|SENTENCIA|SUMILLA|VISTOS?|ESCRITO|CUADERNO|FECHA)\b/iu',
            trim($line)
        ) === 1;
    }

    private function extractCaseNumber(string $value): string
    {
        $value = preg_replace('/^(?:N(?:RO|ÚMERO)?\.?|N[°º])\s*/iu', '', $value) ?? $value;

        if (preg_match('/\b[A-Z0-9]{1,12}(?:\s*[-\/]\s*[A-Z0-9]{1,12}){1,10}\b/iu', $value, $match) === 1) {
            return preg_replace('/\s+/u', '', trim($match[0])) ?? trim($match[0]);
        }

        if (preg_match('/\b\d{3,12}\b/u', $value, $match) === 1) {
            return trim($match[0]);
        }

        return Str::limit(trim($value), 100, '');
    }

    /** @return list<string> */
    private function splitMultipleValues(string $field, string $value): array
    {
        if (! in_array($field, ['tercero', 'demandado', 'demandante'], true)) {
            return [$value];
        }

        $parts = preg_split('/\s*(?:;|\||\n)\s*/u', $value) ?: [$value];

        return array_values(array_filter(array_map(
            fn (string $part): string => Str::limit(trim($part), 1000, ''),
            $parts
        ), fn (string $part): bool => $part !== ''));
    }

    private function numberConfidence(string $number): float
    {
        if (preg_match('/^[A-Z0-9]{1,12}(?:[-\/][A-Z0-9]{1,12}){1,10}$/iu', $number) === 1) {
            return 0.98;
        }

        return preg_match('/^\d{3,12}$/', $number) === 1 ? 0.85 : 0.6;
    }
}
