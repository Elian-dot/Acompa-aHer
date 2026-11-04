<?php

namespace App\Support;

/**
 * Construye geometrías EWKT con SRID 4326 a partir de coordenadas validadas.
 */
final class Geometry
{
    public static function point(float $latitude, float $longitude): string
    {
        return sprintf('SRID=4326;POINT(%.7F %.7F)', $longitude, $latitude);
    }

    /**
     * @param  list<array{0: float|int, 1: float|int}>  $coordinates  pares [longitud, latitud]
     */
    public static function line(array $coordinates): string
    {
        return 'SRID=4326;LINESTRING('.implode(',', array_map(
            fn (array $pair): string => sprintf('%.7F %.7F', $pair[0], $pair[1]),
            $coordinates,
        )).')';
    }
}
