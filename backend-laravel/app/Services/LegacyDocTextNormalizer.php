<?php

namespace App\Services;

class LegacyDocTextNormalizer
{
    private const HEADER_SIGNAL_PATTERN = '/\b(?:EXPEDIENTE|ESPECIALISTA|MATERIA|JUZGADO|DEMANDANTE|DEMANDADO|TERCERO|SUMILLA|ESCRITO)\b/iu';

    /**
     * PHPWord puede interpretar algunos DOC binarios como si cada par de bytes
     * Windows-1252 fuera un carácter UTF-16. El resultado parece texto CJK,
     * aunque el documento original contiene texto latino normal.
     */
    public function normalize(string $text): string
    {
        $original = $this->clean($text);

        if (! $this->looksLikePackedWindowsText($text)) {
            return $original;
        }

        $candidate = $this->decodePackedWindowsText($text);

        if ($candidate === null) {
            return $original;
        }

        $candidate = $this->clean($candidate);

        // El sistema procesa expedientes en español. Exigir una etiqueta de
        // cabecera evita transformar por error un documento Unicode legítimo.
        if (! $this->isReadable($candidate)
            || $this->isReadable($original)
            || preg_match(self::HEADER_SIGNAL_PATTERN, $candidate) !== 1) {
            return $original;
        }

        return $candidate;
    }

    public function isReadable(string $text): bool
    {
        $text = $this->clean($text);

        if (mb_strlen($text) < 30 || ! mb_check_encoding($text, 'UTF-8')) {
            return false;
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($characters)) {
            return false;
        }

        $visible = 0;
        $wide = 0;
        $latinOrNumber = 0;

        foreach ($characters as $character) {
            if (preg_match('/\s/u', $character) === 1) {
                continue;
            }

            $visible++;
            $codepoint = mb_ord($character, 'UTF-8');

            if ($codepoint > 0x00FF) {
                $wide++;
            }

            if (preg_match('/[\p{Latin}\p{N}]/u', $character) === 1) {
                $latinOrNumber++;
            }
        }

        if ($visible === 0) {
            return false;
        }

        return ($wide / $visible) <= 0.25
            && ($latinOrNumber / $visible) >= 0.35;
    }

    private function looksLikePackedWindowsText(string $text): bool
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            return false;
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($characters)) {
            return false;
        }

        $visible = 0;
        $packed = 0;

        foreach ($characters as $character) {
            if (preg_match('/\s/u', $character) === 1) {
                continue;
            }

            $visible++;
            $codepoint = mb_ord($character, 'UTF-8');

            if ($codepoint > 0x00FF && $codepoint <= 0xFFFF) {
                $packed++;
            }
        }

        return $packed >= 4
            && $visible > 0
            && ($packed / $visible) >= 0.35;
    }

    private function decodePackedWindowsText(string $text): ?string
    {
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($characters)) {
            return null;
        }

        $bytes = '';

        foreach ($characters as $character) {
            // WordFirstPageExtractor añade estos saltos al unir los elementos
            // devueltos por PHPWord; no pertenecen al contenido del DOC.
            if ($character === "\n") {
                continue;
            }

            $codepoint = mb_ord($character, 'UTF-8');

            if ($codepoint <= 0x00FF) {
                $bytes .= chr($codepoint);
            } elseif ($codepoint <= 0xFFFF) {
                $bytes .= pack('v', $codepoint);
            } else {
                return null;
            }
        }

        $bytes = str_replace("\0", '', $bytes);

        return mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
    }

    private function clean(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            return trim($text);
        }

        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
