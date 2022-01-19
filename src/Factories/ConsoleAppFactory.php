<?php
/**
 * Created for plugin-core-geocoder
 * Date: 19.01.2022
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Geocoder\Factories;


use Symfony\Component\Console\Application;

class ConsoleAppFactory extends \Leadvertex\Plugin\Core\Factories\ConsoleAppFactory
{

    public function build(): Application
    {
        return parent::build();
    }

}