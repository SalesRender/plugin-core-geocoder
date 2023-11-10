<?php
/**
 * Created for plugin-core-geocoder
 * Date: 1/20/22 12:13 AM
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Geocoder\Components\Geocoder;

use SalesRender\Components\Address\Address;

interface GeocoderInterface
{

    /**
     * @param string $typing
     * @param Address $address
     * @return GeocoderResult[]
     */
    public function handle(string $typing, Address $address): array;

}