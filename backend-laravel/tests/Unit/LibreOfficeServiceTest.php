<?php

namespace Tests\Unit;

use App\Exceptions\DocumentConversionException;
use App\Services\LibreOfficeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Mockery;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LibreOfficeServiceTest extends TestCase
{
    public function test_it_trusts_an_explicit_binary_without_inspecting_the_system_path(): void
    {
        config()->set('services.libreoffice.binary', 'binary-that-does-not-exist-anywhere');

        // An explicitly configured binary is intentionally trusted because
        // Hestia open_basedir may prevent PHP from inspecting /usr/bin.
        $this->assertTrue(app(LibreOfficeService::class)->isAvailable());
    }

    public function test_it_uses_array_arguments_a_unique_profile_and_cleans_the_workspace(): void
    {
        config()->set('services.libreoffice.binary', '/usr/bin/libreoffice-test');
        config()->set('services.libreoffice.timeout', 37);
        $service = new FakeLibreOfficeService(Pdf::loadHTML('<p>Documento convertido</p>')->output());

        $firstPdf = $service->convertToPdf('contenido Word', 'doc');
        $secondPdf = $service->convertToPdf('otro contenido Word', 'doc');

        $this->assertStringStartsWith('%PDF-', $firstPdf);
        $this->assertStringStartsWith('%PDF-', $secondPdf);
        $this->assertCount(2, $service->commands);
        $command = $service->commands[0];
        $this->assertIsArray($command);
        $this->assertSame('/usr/bin/libreoffice-test', $command[0]);
        $this->assertContains('--headless', $command);
        $this->assertContains('--norestore', $command);
        $this->assertContains('--convert-to', $command);
        $this->assertContains('pdf', $command);
        $profileArgument = collect($command)->first(
            fn (string $argument): bool => str_starts_with($argument, '-env:UserInstallation=file:///')
        );
        $this->assertNotNull($profileArgument);
        $secondProfileArgument = collect($service->commands[1])->first(
            fn (string $argument): bool => str_starts_with($argument, '-env:UserInstallation=file:///')
        );
        $this->assertNotSame($profileArgument, $secondProfileArgument);
        $this->assertNotSame($service->workingDirectories[0], $service->workingDirectories[1]);

        foreach ($service->workingDirectories as $workingDirectory) {
            $this->assertStringStartsWith(storage_path('app/temp'), $workingDirectory);
            $this->assertDirectoryDoesNotExist($workingDirectory);
        }
    }

    public function test_it_converts_doc_to_docx_and_verifies_the_result(): void
    {
        config()->set('services.libreoffice.binary', '/usr/bin/libreoffice-test');
        $service = new FakeLibreOfficeService("PK\x03\x04contenido docx");

        $docx = $service->convertDocToDocx('contenido DOC');

        $this->assertStringStartsWith("PK\x03\x04", $docx);
        $this->assertContains('docx:Office Open XML Text', $service->commands[0]);
        $this->assertDirectoryDoesNotExist($service->workingDirectories[0]);
    }

    public function test_it_cleans_the_workspace_when_the_process_fails(): void
    {
        config()->set('services.libreoffice.binary', '/usr/bin/libreoffice-test');
        $service = new FakeLibreOfficeService(null, false);

        try {
            $service->convertToPdf('contenido Word', 'doc');
            $this->fail('La conversión debió fallar.');
        } catch (DocumentConversionException $exception) {
            $this->assertStringContainsString('LibreOffice no pudo convertir', $exception->getMessage());
            $this->assertDirectoryDoesNotExist($service->workingDirectories[0]);
        }
    }

    public function test_it_reports_process_start_failures_and_cleans_the_workspace(): void
    {
        config()->set('services.libreoffice.binary', '/usr/bin/libreoffice-test');
        $service = new FakeLibreOfficeService(
            null,
            true,
            new RuntimeException('proc_open está deshabilitado')
        );

        try {
            $service->convertToPdf('contenido Word', 'doc');
            $this->fail('La ejecución debió fallar.');
        } catch (DocumentConversionException $exception) {
            $this->assertStringContainsString('proc_open', $exception->getMessage());
            $this->assertSame('proc_open está deshabilitado', $exception->getPrevious()?->getMessage());
            $this->assertDirectoryDoesNotExist($service->workingDirectories[0]);
        }
    }
}

class FakeLibreOfficeService extends LibreOfficeService
{
    /** @var list<list<string>> */
    public array $commands = [];

    /** @var list<string> */
    public array $workingDirectories = [];

    public function __construct(
        private readonly ?string $output,
        private readonly bool $successful = true,
        private readonly ?RuntimeException $runException = null
    ) {}

    protected function makeProcess(array $command, string $workingDirectory): Process
    {
        $this->commands[] = $command;
        $this->workingDirectories[] = $workingDirectory;

        if ($this->output !== null) {
            $outputDirectoryIndex = array_search('--outdir', $command, true);
            $outputDirectory = $command[$outputDirectoryIndex + 1];
            $filterIndex = array_search('--convert-to', $command, true);
            $extension = str_starts_with($command[$filterIndex + 1], 'docx') ? 'docx' : 'pdf';
            file_put_contents($outputDirectory.'/document.'.$extension, $this->output);
        }

        $process = Mockery::mock(Process::class);
        $process->shouldReceive('setTimeout')
            ->once()
            ->with((float) config('services.libreoffice.timeout', 120))
            ->andReturnSelf();
        if ($this->runException !== null) {
            $process->shouldReceive('run')->once()->andThrow($this->runException);
        } else {
            $process->shouldReceive('run')->once()->andReturn($this->successful ? 0 : 1);
            $process->shouldReceive('isSuccessful')->once()->andReturn($this->successful);
        }

        return $process;
    }
}
