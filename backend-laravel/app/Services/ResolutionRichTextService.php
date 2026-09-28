<?php

namespace App\Services;

use App\Models\Expediente;
use App\Models\Resolucion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Tab;
use Throwable;

class ResolutionRichTextService
{
    public const SCHEMA_VERSION = 2;

    /** @var list<int> */
    public const FONT_SIZES = [8, 9, 10, 11, 12, 14, 16, 18, 20, 24];

    private const MAX_JSON_BYTES = 250_000;

    private const MAX_NODES = 5_000;

    private const MAX_TEXT_CHARACTERS = 100_000;

    /** @return array{type: string, content: array<int, array<string, mixed>>} */
    public function emptyDocument(): array
    {
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'attrs' => ['textAlign' => 'left'],
                ],
            ],
        ];
    }

    /**
     * Validate and canonicalize the small Tiptap schema supported by the app.
     *
     * @param  array<string, mixed>  $document
     * @return array{type: string, content: array<int, array<string, mixed>>}
     */
    public function normalize(array $document): array
    {
        $encoded = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded) || strlen($encoded) > self::MAX_JSON_BYTES) {
            $this->invalid('El documento supera el tamaño permitido.');
        }

        if (($document['type'] ?? null) !== 'doc'
            || array_diff(array_keys($document), ['type', 'content']) !== []) {
            $this->invalid('La estructura principal del documento no es válida.');
        }

        $blocks = $document['content'] ?? [];
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            $this->invalid('El contenido debe ser una lista de párrafos o listas.');
        }
        $nodeCount = 1;
        $characterCount = 0;
        $normalized = [];
        foreach ($blocks as $block) {
            $normalized[] = $this->normalizeBlock($block, $nodeCount, $characterCount);
        }
        return ['type' => 'doc', 'content' => $normalized ?: $this->emptyDocument()['content']];
    }

    private function normalizeBlock(mixed $block, int &$nodeCount, int &$characterCount, int $depth = 0): array
    {
        $nodeCount++;
        if (! is_array($block) || $nodeCount > self::MAX_NODES || $depth > 8
            || array_diff(array_keys($block), ['type', 'attrs', 'content']) !== []) {
            $this->invalid('El documento contiene demasiados elementos o una estructura no compatible.');
        }
        if (($block['type'] ?? null) === 'paragraph') {
            return $this->normalizeParagraph($block, $nodeCount, $characterCount);
        }
        $type = $block['type'] ?? null;
        if (! in_array($type, ['bulletList', 'orderedList'], true) || $depth >= 8) {
            $this->invalid('El documento contiene un tipo de elemento no compatible o demasiados niveles de lista.');
        }
        $attrs = $block['attrs'] ?? [];
        if (! is_array($attrs)) $this->invalid('La configuración de la lista no es válida.');
        if ($type === 'orderedList') {
            $start = $attrs['start'] ?? 1;
            $style = $attrs['type'] ?? null;
            if (array_diff(array_keys($attrs), ['start', 'type']) !== []
                || ! is_int($start) || $start < 1 || $start > 999999
                || ! in_array($style, [null, '1', 'a', 'A', 'i', 'I'], true)) {
                $this->invalid('La numeración de la lista no es válida.');
            }
            $normalizedAttrs = ['start' => $start, 'type' => $style];
        } else {
            $marker = $attrs['marker'] ?? 'bullet';
            if (array_diff(array_keys($attrs), ['marker']) !== []
                || ! in_array($marker, ['bullet', 'dash'], true)) {
                $this->invalid('La viñeta de la lista no es válida.');
            }
            $normalizedAttrs = ['marker' => $marker];
        }
        $items = $block['content'] ?? null;
        if (! is_array($items) || ! array_is_list($items) || $items === []) {
            $this->invalid('La lista debe contener al menos un elemento.');
        }
        $normalizedItems = [];
        foreach ($items as $item) {
            $nodeCount++;
            if (! is_array($item) || $nodeCount > self::MAX_NODES
                || ($item['type'] ?? null) !== 'listItem'
                || array_diff(array_keys($item), ['type', 'content']) !== []) {
                $this->invalid('Un elemento de lista no es válido.');
            }
            $children = $item['content'] ?? null;
            if (! is_array($children) || ! array_is_list($children) || $children === []
                || ($children[0]['type'] ?? null) !== 'paragraph') {
                $this->invalid('Cada elemento de lista debe comenzar con un párrafo.');
            }
            $normalizedChildren = [];
            foreach ($children as $child) {
                $normalizedChildren[] = $this->normalizeBlock($child, $nodeCount, $characterCount, $depth + 1);
            }
            $normalizedItems[] = ['type' => 'listItem', 'content' => $normalizedChildren];
        }
        return ['type' => $type, 'attrs' => $normalizedAttrs, 'content' => $normalizedItems];
    }

    private function normalizeParagraph(array $paragraph, int &$nodeCount, int &$characterCount): array
    {
        $attrs = $paragraph['attrs'] ?? [];

        if (! is_array($attrs) || array_diff(array_keys($attrs), ['textAlign']) !== []) {
            $this->invalid('La configuración de un párrafo no es válida.');
        }

        $alignment = $attrs['textAlign'] ?? 'left';

        if ($alignment === null) {
            $alignment = 'left';
        }

        if (! in_array($alignment, ['left', 'center', 'right', 'justify'], true)) {
            $this->invalid('La alineación seleccionada no es compatible.');
        }

        $children = $paragraph['content'] ?? [];

        if (! is_array($children) || ! array_is_list($children)) {
            $this->invalid('El contenido de un párrafo no es válido.');
        }

        $normalizedChildren = [];

        foreach ($children as $child) {
            if (! is_array($child)) {
                $this->invalid('El documento contiene un elemento no válido.');
            }

            $nodeCount++;

            if ($nodeCount > self::MAX_NODES) {
                $this->invalid('El documento contiene demasiados elementos.');
            }

            if (($child['type'] ?? null) === 'hardBreak') {
                if (array_diff(array_keys($child), ['type']) !== []) {
                    $this->invalid('Un salto de línea contiene propiedades no compatibles.');
                }

                $normalizedChildren[] = ['type' => 'hardBreak'];

                continue;
            }

            if (($child['type'] ?? null) !== 'text'
                || array_diff(array_keys($child), ['type', 'text', 'marks']) !== []) {
                $this->invalid('El documento contiene un tipo de elemento no compatible.');
            }

            $text = $child['text'] ?? null;

            if (! is_string($text) || ! mb_check_encoding($text, 'UTF-8')) {
                $this->invalid('El documento contiene texto no válido.');
            }

            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
            $characterCount += mb_strlen($text, 'UTF-8');

            if ($characterCount > self::MAX_TEXT_CHARACTERS) {
                $this->invalid('El documento supera la cantidad de texto permitida.');
            }

            $marks = $this->normalizeMarks($child['marks'] ?? []);
            $normalizedChild = ['type' => 'text', 'text' => $text];

            if ($marks !== []) {
                $normalizedChild['marks'] = $marks;
            }

            $normalizedChildren[] = $normalizedChild;
        }

        $normalizedParagraph = [
            'type' => 'paragraph',
            'attrs' => ['textAlign' => $alignment],
        ];

        if ($normalizedChildren !== []) {
            $normalizedParagraph['content'] = $normalizedChildren;
        }


        return $normalizedParagraph;
    }

    /** @param array<string, mixed> $document */
    public function hasMeaningfulContent(array $document): bool
    {
        if (($document['type'] ?? null) === 'text' && trim((string) ($document['text'] ?? '')) !== '') {
            return true;
        }
        foreach ($document['content'] ?? [] as $child) {
            if (is_array($child) && $this->hasMeaningfulContent($child)) return true;
        }
        return false;
    }

    /** @return list<array{label: string, value: string}> */
    public function headerFields(Expediente $expediente): array
    {
        $labels = [
            'numero' => 'Expediente',
            'materia' => 'Materia',
            'juzgado' => 'Juzgado',
            'especialista' => 'Especialista',
            'tercero' => 'Tercero',
            'demandado' => 'Demandado',
            'demandante' => 'Demandante',
        ];
        $result = [];

        foreach ($this->headerData($expediente) as $field => $value) {
            $normalized = trim($value);

            if ($normalized !== '') {
                $result[] = [
                    'label' => $labels[$field],
                    'value' => mb_strtoupper($normalized, 'UTF-8'),
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{
     *     numero: string,
     *     materia: string,
     *     juzgado: string,
     *     especialista: string,
     *     tercero: string,
     *     demandado: string,
     *     demandante: string
     * }
     */
    public function headerData(Expediente $expediente): array
    {
        return [
            'numero' => (string) ($expediente->numero ?? ''),
            'materia' => (string) ($expediente->materia ?? ''),
            'juzgado' => (string) ($expediente->juzgado ?? ''),
            'especialista' => (string) ($expediente->especialista ?? ''),
            'tercero' => (string) ($expediente->tercero ?? ''),
            'demandado' => (string) ($expediente->demandado ?? ''),
            'demandante' => (string) ($expediente->demandante ?? ''),
        ];
    }

    /** @param array<string, mixed> $document */
    public function generateDocx(
        Expediente $expediente,
        Resolucion $resolution,
        array $document
    ): string {
        $document = $this->normalize($document);
        Settings::setOutputEscapingEnabled(true);
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(12);
        $section = $phpWord->addSection([
            'pageSizeW' => 11906,
            'pageSizeH' => 16838,
            'marginTop' => 720,
            'marginRight' => 720,
            'marginBottom' => 720,
            'marginLeft' => 720,
        ]);

        foreach ($this->headerFields($expediente) as $field) {
            $line = $section->addTextRun([
                'spaceAfter' => 0,
                'lineHeight' => 1,
                'indentation' => ['left' => 4320],
                'tabs' => [new Tab(Tab::TAB_STOP_LEFT, 5760)],
            ]);
            $line->addText($field['label'], ['name' => 'Arial', 'size' => 12]);
            $line->addText("\t: {$field['value']}", ['name' => 'Arial', 'size' => 12]);
        }

        $section->addTextBreak();
        $section->addText(
            'RESOLUCIÓN N° '.$resolution->numero,
            ['name' => 'Arial', 'size' => 12, 'bold' => true],
            ['spaceAfter' => 120]
        );

        $listIndex = 0;
        $this->appendBlocks($phpWord, $section, $document['content'], $listIndex);

        $directory = storage_path('app/temp/rich-text');
        File::ensureDirectoryExists($directory, 0755, true);
        $path = $directory.'/'.Str::uuid().'.docx';

        try {
            IOFactory::createWriter($phpWord, 'Word2007')->save($path);
            $binary = file_get_contents($path);

            if (! is_string($binary) || ! str_starts_with($binary, "PK\x03\x04")) {
                throw ValidationException::withMessages([
                    'content' => 'No se pudo generar el documento Word.',
                ]);
            }

            return $binary;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'content' => 'No se pudo generar el documento Word.',
            ]);
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function appendBlocks(PhpWord $word, \PhpOffice\PhpWord\Element\Section $section, array $blocks, int &$listIndex, int $depth = 0): void
    {
        foreach ($blocks as $block) {
            if ($block['type'] === 'paragraph') {
                $this->appendParagraph($section, $block, $depth);
                continue;
            }
            $styleName = 'resolution_list_'.(++$listIndex);
            $ordered = $block['type'] === 'orderedList';
            $format = $ordered ? match ($block['attrs']['type'] ?? null) {
                'a' => 'lowerLetter', 'A' => 'upperLetter',
                'i' => 'lowerRoman', 'I' => 'upperRoman',
                default => 'decimal',
            } : 'bullet';
            $word->addNumberingStyle($styleName, [
                'type' => 'singleLevel',
                'levels' => [[
                    'format' => $format,
                    'text' => $ordered ? '%1.' : (($block['attrs']['marker'] ?? 'bullet') === 'dash' ? '–' : '•'),
                    'start' => $block['attrs']['start'] ?? 1,
                    'left' => ($depth + 1) * 360, 'hanging' => 240,
                    'tabPos' => ($depth + 1) * 360, 'font' => 'Arial',
                ]],
            ]);
            foreach ($block['content'] as $item) {
                foreach ($item['content'] as $index => $child) {
                    if ($child['type'] === 'paragraph') {
                        $this->appendParagraph($section, $child, $depth + 1, $index === 0 ? $styleName : null);
                    } else {
                        $this->appendBlocks($word, $section, [$child], $listIndex, $depth + 1);
                    }
                }
            }
        }
    }

    private function appendParagraph(\PhpOffice\PhpWord\Element\Section $section, array $paragraph, int $depth = 0, ?string $listStyle = null): void
    {
        $style = [
            'alignment' => match ($paragraph['attrs']['textAlign'] ?? 'left') {
                'center' => Jc::CENTER, 'right' => Jc::RIGHT,
                'justify' => Jc::BOTH, default => Jc::LEFT,
            },
            'spaceAfter' => 120, 'lineHeight' => 1.15,
        ];
        if ($depth > 0 && $listStyle === null) $style['indentation'] = ['left' => $depth * 360];
        $run = $listStyle !== null ? $section->addListItemRun(0, $listStyle, $style) : $section->addTextRun($style);
        $children = $paragraph['content'] ?? [];
        if ($children === []) $run->addText(' ', ['name' => 'Arial', 'size' => 12]);
        foreach ($children as $child) {
            if ($child['type'] === 'hardBreak') $run->addTextBreak();
            else $run->addText((string) $child['text'], $this->fontStyle($child['marks'] ?? []));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeMarks(mixed $marks): array
    {
        if (! is_array($marks) || ! array_is_list($marks)) {
            $this->invalid('El formato aplicado al texto no es válido.');
        }

        $normalized = [];
        $seen = [];

        foreach ($marks as $mark) {
            if (! is_array($mark) || ! is_string($mark['type'] ?? null)) {
                $this->invalid('El formato aplicado al texto no es válido.');
            }

            $type = $mark['type'];

            if (isset($seen[$type])) {
                $this->invalid('Un formato de texto está repetido.');
            }

            $seen[$type] = true;

            if (in_array($type, ['bold', 'underline', 'italic'], true)) {
                if (array_diff(array_keys($mark), ['type', 'attrs']) !== []
                    || (isset($mark['attrs']) && $mark['attrs'] !== [] && $mark['attrs'] !== null)) {
                    $this->invalid('Un formato de texto contiene propiedades no compatibles.');
                }

                $normalized[] = ['type' => $type];

                continue;
            }

            if ($type !== 'textStyle'
                || array_diff(array_keys($mark), ['type', 'attrs']) !== []
                || ! is_array($mark['attrs'] ?? null)
                || array_diff(array_keys($mark['attrs']), ['fontSize']) !== []) {
                $this->invalid('El documento contiene un formato de texto no compatible.');
            }

            $fontSize = $mark['attrs']['fontSize'] ?? null;

            if (! is_string($fontSize)
                || preg_match('/^(\d{1,2})pt$/', $fontSize, $matches) !== 1
                || ! in_array((int) $matches[1], self::FONT_SIZES, true)) {
                $this->invalid('El tamaño de texto seleccionado no es compatible.');
            }

            $normalized[] = [
                'type' => 'textStyle',
                'attrs' => ['fontSize' => ((int) $matches[1]).'pt'],
            ];
        }

        return $normalized;
    }

    /** @param list<array<string, mixed>> $marks
     * @return array<string, mixed>
     */
    private function fontStyle(array $marks): array
    {
        $style = ['name' => 'Arial', 'size' => 12];

        foreach ($marks as $mark) {
            if ($mark['type'] === 'bold') {
                $style['bold'] = true;
            } elseif ($mark['type'] === 'italic') {
                $style['italic'] = true;
            } elseif ($mark['type'] === 'underline') {
                $style['underline'] = Font::UNDERLINE_SINGLE;
            } elseif ($mark['type'] === 'textStyle') {
                $style['size'] = (int) rtrim((string) $mark['attrs']['fontSize'], 'pt');
            }
        }

        return $style;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['content' => $message]);
    }
}
