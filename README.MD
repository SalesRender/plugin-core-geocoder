# Plugin Core Geocoder

> Type-specific core framework for SalesRender **GEOCODER** plugins

## Overview

`salesrender/plugin-core-geocoder` is a specialized core library that extends the base `salesrender/plugin-core` to build **Geocoder-type** plugins. Geocoder plugins resolve addresses to geographic coordinates, timezones, and structured address data.

This core provides:

- A `GeocoderInterface` that the developer must implement with actual geocoding logic
- A `GeocoderContainer` for registering the geocoder implementation
- An HTTP endpoint (`POST /protected/geocoder/handle`) for processing geocoding requests
- A `GeocoderResult` value object for returning structured results (address, timezone, info)
- A `Timezone` class supporting both named timezones and UTC offsets
- A `GeocoderAction` that parses requests and invokes the configured geocoder

## Installation

```bash
composer require salesrender/plugin-core-geocoder
```

### Requirements

- PHP >= 7.4
- ext-json
- `salesrender/plugin-core` ^0.4.0 (installed automatically)
- `salesrender/component-address` ^1.0.0 (installed automatically)
- `adbario/php-dot-notation` ^2.2 (installed automatically)

## Architecture

### How This Core Extends plugin-core

`plugin-core-geocoder` overrides both factory classes from the base `plugin-core`:

**`WebAppFactory`** (extends `\SalesRender\Plugin\Core\Factories\WebAppFactory`):
- Adds CORS support
- Registers the `GeocoderAction` at `POST /protected/geocoder/handle` with protected middleware

**`ConsoleAppFactory`** (extends `\SalesRender\Plugin\Core\Factories\ConsoleAppFactory`):
- Inherits all base commands without adding new ones (geocoding is synchronous, no queue needed)

### Request Flow

```
SalesRender CRM                          Geocoder Plugin                     External API
      |                                       |                                    |
      |-- POST /protected/geocoder/handle --->|                                    |
      |                                       |-- GeocoderInterface::handle() ---->|
      |                                       |<-- GeocoderResult[] --------------|
      |<-- JSON array of GeocoderResult ------|                                    |
```

## Getting Started: Creating a Geocoder Plugin

### Step 1: Project Setup

Create a new project and add the dependency:

```bash
mkdir my-geocoder-plugin && cd my-geocoder-plugin
composer init --name="myvendor/plugin-geocoder-myservice" --type="project"
composer require salesrender/plugin-core-geocoder
```

Create the directory structure:

```
my-geocoder-plugin/
  bootstrap.php
  console.php
  composer.json
  example.env
  public/
    .htaccess
    index.php
    icon.png
  src/
    Geocoder.php
    SettingsForm.php
  db/
  runtime/
```

### Step 2: Bootstrap Configuration

Create `bootstrap.php` in the project root. This file configures all plugin components:

```php
<?php

use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Form\Autocomplete\AutocompleteRegistry;
use SalesRender\Plugin\Components\Info\Developer;
use SalesRender\Plugin\Components\Info\Info;
use SalesRender\Plugin\Components\Info\PluginType;
use SalesRender\Plugin\Components\Settings\Settings;
use SalesRender\Plugin\Components\Translations\Translator;
use SalesRender\Plugin\Core\Geocoder\Components\Geocoder\GeocoderContainer;
use SalesRender\Plugin\Instance\Geocoder\Geocoder;
use SalesRender\Plugin\Instance\Geocoder\SettingsForm;
use Medoo\Medoo;
use XAKEPEHOK\Path\Path;

# 0. Configure environment variable in .env file, that placed into root of app

# 1. Configure DB (for SQLite *.db file and parent directory should be writable)
Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => Path::root()->down('db/database.db')
]));

# 2. Set plugin default language
Translator::config('ru_RU');

# 3. Configure info about plugin
Info::config(
    new PluginType(PluginType::GEOCODER),
    fn() => Translator::get('info', 'Plugin name'),
    fn() => Translator::get('info', 'Plugin markdown description'),
    [
        'countries' => ['RU'],
    ],
    new Developer(
        'Your (company) name',
        'support.for.plugin@example.com',
        'example.com',
    )
);

# 4. Configure settings form
Settings::setForm(fn() => new SettingsForm());

# 5. Configure form autocompletes (or remove this block if not used)
AutocompleteRegistry::config(function (string $name) {
    return null;
});

# 6. Configure GeocoderContainer with your geocoder implementation
GeocoderContainer::config(new Geocoder());
```

**Key geocoder-specific configuration points:**

- **`PluginType::GEOCODER`** -- identifies this plugin as a Geocoder type
- **`countries`** -- array of ISO 3166-1 alpha-2 country codes that this geocoder supports (e.g., `['RU']`, `['RU', 'KZ']`)
- **`GeocoderContainer::config()`** -- registers your `GeocoderInterface` implementation

### Step 3: Implement GeocoderInterface

This is the core of your geocoder plugin. Create a class that implements `GeocoderInterface`:

```php
<?php

namespace SalesRender\Plugin\Instance\Geocoder;

use SalesRender\Components\Address\Address;
use SalesRender\Components\Address\Location;
use SalesRender\Plugin\Core\Geocoder\Components\Geocoder\GeocoderInterface;
use SalesRender\Plugin\Core\Geocoder\Components\Geocoder\GeocoderResult;
use SalesRender\Plugin\Core\Geocoder\Components\Geocoder\Timezone;

class Geocoder implements GeocoderInterface
{

    /**
     * @param string $typing - free-form text input by the user
     * @param Address $address - structured address data
     * @return GeocoderResult[]
     */
    public function handle(string $typing, Address $address): array
    {
        // Option 1: If $typing is not empty, use it as free-form search
        if (!empty(trim($typing))) {
            // Call your geocoding API with the free-form text
            // Parse the response into GeocoderResult objects
            $resolvedAddress = new Address(
                'Region',           // region
                'City',             // city
                'Street 1',         // address_1
                '',                 // address_2
                '123456',           // postcode
                'RU',               // countryCode
                new Location(55.7558, 37.6173)  // latitude, longitude
            );

            return [
                new GeocoderResult(
                    $resolvedAddress,
                    new Timezone('Europe/Moscow'),
                    'Additional info about this result'
                ),
            ];
        }

        // Option 2: If $typing is empty, resolve/enhance the structured $address
        $handledAddress = new Address(
            strtoupper($address->getRegion()),
            strtoupper($address->getCity()),
            strtoupper($address->getAddress_1()),
            strtoupper($address->getAddress_2()),
            strtoupper($address->getPostcode()),
            $address->getCountryCode(),
            $address->getLocation()
        );

        $timezone = null;
        if ($address->getCountryCode() && !empty($address->getRegion())) {
            $timezone = new Timezone('UTC+03:00');
        }

        return [new GeocoderResult($handledAddress, $timezone)];
    }
}
```

The `handle()` method receives two parameters:
- **`$typing`** -- free-form text typed by the user (for autocomplete-style address search)
- **`$address`** -- a structured `Address` object with fields like region, city, address_1, address_2, postcode, countryCode, and location

It must return an array of `GeocoderResult` objects. Each result contains a resolved `Address`, an optional `Timezone`, and an optional info string.

### Step 4: Create Web Entry Point

Create `public/index.php`:

```php
<?php
use SalesRender\Plugin\Core\Geocoder\Factories\WebAppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$factory = new WebAppFactory();
$application = $factory->build();
$application->run();
```

Create `public/.htaccess`:

```apache
RewriteEngine On
RewriteRule ^output - [L]
RewriteRule ^uploaded - [L]
RewriteCond %{REQUEST_FILENAME}  -f [OR]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php [L,QSA]
```

### Step 5: Create Console Entry Point

Create `console.php`:

```php
#!/usr/bin/env php
<?php

use SalesRender\Plugin\Core\Geocoder\Factories\ConsoleAppFactory;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

$factory = new ConsoleAppFactory();
$application = $factory->build();
$application->run();
```

### Step 6: Create Settings Form

Create `src/SettingsForm.php`:

```php
<?php

namespace SalesRender\Plugin\Instance\Geocoder;

use SalesRender\Plugin\Components\Form\FieldDefinitions\FieldDefinition;
use SalesRender\Plugin\Components\Form\FieldDefinitions\PasswordDefinition;
use SalesRender\Plugin\Components\Form\FieldDefinitions\StringDefinition;
use SalesRender\Plugin\Components\Form\FieldGroup;
use SalesRender\Plugin\Components\Form\Form;
use SalesRender\Plugin\Components\Form\FormData;
use SalesRender\Plugin\Components\Translations\Translator;

class SettingsForm extends Form
{

    public function __construct()
    {
        $nonNull = function ($value, FieldDefinition $definition, FormData $data) {
            $errors = [];
            if (is_null($value)) {
                $errors[] = Translator::get('settings', 'Field can not be empty');
            }
            return $errors;
        };
        parent::__construct(
            Translator::get('settings', 'Settings'),
            null,
            [
                'main' => new FieldGroup(
                    Translator::get('settings', 'Main settings'),
                    null,
                    [
                        'email' => new StringDefinition(
                            Translator::get('settings', 'Email'),
                            null,
                            $nonNull
                        ),
                        'password' => new PasswordDefinition(
                            Translator::get('settings', 'Password'),
                            null,
                            $nonNull
                        ),
                    ]
                ),
            ],
            Translator::get('settings', 'Save'),
        );
    }
}
```

### Step 7: Create .env

Create `example.env` (copy to `.env` for local development):

```env
LV_PLUGIN_DEBUG=1
LV_PLUGIN_PHP_BINARY=php
LV_PLUGIN_QUEUE_LIMIT=1
LV_PLUGIN_SELF_URI=http://plugin-example/
LV_PLUGIN_COMPONENT_REGISTRATION_SCHEME=https
LV_PLUGIN_COMPONENT_REGISTRATION_HOSTNAME=lv-app
```

### Step 8: Initialize & Deploy

```bash
# Install dependencies
composer install

# Create database tables
php console.php db:create

# Start cron (for base tasks like special requests)
php console.php cron
```

## HTTP Routes

Routes added by `\SalesRender\Plugin\Core\Geocoder\Factories\WebAppFactory`:

| Method | Path | Description | Source |
|---|---|---|---|
| `POST` | `/protected/geocoder/handle` | Receives geocoding requests. Parses the request body into `typing` (string) and `address` (Address), invokes `GeocoderInterface::handle()`, and returns a JSON array of `GeocoderResult` objects. Protected by middleware. | `GeocoderAction` |

Additionally, all base `plugin-core` routes are inherited:

| Method | Path | Description |
|---|---|---|
| `GET` | `/info` | Plugin information |
| `PUT` | `/registration` | Plugin registration |
| `GET` | `/protected/forms/settings` | Settings form definition |
| `PUT` | `/protected/data/settings` | Save settings |
| `GET` | `/protected/data/settings` | Get settings data |
| `GET` | `/protected/autocomplete/{name}` | Autocomplete handler |
| `GET` | `/robots.txt` | Robots.txt |

### Request Format

`POST /protected/geocoder/handle` expects the following JSON body:

```json
{
    "typing": "Moscow Red Square",
    "address": {
        "region": "",
        "city": "",
        "address_1": "",
        "address_2": "",
        "building": "",
        "apartment": "",
        "postcode": "",
        "countryCode": "RU",
        "location": {
            "latitude": null,
            "longitude": null
        }
    }
}
```

### Response Format

Returns a JSON array of geocoder results:

```json
[
    {
        "address": {
            "region": "Moscow Oblast",
            "city": "Moscow",
            "address_1": "Red Square, 1",
            "address_2": "",
            "postcode": "109012",
            "countryCode": "RU",
            "location": {
                "latitude": 55.7539,
                "longitude": 37.6208
            }
        },
        "timezone": {
            "name": "Europe/Moscow",
            "offset": null
        },
        "info": "Additional information"
    }
]
```

### Error Responses

| Code | Description |
|---|---|
| `400` | Invalid address data in the request |
| `417` | `GeocoderHandleException` -- geocoder-specific error during processing |
| `501` | Geocoder not configured (GeocoderContainer has no handler) |

## CLI Commands

The geocoder core does not add any new CLI commands beyond those inherited from the base `plugin-core`:

| Command | Description |
|---|---|
| `db:create` | Create database tables |
| `db:clean` | Clean database tables |
| `specialRequest:queue` | Process special request queue |
| `specialRequest:handle` | Handle a special request |
| `cron` | Run all scheduled cron tasks |
| `lang:add` | Add a translation language |
| `lang:update` | Update translations |
| `directory:clean` | Clean temporary directories |

## Key Classes & Interfaces

### `GeocoderInterface`

**Namespace:** `SalesRender\Plugin\Core\Geocoder\Components\Geocoder\GeocoderInterface`

The primary interface that every geocoder plugin must implement:

```php
use SalesRender\Components\Address\Address;

interface GeocoderInterface
{
    /**
     * @param string $typing - free-form text input
     * @param Address $address - structured address data
     * @return GeocoderResult[]
     */
    public function handle(string $typing, Address $address): array;
}
```

### `GeocoderResult`

**Namespace:** `SalesRender\Plugin\Core\Geocoder\Components\Geocoder\GeocoderResult`

A value object that represents a single geocoding result. Implements `JsonSerializable`.

| Method | Return Type | Description |
|---|---|---|
| `__construct(Address $address, ?Timezone $timezone, ?string $info = null)` | | Create a result with address, optional timezone, and optional info |
| `getAddress()` | `Address` | The resolved/enhanced address |
| `getTimezone()` | `?Timezone` | The resolved timezone (if available) |
| `getInfo()` | `?string` | Additional informational text about this result |

### `GeocoderContainer`

**Namespace:** `SalesRender\Plugin\Core\Geocoder\Components\Geocoder\GeocoderContainer`

Static container for registering and retrieving the `GeocoderInterface` implementation.

| Method | Return Type | Description |
|---|---|---|
| `config(GeocoderInterface $geocoder)` | `void` | Register the geocoder implementation |
| `getHandler()` | `GeocoderInterface` | Retrieve the registered geocoder. Throws `GeocoderContainerException` if not configured. |

### `Timezone`

**Namespace:** `SalesRender\Plugin\Core\Geocoder\Components\Geocoder\Timezone`

Represents a timezone, accepting either a named timezone or a UTC offset. Implements `JsonSerializable`.

| Method | Return Type | Description |
|---|---|---|
| `__construct(string $timezoneOrOffset)` | | Create from a timezone name (e.g., `"Europe/Moscow"`) or UTC offset (e.g., `"UTC+03:00"`). Throws `InvalidTimezoneException` if invalid. |
| `getName()` | `?string` | The timezone name (e.g., `"Europe/Moscow"`) or null if constructed from offset |
| `getOffset()` | `?string` | The UTC offset (e.g., `"UTC+03:00"`) or null if constructed from name |

**Examples:**

```php
// From timezone name
$tz = new Timezone('Europe/Moscow');
$tz->getName();   // "Europe/Moscow"
$tz->getOffset(); // null

// From UTC offset
$tz = new Timezone('UTC+03:00');
$tz->getName();   // null
$tz->getOffset(); // "UTC+03:00"

// Invalid -- throws InvalidTimezoneException
$tz = new Timezone('Invalid/Zone');
```

The offset format must match the pattern `UTC[+-]\d{2}:\d{2}` (e.g., `UTC+03:00`, `UTC-05:00`). Named timezones must be valid PHP `DateTimeZone` identifiers.

### `GeocoderAction`

**Namespace:** `SalesRender\Plugin\Core\Geocoder\GeocoderAction`

HTTP action that handles `POST /protected/geocoder/handle`. Implements `ActionInterface`. Parses the request body using dot notation (via `Adbar\Dot`), constructs an `Address` object with optional `Location`, and invokes the geocoder.

The action:
1. Retrieves the geocoder from `GeocoderContainer::getHandler()`
2. Extracts `typing` from the request body
3. Constructs an `Address` from the `address.*` fields, including optional `Location` (latitude/longitude)
4. Calls `GeocoderInterface::handle($typing, $address)`
5. Returns the result array as JSON

## Exceptions

| Exception | Namespace | Description |
|---|---|---|
| `GeocoderContainerException` | `SalesRender\Plugin\Core\Geocoder\Exceptions` | Thrown when `GeocoderContainer::getHandler()` is called before configuration |
| `GeocoderHandleException` | `SalesRender\Plugin\Core\Geocoder\Exceptions` | Should be thrown by the geocoder implementation when an expected error occurs during geocoding. Results in a 417 HTTP response. |
| `InvalidTimezoneException` | `SalesRender\Plugin\Core\Geocoder\Exceptions` | Thrown when constructing a `Timezone` with an invalid name or offset |

## Example Plugin

See the reference implementation: [plugin-example-geocoder](https://github.com/SalesRender/plugin-example-geocoder)

```
plugin-example-geocoder/
  bootstrap.php           -- Plugin configuration & Geocoder registration
  console.php             -- Console entry point (ConsoleAppFactory)
  public/
    index.php             -- Web entry point (WebAppFactory)
    .htaccess             -- Apache rewrite rules
    icon.png              -- Plugin icon
  src/
    Geocoder.php          -- GeocoderInterface implementation
    SettingsForm.php       -- Settings form definition
  db/                     -- SQLite database directory
  example.env             -- Environment variables template
```

## Dependencies

| Package | Version | Purpose |
|---|---|---|
| [`salesrender/plugin-core`](https://github.com/SalesRender/plugin-core) | ^0.4.0 | Base plugin framework |
| [`salesrender/component-address`](https://github.com/SalesRender/component-address) | ^1.0.0 | Address and Location value objects |
| `adbario/php-dot-notation` | ^2.2 | Dot notation access for nested request data |

## See Also

- [salesrender/plugin-core](https://github.com/SalesRender/plugin-core) -- Base plugin framework
- [salesrender/component-address](https://github.com/SalesRender/component-address) -- Address component
- [plugin-example-geocoder](https://github.com/SalesRender/plugin-example-geocoder) -- Example geocoder plugin implementation
