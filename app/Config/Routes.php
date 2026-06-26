<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->get('ebook/getEbooksByChild/(:num)', 'EbookController::getEbooksByChild/$1');

$routes->group('api', function($routes) {
    $routes->get('dashboard/user-stats', 'Dashboard::getUserStats');
    
    //Guru
    $routes->get('gurucontroller/guru-stats', 'GuruController::guruStats'); 
    $routes->post('gurucontroller/add-student', 'GuruController::addStudent');
    $routes->options('gurucontroller/add-student', 'GuruController::addStudent');  
    $routes->get('gurucontroller/parents', 'GuruController::getParents');
});



$routes->post('auth/register', 'Auth::register');
$routes->post('auth/login', 'Auth::login');