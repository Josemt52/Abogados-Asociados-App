<?php

namespace Tests\Unit;

use App\Services\ExpedienteHeaderParser;
use PHPUnit\Framework\TestCase;

class ExpedienteHeaderParserTest extends TestCase
{
    public function test_it_extracts_the_legal_header_and_preserves_multiple_parties(): void
    {
        $parser = new ExpedienteHeaderParser;
        $result = $parser->parse(implode("\n", [
            'EXPEDIENTE : 02536-2024-0-1801-JR-CI-01',
            'MATERIA : OBLIGACIÓN DE DAR SUMA DE DINERO',
            'JUZGADO : PRIMER JUZGADO CIVIL',
            'ESPECIALISTA LEGAL : ANA PÉREZ',
            'TERCEROS : EMPRESA UNO; EMPRESA DOS',
            'DEMANDADO : JUAN UNO',
            'DEMANDADO : JUAN DOS',
            'DEMANDANTE : MARÍA TRES',
        ]));

        $this->assertSame('02536-2024-0-1801-JR-CI-01', $result['fields']['numero']);
        $this->assertSame('OBLIGACIÓN DE DAR SUMA DE DINERO', $result['fields']['materia']);
        $this->assertSame(['EMPRESA UNO', 'EMPRESA DOS'], $result['fields']['tercero']);
        $this->assertSame(['JUAN UNO', 'JUAN DOS'], $result['fields']['demandado']);
        $this->assertSame(['MARÍA TRES'], $result['fields']['demandante']);
        $this->assertGreaterThanOrEqual(0.9, $result['confidence']);
    }

    public function test_it_reads_values_from_the_line_after_a_table_label(): void
    {
        $parser = new ExpedienteHeaderParser;
        $result = $parser->parse(implode("\n", [
            'EXPEDIENTE',
            '00123-2026-0-1801-JR-LA-01',
            'MATERIA',
            'LABORAL',
            'JUZGADO',
            'SEGUNDO JUZGADO DE TRABAJO',
            'ESPECIALISTA',
            'LUIS RAMOS',
            'DEMANDADO',
            'EMPRESA DEMANDADA',
            'DEMANDANTE',
            'PERSONA DEMANDANTE',
        ]));

        $this->assertSame('00123-2026-0-1801-JR-LA-01', $result['fields']['numero']);
        $this->assertSame('LABORAL', $result['fields']['materia']);
        $this->assertSame(['EMPRESA DEMANDADA'], $result['fields']['demandado']);
        $this->assertGreaterThanOrEqual(0.65, $result['confidence']);
    }

    public function test_missing_case_number_never_reaches_the_registration_threshold(): void
    {
        $result = (new ExpedienteHeaderParser)->parse("MATERIA: CIVIL\nJUZGADO: JUZGADO CIVIL");

        $this->assertNull($result['fields']['numero']);
        $this->assertLessThan(0.65, $result['confidence']);
    }

    public function test_low_ocr_confidence_keeps_an_otherwise_complete_header_below_the_threshold(): void
    {
        $result = (new ExpedienteHeaderParser)->parse(implode("\n", [
            'EXPEDIENTE: 00123-2026-0-1801-JR-CI-01',
            'MATERIA: CIVIL',
            'JUZGADO: PRIMER JUZGADO CIVIL',
            'ESPECIALISTA: ANA PÉREZ',
            'DEMANDADO: JUAN DEMANDADO',
            'DEMANDANTE: MARÍA DEMANDANTE',
        ]), 'docx_ocr', 0.25);

        $this->assertSame(0.25, $result['confidence']);
        $this->assertLessThan(0.65, $result['confidence']);
    }

    public function test_it_collects_party_names_on_continuation_lines_without_consuming_the_document_body(): void
    {
        $result = (new ExpedienteHeaderParser)->parse(implode("\n", [
            'N° DE EXPEDIENTE: 00999-2026-0-1801-JR-CI-04',
            'MATERIA: CIVIL',
            'JUZGADO: JUZGADO CIVIL',
            'ESPECIALISTA: ANA PÉREZ',
            'DEMANDADO(S):',
            'PERSONA UNO',
            'PERSONA DOS',
            'DEMANDANTE(S):',
            'PERSONA TRES',
            'RESOLUCIÓN NÚMERO UNO',
            'Este texto ya pertenece al cuerpo.',
        ]));

        $this->assertSame(['PERSONA UNO', 'PERSONA DOS'], $result['fields']['demandado']);
        $this->assertSame(['PERSONA TRES'], $result['fields']['demandante']);
    }
}
