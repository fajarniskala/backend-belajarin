<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->get('ebook/getEbooksByChild/(:num)', 'EbookController::getEbooksByChild/$1');

$routes->group('api', function ($routes) {
    $routes->get('dashboard/user-stats', 'Dashboard::getUserStats');
    $routes->get('dashboard/teachers', 'Dashboard::teachers');
    $routes->post('dashboard/addTeacher', 'Dashboard::addTeacher');
    $routes->post('dashboard/updateTeacher', 'Dashboard::updateTeacher');
    $routes->post('dashboard/deleteTeacher', 'Dashboard::deleteTeacher');

    //ortu admin view read delete
    $routes->get('dashboard/parents', 'Dashboard::parents');
    $routes->post('dashboard/updateParent', 'Dashboard::updateParent');
    $routes->post('dashboard/deleteParent', 'Dashboard::deleteParent');

    //anak admin view read delete
    $routes->get('dashboard/students', 'Dashboard::students');
    $routes->post('dashboard/updateStudent', 'Dashboard::updateStudent');
    $routes->post('dashboard/deleteStudent', 'Dashboard::deleteStudent');

    $routes->get('dashboard/getSystemReport', 'Dashboard::getSystemReport');

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
    $routes->get('gurucontroller/task-submissions/(:num)', 'GuruController::getTaskSubmissions/$1');
    $routes->post('gurucontroller/grade-submission', 'GuruController::gradeSubmission');
    $routes->options('gurucontroller/grade-submission', 'GuruController::gradeSubmission');
    $routes->get('gurucontroller/stream-submission/(:any)', 'GuruController::streamSubmission/$1');
    $routes->get('gurucontroller/modules-detailed/(:num)', 'GuruController::getGuruModulesDetailed/$1');
    $routes->get('gurucontroller/stream-module/(:any)', 'GuruController::streamModule/$1');
    $routes->delete('gurucontroller/delete-module/(:num)', 'GuruController::deleteModule/$1');
    $routes->options('gurucontroller/delete-module/(:num)', 'GuruController::deleteModule/$1');
    $routes->get('gurucontroller/students/(:num)', 'GuruController::getGuruStudents/$1');
    $routes->get('gurucontroller/grades-recap/(:num)', 'GuruController::getGuruGradesRecap/$1');
    $routes->post('gurucontroller/upload-ebook', 'GuruController::uploadEbook');
    $routes->options('gurucontroller/upload-ebook', 'GuruController::uploadEbook');
    $routes->post('gurucontroller/update-profile', 'GuruController::updateProfile');

    //ortu
    $routes->get('ortucontroller/child-reading/(:num)', 'OrtuController::getChildActiveReading/$1');
    $routes->get('ortucontroller/riwayat-baca/(:num)', 'OrtuController::getRiwayatBaca/$1');
    $routes->get('ortucontroller/dashboard/(:num)', 'OrtuController::dashboard/$1');
    $routes->post('ortucontroller/updateParentProfile', 'OrtuController::updateParentProfile');

    //Categories
    $routes->get('categorycontroller/categories', 'CategoryController::index');
});

//siswa
$routes->post('siswa/submit-task', 'SiswaController::submitTask');
$routes->get('siswa/pending-tasks/(:num)', 'SiswaController::getPendingTasks/$1');
$routes->get('siswa/categories-with-count/(:num)', 'SiswaController::getCategoriesWithCount/$1');
$routes->get('siswa/latest-reading/(:num)', 'SiswaController::getLatestReadingLog/$1');
$routes->post('siswa/save-reading', 'SiswaController::saveReadingLog');
$routes->get('siswa/stream-ebook/(:num)', 'SiswaController::streamEbook/$1');
$routes->get('siswa/gamification-stats/(:num)', 'SiswaController::getGamificationStats/$1');
$routes->get('siswa/submited-tasks/(:num)', 'SiswaController::getSubmitedTasks/$1');
$routes->get('siswa/all-ebooks/(:num)', 'SiswaController::getAllEbooks/$1');
$routes->get('siswa/modules-by-category/(:num)/(:num)', 'SiswaController::getModulesByCategory/$1/$2');
$routes->get('siswa/library-books/(:num)', 'SiswaController::getLibraryBooks/$1');
$routes->get('api/siswa/stream-modul/(:segment)', 'SiswaController::streamModul/$1');
$routes->get('siswa/achievements/(:num)', 'SiswaController::getAchievements/$1');
$routes->post('siswa/update-profile', 'SiswaController::updateProfile');

$routes->options('auth/register', 'Auth::register');
$routes->post('auth/register', 'Auth::register');
$routes->post('auth/login', 'Auth::login');
$routes->post('auth/register_via_login', 'Auth::register_via_login');
$routes->options('auth/register_via_login', 'Auth::register_via_login');
$routes->options('auth/(:any)', static function () {}); // untuk preflight
