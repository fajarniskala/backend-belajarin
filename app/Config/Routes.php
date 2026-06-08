<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
// app/Config/Routes.php

$routes->get('ebook/getEbooksByChild/(:num)', 'EbookController::getEbooksByChild/$1');