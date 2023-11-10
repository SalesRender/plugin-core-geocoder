<?php
/**
 * Created for plugin-core-geocoder
 * Date: 1/20/22 12:10 AM
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Geocoder\Components\Geocoder;

use SalesRender\Plugin\Core\Geocoder\Exceptions\GeocoderContainerException;

class GeocoderContainer
{

    private static GeocoderInterface $geocoder;

    public static function config(GeocoderInterface $geocoder): void
    {
        self::$geocoder = $geocoder;
    }

    /**
     * @return GeocoderInterface
     * @throws GeocoderContainerException
     */
    public static function getHandler(): GeocoderInterface
    {
        if (!isset(self::$geocoder)) {
            throw new GeocoderContainerException('Geocoder was not configured', 100);
        }

        return self::$geocoder;
    }

}