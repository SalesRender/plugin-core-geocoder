<?php
/**
 * Created for plugin-core-geocoder
 * Date: 1/20/22 12:13 AM
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Geocoder\Components\Geocoder;

use Leadvertex\Components\Address\Address;

interface GeocoderInterface
{

    public function handle(string $typing, Address $address): GeocoderResult;

}