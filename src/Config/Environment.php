<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Config;

/**
 * Ambiente de Finkok contra el que se consume el Web Service.
 *
 * Finkok expone dos entornos totalmente independientes: cada uno tiene su propio
 * panel, sus propias credenciales y su propio conjunto de RFC registrados. Usar
 * credenciales de producción contra la URL de demostración (o viceversa) produce
 * el error 300 «El usuario o contraseña son inválidos».
 */
enum Environment: string
{
    /** Entorno de pruebas. No soporta cargas altas ni pruebas de estrés. */
    case Demo = 'demo';

    /** Entorno productivo. Los comprobantes timbrados aquí son fiscales y reales. */
    case Production = 'production';

    /**
     * Alias en español para comodidad de los consumidores de la librería.
     */
    public static function fromSpanish(string $value): self
    {
        return match (strtolower(trim($value))) {
            'demo', 'demostracion', 'demostración', 'pruebas', 'sandbox', 'test' => self::Demo,
            'prod', 'produccion', 'producción', 'production', 'live' => self::Production,
            default => throw new \InvalidArgumentException(sprintf(
                'Ambiente desconocido "%s". Valores válidos: "demo", "production".',
                $value,
            )),
        };
    }

    public function isDemo(): bool
    {
        return self::Demo === $this;
    }

    public function isProduction(): bool
    {
        return self::Production === $this;
    }

    /**
     * Etiqueta legible para mensajes de error y logs.
     */
    public function label(): string
    {
        return match ($this) {
            self::Demo => 'DEMO',
            self::Production => 'PRODUCCIÓN',
        };
    }
}
