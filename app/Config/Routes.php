<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->get('ebook/getEbooksByChild/(:num)', 'EbookController::getEbooksByChild/$1');

$routes->group('api', function ($routes) {
    $routes->get('dashboard/user-stats', 'Dashboard::getUserStats');

    //Guru
    $routes->get('gurucontroller/guru-stats', 'GuruController::guruStats');
    $routes->post('gurucontroller/add-student', 'GuruController::addStudent');
    $routes->options('gurucontroller/add-student', 'GuruController::addStudent');
    $routes->get('gurucontroller/parents', 'GuruController::getParents');
    $routes->post('gurucontroller/add-module', 'GuruController::addModule');
    $routes->options('gurucontroller/add-module', 'GuruController::addModule');
    $routes->get('gurucontroller/categories', 'GuruController::getCategories');
    $routes->get('gurucontroller/guru-modules/(:num)', 'GuruController::getGuruModules/$1');
    $routes->post('gurucontroller/add-task', 'GuruController::addTask');
    $routes->options('gurucontroller/add-task', 'GuruController::addTask');
    $routes->get('gurucontroller/task-recap/(:num)', 'GuruController::getTaskRecap/$1');
    
    //Categories
    $routes->get('categorycontroller/categories', 'CategoryController::index');
});

$routes->options('auth/register', 'Auth::register');
$routes->post('auth/register', 'Auth::register');
$routes->post('auth/login', 'Auth::login');
$routes->post('auth/register_via_login', 'Auth::register_via_login');
$routes->options('auth/register_via_login', 'Auth::register_via_login');
$routes->options('auth/(:any)', static function () {}); // untuk preflight
