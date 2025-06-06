<?php
/**
 * Created for plugin-core-geocoder
 * Date: 1/19/22 11:35 PM
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Geocoder;

use Adbar\Dot;
use SalesRender\Components\Address\Address;
use SalesRender\Components\Address\Location;
use SalesRender\Plugin\Core\Actions\ActionInterface;
use SalesRender\Plugin\Core\Geocoder\Components\Geocoder\GeocoderContainer;
use SalesRender\Plugin\Core\Geocoder\Exceptions\GeocoderContainerException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Throwable;

class GeocoderAction implements ActionInterface
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        try {
            $handler = GeocoderContainer::getHandler();
        } catch (GeocoderContainerException $e) {
            return $response->withJson([
                'code' => 501,
                'message' => 'Geocoder not implemented',
            ], 501);
        }

        $data = new Dot($request->getParsedBody());
        $typing = (string)$data->get('typing', '');

        try {
            $location = null;
            if ($data->get('address.location.latitude') && $data->get('address.location.longitude')) {
                $location = new Location(
                    $data->get('address.location.latitude'),
                    $data->get('address.location.longitude'),
                );
            }

            $address = new Address(
                (string)$data->get('address.region', ''),
                (string)$data->get('address.city', ''),
                (string)$data->get('address.address_1', ''),
                (string)$data->get('address.address_2', ''),
                (string)$data->get('address.building', ''),
                (string)$data->get('address.apartment', ''),
                (string)$data->get('address.postcode', ''),
                $data->get('address.countryCode'),
                $location,
            );
        } catch (Throwable $throwable) {
            return $response->withJson([
                'code' => 400,
                'message' => 'Invalid address data',
            ], 400);
        }

        return $response->withJson($handler->handle($typing, $address));
    }
}