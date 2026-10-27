<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use JsonException;
use Symfony\UX\Map\Bridge\Leaflet\LeafletOptions;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Point;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'MunicipalityGeographyMap', template: 'components/MunicipalityGeographyMap.html.twig')]
final class MunicipalityGeographyMap
{
    public MunicipalityEntity $municipality;

    public function getMap(): ?Map
    {
        $center = $this->shapeCenter() ?? $this->centerPoint();
        if (null === $center) {
            return null;
        }

        $map = (new Map())
            ->center($center)
            ->zoom(9.0)
            ->minZoom(4.0)
            ->maxZoom(18.0)
            ->options(new LeafletOptions());

        return $map;
    }

    public function getGeoShapeJson(): string
    {
        try {
            return json_encode($this->municipality->getGeoShape() ?? [], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '[]';
        }
    }

    private function centerPoint(): ?Point
    {
        $latitude = $this->municipality->getCenterLatitude();
        $longitude = $this->municipality->getCenterLongitude();
        if (null === $latitude || null === $longitude || -90 > $latitude || 90 < $latitude || -180 > $longitude || 180 < $longitude) {
            return null;
        }

        return new Point($latitude, $longitude);
    }

    private function shapeCenter(): ?Point
    {
        $geoShape = $this->municipality->getGeoShape();
        if (!is_array($geoShape)) {
            return null;
        }

        $coordinates = $geoShape['coordinates'] ?? null;
        if (!is_array($coordinates)) {
            return null;
        }

        $points = [];
        $this->collectPoints($coordinates, $points);
        if ([] === $points) {
            return null;
        }

        $longitudeAverage = array_sum(array_column($points, 0)) / count($points);
        $latitudeAverage = array_sum(array_column($points, 1)) / count($points);

        return new Point($latitudeAverage, $longitudeAverage);
    }

    /**
     * @param array<mixed>              $coordinates
     * @param list<array{float, float}> $points
     */
    private function collectPoints(array $coordinates, array &$points): void
    {
        if (isset($coordinates[0], $coordinates[1]) && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
            $longitude = (float) $coordinates[0];
            $latitude = (float) $coordinates[1];
            if (-90 <= $latitude && 90 >= $latitude && -180 <= $longitude && 180 >= $longitude) {
                $points[] = [$longitude, $latitude];
            }

            return;
        }

        foreach ($coordinates as $coordinateGroup) {
            if (is_array($coordinateGroup)) {
                $this->collectPoints($coordinateGroup, $points);
            }
        }
    }
}
