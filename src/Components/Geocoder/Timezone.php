<?php
/**
 * Created for plugin-core-geocoder
 * Date: 1/20/22 12:19 AM
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Geocoder\Components\Geocoder;

use DateTimeZone;
use JsonSerializable;
use Leadvertex\Plugin\Core\Geocoder\Exceptions\InvalidTimezoneException;

class Timezone implements JsonSerializable
{

    private ?string $name = null;
    private ?string $offset = null;

    /**
     * @param string $timezoneOrOffset
     * @throws InvalidTimezoneException
     */
    public function __construct(string $timezoneOrOffset)
    {
        if (preg_match('~^[+\-]\d{2}:\d{2}$~', $timezoneOrOffset)) {
            $this->offset = $timezoneOrOffset;
        } elseif (in_array($timezoneOrOffset, DateTimeZone::listIdentifiers())) {
            $this->name = $timezoneOrOffset;
        } else {
            throw new InvalidTimezoneException("Timezone name or offset value '{$timezoneOrOffset}' is invalid");
        }
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getOffset(): ?string
    {
        return $this->offset;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'offset' => $this->offset,
        ];
    }
}