<?php

namespace Tests\Unit;

use App\Services\ExpedienteHeaderParser;
use App\Services\LegacyDocTextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegacyDocTextNormalizerTest extends TestCase
{
    public function test_it_recovers_windows_1252_text_packed_as_unicode_by_the_msdoc_reader(): void
    {
        $source = implode("\r", [
            'Especialista : YERALDO CAMPOS CORNEJO',
            'Expediente : 00061-2023-0-0401-JR-CI-07',
            'Materia : NULIDAD DE ACTO JURÍDICO',
            'Escrito : 03',
            'Sumilla : SOLICITO RESUELVA LA EXCEPCIÓN PLANTEADA',
        ]);
        $packed = $this->packLikeBrokenMsDocReader($source);
        $normalizer = new LegacyDocTextNormalizer;

        $normalized = $normalizer->normalize($packed);
        $parsed = app(ExpedienteHeaderParser::class)->parse($normalized, 'doc_text');

        $this->assertFalse($normalizer->isReadable($packed));
        $this->assertTrue($normalizer->isReadable($normalized));
        $this->assertStringContainsString("Especialista : YERALDO CAMPOS CORNEJO\n", $normalized);
        $this->assertSame('00061-2023-0-0401-JR-CI-07', $parsed['fields']['numero']);
        $this->assertSame('YERALDO CAMPOS CORNEJO', $parsed['fields']['especialista']);
        $this->assertSame('NULIDAD DE ACTO JURÍDICO', $parsed['fields']['materia']);
    }

    #[DataProvider('ordinaryTextProvider')]
    public function test_it_does_not_change_ordinary_unicode_text(string $text): void
    {
        $normalizer = new LegacyDocTextNormalizer;

        $this->assertSame($text, $normalizer->normalize($text));
    }

    /** @return iterable<string, array{string}> */
    public static function ordinaryTextProvider(): iterable
    {
        yield 'Spanish text' => ['EXPEDIENTE: 00123-2026-0-1801-JR-CI-01'];
        yield 'legitimate Unicode text' => ['案件番号：中国語の文書です。'];
    }

    private function packLikeBrokenMsDocReader(string $text): string
    {
        $bytes = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        $packed = '';
        $length = strlen($bytes);

        for ($offset = 0; $offset < $length; $offset += 2) {
            $low = ord($bytes[$offset]);
            $high = $offset + 1 < $length ? ord($bytes[$offset + 1]) : 0;
            $packed .= mb_chr($low | ($high << 8), 'UTF-8');

            // Simula los separadores artificiales añadidos entre elementos.
            if ($offset > 0 && $offset % 18 === 0) {
                $packed .= "\n";
            }
        }

        return $packed;
    }
}
