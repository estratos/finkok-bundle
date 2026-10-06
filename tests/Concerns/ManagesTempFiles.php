<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Concerns;

/**
 * Archivos temporales para las pruebas que necesitan rutas reales (CSD, CFDI en
 * disco, etc.).
 */
trait ManagesTempFiles
{
    /** @var list<string> */
    private array $temporaryPaths = [];

    private string $temporaryDirectory = '';

    protected function temporaryDirectory(): string
    {
        if ('' === $this->temporaryDirectory) {
            $this->temporaryDirectory = sys_get_temp_dir().'/finkok-cfdi-bundle-'.bin2hex(random_bytes(6));

            if (!is_dir($this->temporaryDirectory) && !@mkdir($this->temporaryDirectory, 0o700, true) && !is_dir($this->temporaryDirectory)) {
                throw new \RuntimeException(sprintf('No fue posible crear el directorio temporal "%s".', $this->temporaryDirectory));
            }
        }

        return $this->temporaryDirectory;
    }

    /**
     * Crea un archivo temporal y devuelve su ruta.
     */
    protected function temporaryFile(string $contents, string $name = 'archivo.bin'): string
    {
        $path = $this->temporaryDirectory().'/'.bin2hex(random_bytes(4)).'-'.$name;
        file_put_contents($path, $contents);
        $this->temporaryPaths[] = $path;

        return $path;
    }

    protected function cleanupTemporaryFiles(): void
    {
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->temporaryPaths = [];

        if ('' !== $this->temporaryDirectory && is_dir($this->temporaryDirectory)) {
            @rmdir($this->temporaryDirectory);
            $this->temporaryDirectory = '';
        }
    }
}
