<?php

namespace App\Services;

use App\Exceptions\DocumentConversionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class LibreOfficeService
{
    public function isAvailable(): bool
    {
        return $this->resolveBinaryPath() !== null;
    }

    public function convertToPdf(string $binary, string $extension): string
    {
        $sourceExtension = strtolower(ltrim(trim($extension), '.'));

        if (! in_array($sourceExtension, ['doc', 'docx'], true)) {
            throw new DocumentConversionException('LibreOffice solo admite documentos DOC o DOCX en esta conversión.');
        }

        return $this->convert($binary, $sourceExtension, 'pdf');
    }

    public function convertDocToDocx(string $binary): string
    {
        return $this->convert($binary, 'doc', 'docx');
    }

    private function convert(string $binary, string $sourceExtension, string $targetExtension): string
    {
        if ($binary === '') {
            throw new DocumentConversionException('El contenido del documento está vacío.');
        }

        $executable = $this->resolveBinaryPath();

        if ($executable === null) {
            throw new DocumentConversionException(
                'LibreOffice no está configurado o disponible para procesar este documento.'
            );
        }

        $workingDirectory = storage_path('app/temp/libreoffice/'.Str::uuid());
        $outputDirectory = $workingDirectory.'/output';
        $profileDirectory = $workingDirectory.'/profile';
        $inputPath = $workingDirectory.'/document.'.$sourceExtension;

        try {
            File::ensureDirectoryExists($outputDirectory, 0755, true);
            File::ensureDirectoryExists($profileDirectory, 0755, true);

            $writtenBytes = file_put_contents($inputPath, $binary);

            if ($writtenBytes === false || $writtenBytes !== strlen($binary)) {
                throw new DocumentConversionException('No se pudo escribir el documento temporal completo.');
            }

            $conversionFilter = $targetExtension === 'docx'
                ? 'docx:Office Open XML Text'
                : 'pdf';

            $process = $this->makeProcess([
                $executable,
                '-env:UserInstallation='.$this->fileUri($profileDirectory),
                '--headless',
                '--nologo',
                '--nodefault',
                '--nolockcheck',
                '--nofirststartwizard',
                '--norestore',
                '--convert-to',
                $conversionFilter,
                '--outdir',
                $outputDirectory,
                $inputPath,
            ], $workingDirectory);
            $process->setTimeout($this->timeout());
            $process->run();

            if (! $process->isSuccessful()) {
                throw new DocumentConversionException(
                    sprintf('LibreOffice no pudo convertir el archivo %s.', strtoupper($sourceExtension))
                );
            }

            $outputPath = $outputDirectory.'/document.'.$targetExtension;
            $converted = is_file($outputPath) ? file_get_contents($outputPath) : false;

            if (! is_string($converted) || $converted === '') {
                throw new DocumentConversionException('LibreOffice no generó el documento convertido.');
            }

            $this->assertOutputSignature($converted, $targetExtension);

            return $converted;
        } catch (DocumentConversionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DocumentConversionException(
                sprintf(
                    'No se pudo ejecutar LibreOffice para convertir el archivo %s. Verifique LIBREOFFICE_BINARY, sus permisos y que proc_open esté habilitado.',
                    strtoupper($sourceExtension)
                ),
                0,
                $exception
            );
        } finally {
            if (is_dir($workingDirectory)) {
                File::deleteDirectory($workingDirectory);
            }
        }
    }

    /**
     * @param  list<string>  $command
     */
    protected function makeProcess(array $command, string $workingDirectory): Process
    {
        return new Process($command, $workingDirectory);
    }

    private function resolveBinaryPath(): ?string
    {
        $configuredBinary = trim((string) config('services.libreoffice.binary', ''));

        if ($configuredBinary !== '') {
            // Do not inspect an explicitly configured system path with
            // is_file(): Hestia's open_basedir may hide /usr/bin from PHP
            // while Symfony Process can still execute the binary.
            return $configuredBinary;
        }

        $directories = array_filter(explode(
            PATH_SEPARATOR,
            (string) (getenv('PATH') ?: getenv('Path') ?: '')
        ));

        if (DIRECTORY_SEPARATOR === '/') {
            $directories = array_merge($directories, ['/usr/bin', '/usr/local/bin', '/snap/bin']);
        }

        $suffixes = DIRECTORY_SEPARATOR === '\\'
            ? (explode(PATH_SEPARATOR, (string) (getenv('PATHEXT') ?: '.EXE;.BAT;.CMD')) ?: [''])
            : [''];

        foreach (array_unique($directories) as $directory) {
            foreach (['libreoffice', 'soffice'] as $candidate) {
                foreach ($suffixes as $suffix) {
                    $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$candidate.$suffix;

                    if (@is_file($path) && (DIRECTORY_SEPARATOR === '\\' || @is_executable($path))) {
                        return $path;
                    }
                }
            }
        }

        return null;
    }

    private function timeout(): float
    {
        return max(1, (float) config('services.libreoffice.timeout', 120));
    }

    private function fileUri(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $encoded = implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            explode('/', $normalized)
        ));
        $encoded = preg_replace('/^([A-Za-z])%3A\//', '$1:/', $encoded) ?? $encoded;

        return str_starts_with($normalized, '/') ? 'file://'.$encoded : 'file:///'.$encoded;
    }

    private function assertOutputSignature(string $binary, string $extension): void
    {
        $valid = match ($extension) {
            'pdf' => str_starts_with($binary, '%PDF-'),
            'docx' => str_starts_with($binary, "PK\x03\x04"),
            default => false,
        };

        if (! $valid) {
            throw new DocumentConversionException(
                sprintf('LibreOffice generó un archivo %s inválido.', strtoupper($extension))
            );
        }
    }
}
