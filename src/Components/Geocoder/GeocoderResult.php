<?php
/**
 * Created for plugin-core-geocoder
 * Date: 1/20/22 12:16 AM
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Geocoder\Components\Geocoder;

use JsonSerializable;
use SalesRender\Components\Address\Address;

class GeocoderResult implements JsonSerializable
{

    private Address $address;
    private ?Timezone $timezone;

    public function __construct(Address $address, ?Timezone $timezone)
    {
        $this->address = $address;
        $this->timezone = $timezone;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getTimezone(): ?Timezone
    {
        return $this->timezone;
    }

    public function jsonSerialize(): array
    {
        return [
            'address' => $this->address,
            'timezone' => $this->timezone,
        ];
    }
}