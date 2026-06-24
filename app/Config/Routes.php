<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->get('ebook/getEbooksByChild/(:num)', 'EbookController::getEbooksByChild/$1');

$routes->group('api', function($routes) {
    $routes->get('dashboard/user-stats', 'Dashboard::getUserStats');
});

$routes->post('auth/register', 'Auth::register');
$routes->post('auth/login', 'Auth::login');