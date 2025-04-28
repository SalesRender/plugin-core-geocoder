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
    private ?string $info;

    public function __construct(Address $address, ?Timezone $timezone, ?string $info = null)
    {
        $this->address = $address;
        $this->timezone = $timezone;
        $this->info = $info;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getTimezone(): ?Timezone
    {
        return $this->timezone;
    }

    public function getInfo(): ?string
    {
        return $this->info;
    }

    public function jsonSerialize(): array
    {
        return [
            'address' => $this->address,
            'timezone' => $this->timezone,
            'info' => $this->info,
        ];
    }
}