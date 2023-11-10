<?php
/**
 * Created for plugin-core-geocoder
 * Date: 19.01.2022
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Geocoder\Factories;


use SalesRender\Plugin\Core\Geocoder\GeocoderAction;
use Slim\App;

class WebAppFactory extends \SalesRender\Plugin\Core\Factories\WebAppFactory
{

    public function build(): App
    {
        $this->addCors();

        $this->app
            ->post('/protected/geocoder/handle', GeocoderAction::class)
            ->add($this->protected);

        return parent::build();
    }

}