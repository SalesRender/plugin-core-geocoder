# Plugin Core Geocoder

> Типоспецифичное ядро фреймворка для SalesRender-плагинов типа **GEOCODER**

## Обзор

`salesrender/plugin-core-geocoder` -- специализированная библиотека-ядро, расширяющая базовый `salesrender/plugin-core` для создания плагинов типа **Geocoder**. Geocoder-плагины разрешают адреса в географические координаты, часовые пояса и структурированные адресные данные.

Данное ядро предоставляет:

- Интерфейс `GeocoderInterface`, который разработчик должен реализовать с логикой конкретного сервиса геокодирования
- `GeocoderContainer` для регистрации реализации геокодера
- HTTP-эндпоинт (`POST /protected/geocoder/handle`) для обработки запросов геокодирования
- Объект-значение `GeocoderResult` для возврата структурированных результатов (адрес, часовой пояс, информация)
- Класс `Timezone`, поддерживающий как именованные часовые пояса, так и смещения UTC
- `GeocoderAction`, который парсит запросы и вызывает настроенный геокодер

## Установка

```bash
composer require salesrender/plugin-core-geocoder
```

### Требования

- PHP >= 7.4
- ext-json
- `salesrender/plugin-core` ^0.4.0 (устанавливается автоматически)
- `salesrender/component-address` ^1.0.0 (устанавливается автоматически)
- `adbario/php-dot-notation` ^2.2 (устанавливается автоматически)

## Архитектура

### Как это ядро расширяет plugin-core

`plugin-core-geocoder` переопределяет оба класса фабрик из базового `plugin-core`:

**`WebAppFactory`** (наследует `\SalesRender\Plugin\Core\Factories\WebAppFactory`):
- Добавляет поддержку CORS
- Регистрирует `GeocoderAction` по маршруту `POST /protected/geocoder/handle` с protected middleware

**`ConsoleAppFactory`** (наследует `\SalesRender\Plugin\Core\Factories\ConsoleAppFactory`):
- Наследует все базовые команды без добавления новых (геокодирование синхронное, очередь не требуется)

### Поток запросов

```
SalesRender CRM                          Geocoder-плагин                     Внешний API
      |                                       |                                    |
      |-- POST /protected/geocoder/handle --->|                                    |
      |                                       |-- GeocoderInterface::handle() ---->|
      |                                       |<-- GeocoderResult[] --------------|
      |<-- JSON-массив GeocoderResult --------|                                    |
```

## Начало работы: создание Geocoder-плагина

### Шаг 1: Настройка проекта

Создайте новый проект и добавьте зависимость:

```bash
mkdir my-geocoder-plugin && cd my-geocoder-plugin
composer init --name="myvendor/plugin-geocoder-myservice" --type="project"
composer require salesrender/plugin-core-geocoder
```

Создайте структуру директорий:

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

### Шаг 2: Конфигурация bootstrap

Создайте `bootstrap.php` в корне проекта. Этот файл конфигурирует все компоненты плагина:

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

# 0. Настройте переменные окружения в файле .env в корне приложения

# 1. Настройка БД (для SQLite файл *.db и родительская директория должны быть доступны для записи)
Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => Path::root()->down('db/database.db')
]));

# 2. Установите язык плагина по умолчанию
Translator::config('ru_RU');

# 3. Настройте информацию о плагине
Info::config(
    new PluginType(PluginType::GEOCODER),
    fn() => Translator::get('info', 'Название плагина'),
    fn() => Translator::get('info', 'Описание плагина в формате markdown'),
    [
        'countries' => ['RU'],
    ],
    new Developer(
        'Название вашей компании',
        'support.for.plugin@example.com',
        'example.com',
    )
);

# 4. Настройте форму настроек
Settings::setForm(fn() => new SettingsForm());

# 5. Настройте автодополнения форм (или удалите этот блок, если не используется)
AutocompleteRegistry::config(function (string $name) {
    return null;
});

# 6. Настройте GeocoderContainer, указав вашу реализацию геокодера
GeocoderContainer::config(new Geocoder());
```

**Ключевые параметры конфигурации для geocoder-плагинов:**

- **`PluginType::GEOCODER`** -- определяет тип плагина как Geocoder
- **`countries`** -- массив кодов стран ISO 3166-1 alpha-2, которые поддерживает данный геокодер (например, `['RU']`, `['RU', 'KZ']`)
- **`GeocoderContainer::config()`** -- регистрирует вашу реализацию `GeocoderInterface`

### Шаг 3: Реализация GeocoderInterface

Это ключевая часть вашего geocoder-плагина. Создайте класс, реализующий `GeocoderInterface`:

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
     * @param string $typing - свободный текстовый ввод пользователя
     * @param Address $address - структурированные адресные данные
     * @return GeocoderResult[]
     */
    public function handle(string $typing, Address $address): array
    {
        // Вариант 1: если $typing не пуст, используйте его как свободный поиск
        if (!empty(trim($typing))) {
            // Вызовите API вашего сервиса геокодирования
            // Преобразуйте ответ в объекты GeocoderResult
            $resolvedAddress = new Address(
                'Регион',           // region
                'Город',            // city
                'Улица, 1',         // address_1
                '',                 // address_2
                '123456',           // postcode
                'RU',               // countryCode
                new Location(55.7558, 37.6173)  // latitude, longitude
            );

            return [
                new GeocoderResult(
                    $resolvedAddress,
                    new Timezone('Europe/Moscow'),
                    'Дополнительная информация о результате'
                ),
            ];
        }

        // Вариант 2: если $typing пуст, разрешите/улучшите структурированный $address
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

Метод `handle()` принимает два параметра:
- **`$typing`** -- свободный текст, введённый пользователем (для поиска адреса в стиле автодополнения)
- **`$address`** -- структурированный объект `Address` с полями region, city, address_1, address_2, postcode, countryCode и location

Метод должен вернуть массив объектов `GeocoderResult`. Каждый результат содержит разрешённый `Address`, необязательный `Timezone` и необязательную информационную строку.

### Шаг 4: Создание веб-точки входа

Создайте `public/index.php`:

```php
<?php
use SalesRender\Plugin\Core\Geocoder\Factories\WebAppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$factory = new WebAppFactory();
$application = $factory->build();
$application->run();
```

Создайте `public/.htaccess`:

```apache
RewriteEngine On
RewriteRule ^output - [L]
RewriteRule ^uploaded - [L]
RewriteCond %{REQUEST_FILENAME}  -f [OR]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php [L,QSA]
```

### Шаг 5: Создание консольной точки входа

Создайте `console.php`:

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

### Шаг 6: Создание формы настроек

Создайте `src/SettingsForm.php`:

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
                $errors[] = Translator::get('settings', 'Поле не может быть пустым');
            }
            return $errors;
        };
        parent::__construct(
            Translator::get('settings', 'Настройки'),
            null,
            [
                'main' => new FieldGroup(
                    Translator::get('settings', 'Основные настройки'),
                    null,
                    [
                        'email' => new StringDefinition(
                            Translator::get('settings', 'Email'),
                            null,
                            $nonNull
                        ),
                        'password' => new PasswordDefinition(
                            Translator::get('settings', 'Пароль'),
                            null,
                            $nonNull
                        ),
                    ]
                ),
            ],
            Translator::get('settings', 'Сохранить'),
        );
    }
}
```

### Шаг 7: Создание .env

Создайте `example.env` (скопируйте в `.env` для локальной разработки):

```env
LV_PLUGIN_DEBUG=1
LV_PLUGIN_PHP_BINARY=php
LV_PLUGIN_QUEUE_LIMIT=1
LV_PLUGIN_SELF_URI=http://plugin-example/
LV_PLUGIN_COMPONENT_REGISTRATION_SCHEME=https
LV_PLUGIN_COMPONENT_REGISTRATION_HOSTNAME=lv-app
```

### Шаг 8: Инициализация и развёртывание

```bash
# Установка зависимостей
composer install

# Создание таблиц базы данных
php console.php db:create

# Запуск cron (для базовых задач, таких как special requests)
php console.php cron
```

## HTTP-маршруты

Маршруты, добавляемые `\SalesRender\Plugin\Core\Geocoder\Factories\WebAppFactory`:

| Метод | Путь | Описание | Источник |
|---|---|---|---|
| `POST` | `/protected/geocoder/handle` | Принимает запросы геокодирования. Парсит тело запроса в `typing` (string) и `address` (Address), вызывает `GeocoderInterface::handle()` и возвращает JSON-массив объектов `GeocoderResult`. Защищён middleware. | `GeocoderAction` |

Также наследуются все базовые маршруты `plugin-core`:

| Метод | Путь | Описание |
|---|---|---|
| `GET` | `/info` | Информация о плагине |
| `PUT` | `/registration` | Регистрация плагина |
| `GET` | `/protected/forms/settings` | Определение формы настроек |
| `PUT` | `/protected/data/settings` | Сохранение настроек |
| `GET` | `/protected/data/settings` | Получение данных настроек |
| `GET` | `/protected/autocomplete/{name}` | Обработчик автодополнения |
| `GET` | `/robots.txt` | Robots.txt |

### Формат запроса

`POST /protected/geocoder/handle` ожидает следующее JSON-тело:

```json
{
    "typing": "Москва Красная площадь",
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

### Формат ответа

Возвращает JSON-массив результатов геокодирования:

```json
[
    {
        "address": {
            "region": "Московская область",
            "city": "Москва",
            "address_1": "Красная площадь, 1",
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
        "info": "Дополнительная информация"
    }
]
```

### Коды ошибок

| Код | Описание |
|---|---|
| `400` | Некорректные адресные данные в запросе |
| `417` | `GeocoderHandleException` -- специфичная для геокодера ошибка при обработке |
| `501` | Геокодер не настроен (в GeocoderContainer нет обработчика) |

## CLI-команды

Ядро для геокодера не добавляет новых CLI-команд помимо унаследованных от базового `plugin-core`:

| Команда | Описание |
|---|---|
| `db:create` | Создание таблиц базы данных |
| `db:clean` | Очистка таблиц базы данных |
| `specialRequest:queue` | Обработка очереди специальных запросов |
| `specialRequest:handle` | Обработка специального запроса |
| `cron` | Запуск всех запланированных cron-задач |
| `lang:add` | Добавление языка перевода |
| `lang:update` | Обновление переводов |
| `directory:clean` | Очистка временных директорий |

## Ключевые классы и интерфейсы

### `GeocoderInterface`

**Namespace:** `SalesRender\Plugin\Core\Geocoder\Components\Geocoder\GeocoderInterface`

Основной интерфейс, который должен реализовать каждый geocoder-плагин:

```php
use SalesRender\Components\Address\Address;

interface GeocoderInterface
{
    /**
     * @param string $typing - свободный текстовый ввод
     * @param Address $address - структурированные адресные данные
     * @return GeocoderResult[]
     */
    public function handle(string $typing, Address $address): array;
}
```

### `GeocoderResult`

**Namespace:** `SalesRender\Plugin\Core\Geocoder\Components\Geocoder\GeocoderResult`

Объект-значение, представляющий один результат геокодирования. Реализует `JsonSerializable`.

| Метод | Тип возврата | Описание |
|---|---|---|
| `__construct(Address $address, ?Timezone $timezone, ?string $info = null)` | | Создание результата с адресом, необязательным часовым поясом и необязательной информацией |
| `getAddress()` | `Address` | Разрешённый/улучшенный адрес |
| `getTimezone()` | `?Timezone` | Разрешённый часовой пояс (если доступен) |
| `getInfo()` | `?string` | Дополнительный информационный текст о результате |

### `GeocoderContainer`

**Namespace:** `SalesRender\Plugin\Core\Geocoder\Components\Geocoder\GeocoderContainer`

Статический контейнер для регистрации и получения реализации `GeocoderInterface`.

| Метод | Тип возврата | Описание |
|---|---|---|
| `config(GeocoderInterface $geocoder)` | `void` | Регистрация реализации геокодера |
| `getHandler()` | `GeocoderInterface` | Получение зарегистрированного геокодера. Выбрасывает `GeocoderContainerException`, если не настроен. |

### `Timezone`

**Namespace:** `SalesRender\Plugin\Core\Geocoder\Components\Geocoder\Timezone`

Представляет часовой пояс, принимая либо именованный часовой пояс, либо смещение UTC. Реализует `JsonSerializable`.

| Метод | Тип возврата | Описание |
|---|---|---|
| `__construct(string $timezoneOrOffset)` | | Создание из названия часового пояса (например, `"Europe/Moscow"`) или смещения UTC (например, `"UTC+03:00"`). Выбрасывает `InvalidTimezoneException` при невалидном значении. |
| `getName()` | `?string` | Название часового пояса (например, `"Europe/Moscow"`) или null, если создан из смещения |
| `getOffset()` | `?string` | Смещение UTC (например, `"UTC+03:00"`) или null, если создан из названия |

**Примеры:**

```php
// Из названия часового пояса
$tz = new Timezone('Europe/Moscow');
$tz->getName();   // "Europe/Moscow"
$tz->getOffset(); // null

// Из смещения UTC
$tz = new Timezone('UTC+03:00');
$tz->getName();   // null
$tz->getOffset(); // "UTC+03:00"

// Невалидное значение -- выбрасывает InvalidTimezoneException
$tz = new Timezone('Invalid/Zone');
```

Формат смещения должен соответствовать паттерну `UTC[+-]\d{2}:\d{2}` (например, `UTC+03:00`, `UTC-05:00`). Именованные часовые пояса должны быть валидными идентификаторами PHP `DateTimeZone`.

### `GeocoderAction`

**Namespace:** `SalesRender\Plugin\Core\Geocoder\GeocoderAction`

HTTP-действие, обрабатывающее `POST /protected/geocoder/handle`. Реализует `ActionInterface`. Парсит тело запроса с использованием dot notation (через `Adbar\Dot`), конструирует объект `Address` с необязательным `Location` и вызывает геокодер.

Действие выполняет:
1. Получение геокодера из `GeocoderContainer::getHandler()`
2. Извлечение `typing` из тела запроса
3. Конструирование `Address` из полей `address.*`, включая необязательный `Location` (latitude/longitude)
4. Вызов `GeocoderInterface::handle($typing, $address)`
5. Возврат массива результатов в формате JSON

## Исключения

| Исключение | Namespace | Описание |
|---|---|---|
| `GeocoderContainerException` | `SalesRender\Plugin\Core\Geocoder\Exceptions` | Выбрасывается при вызове `GeocoderContainer::getHandler()` до конфигурации |
| `GeocoderHandleException` | `SalesRender\Plugin\Core\Geocoder\Exceptions` | Должно выбрасываться реализацией геокодера при возникновении ожидаемой ошибки в процессе геокодирования. Приводит к HTTP-ответу 417. |
| `InvalidTimezoneException` | `SalesRender\Plugin\Core\Geocoder\Exceptions` | Выбрасывается при создании `Timezone` с невалидным названием или смещением |

## Пример плагина

Смотрите эталонную реализацию: [plugin-example-geocoder](https://github.com/SalesRender/plugin-example-geocoder)

```
plugin-example-geocoder/
  bootstrap.php           -- Конфигурация плагина и регистрация Geocoder
  console.php             -- Консольная точка входа (ConsoleAppFactory)
  public/
    index.php             -- Веб-точка входа (WebAppFactory)
    .htaccess             -- Правила перенаправления Apache
    icon.png              -- Иконка плагина
  src/
    Geocoder.php          -- Реализация GeocoderInterface
    SettingsForm.php       -- Определение формы настроек
  db/                     -- Директория базы данных SQLite
  example.env             -- Шаблон переменных окружения
```

## Зависимости

| Пакет | Версия | Назначение |
|---|---|---|
| [`salesrender/plugin-core`](https://github.com/SalesRender/plugin-core) | ^0.4.0 | Базовый фреймворк плагинов |
| [`salesrender/component-address`](https://github.com/SalesRender/component-address) | ^1.0.0 | Объекты-значения Address и Location |
| `adbario/php-dot-notation` | ^2.2 | Доступ к вложенным данным запроса через dot notation |

## Смотрите также

- [salesrender/plugin-core](https://github.com/SalesRender/plugin-core) -- Базовый фреймворк плагинов
- [salesrender/component-address](https://github.com/SalesRender/component-address) -- Компонент адреса
- [plugin-example-geocoder](https://github.com/SalesRender/plugin-example-geocoder) -- Пример реализации geocoder-плагина
