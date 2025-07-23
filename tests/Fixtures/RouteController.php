<?php
namespace FlujosDimension\Controllers;
use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Core\Response;
class RouteController
{
    public function __construct(Container $c, Request $r) {}
    public function greet(string $name): Response
    {
        return new Response("Hello $name");
    }
}
