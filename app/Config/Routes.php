<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->get('/login', 'AuthController::loginForm');
$routes->post('/login', 'AuthController::login');
$routes->get('/change-password', 'AuthController::changePasswordForm', ['filter' => 'auth']);
$routes->post('/change-password', 'AuthController::updatePassword', ['filter' => 'auth']);
$routes->get('/logout', 'AuthController::logout', ['filter' => 'auth']);

$routes->group('admin', ['filter' => 'admin'], static function ($routes) {
    $routes->get('/', 'AdminController::index');
    $routes->get('riders', 'AdminController::riders');
    $routes->get('deliveries', 'AdminController::deliveries');
    $routes->get('history', 'AdminController::deliveryHistory');
    $routes->get('activity', 'AdminController::activity');
    $routes->get('corrections', 'AdminController::corrections');
    $routes->get('deliveries/(:num)', 'AdminController::deliveryShow/$1');
    $routes->get('remittances', 'AdminController::remittances');
    $routes->get('shortages', 'AdminController::shortages');
    $routes->get('announcements', 'AdminController::announcements');
    $routes->get('adjustments', 'AdminController::adjustments');
    $routes->get('analytics', 'AdminController::analytics');
    $routes->get('payroll', 'AdminController::payroll');
    $routes->get('settings', 'AdminController::settings');
    $routes->get('remittance/(:num)', 'AdminController::remittanceForm/$1');
    $routes->get('delivery-submissions/(:num)', 'AdminController::deliverySubmissionForm/$1');
    $routes->get('remittance/pdf/(:num)', 'AdminController::remittancePdf/$1');
    $routes->get('payroll/(:num)/pdf', 'AdminController::payrollPdf/$1');
    $routes->get('payroll/summary/pdf', 'AdminController::payrollSummaryPdf');
    $routes->get('payroll/export/csv', 'AdminController::payrollCsv');
    $routes->get('history/export/csv', 'AdminController::deliveryHistoryCsv');
    $routes->get('corrections/export/csv', 'AdminController::correctionsCsv');
    $routes->get('adjustments/export/csv', 'AdminController::adjustmentsCsv');

    $routes->post('riders', 'AdminController::createRider');
    $routes->post('riders/(:num)', 'AdminController::updateRider/$1');
    $routes->post('riders/(:num)/reset-password', 'AdminController::resetRiderPassword/$1');
    $routes->post('announcements', 'AdminController::storeAnnouncement');
    $routes->post('announcements/(:num)', 'AdminController::updateAnnouncement/$1');
    $routes->post('adjustments', 'AdminController::storeAdjustment');
    $routes->post('settings/commission', 'AdminController::storeCommissionRate');
    $routes->post('settings/remittance-accounts', 'AdminController::storeRemittanceAccount');
    $routes->post('settings/remittance-accounts/(:num)', 'AdminController::updateRemittanceAccount/$1');
    $routes->post('deliveries', 'AdminController::storeDelivery');
    $routes->post('delivery-submissions/(:num)/approve', 'AdminController::approveDeliverySubmission/$1');
    $routes->post('delivery-submissions/(:num)/reject', 'AdminController::rejectDeliverySubmission/$1');
    $routes->post('deliveries/(:num)/corrections', 'AdminController::storeDeliveryCorrectionRequest/$1');
    $routes->post('delivery-corrections/(:num)/apply', 'AdminController::applyDeliveryCorrectionRequest/$1');
    $routes->post('delivery-corrections/(:num)/reject', 'AdminController::rejectDeliveryCorrectionRequest/$1');
    $routes->post('remittance/(:num)', 'AdminController::saveRemittance/$1');
    $routes->post('shortages/(:num)/payment', 'AdminController::recordShortagePayment/$1');
    $routes->post('payroll/generate', 'AdminController::generatePayroll');
    $routes->post('payroll/(:num)/release', 'AdminController::releasePayroll/$1');
    $routes->post('payroll/(:num)/reopen', 'AdminController::reopenPayroll/$1');
});

$routes->get('/rider-dashboard', 'RiderController::ownDashboard', ['filter' => 'rider']);
$routes->get('/rider/(:num)', 'RiderController::dashboard/$1', ['filter' => 'rider']);
$routes->post('/rider/delivery-submissions', 'RiderController::storeDeliverySubmission', ['filter' => 'rider']);
$routes->post('/rider/payroll/(:num)/confirm', 'RiderController::confirmPayrollReceipt/$1', ['filter' => 'rider']);
$routes->post('/rider/announcements/(:num)/read', 'RiderController::markAnnouncementRead/$1', ['filter' => 'rider']);

$routes->group('api', static function ($routes) {
    $routes->post('login', 'Api\AuthController::login');
    $routes->get('rider/profile', 'Api\RiderController::profile');
    $routes->get('rider/dashboard', 'Api\RiderController::dashboard');
    $routes->get('rider/payrolls', 'Api\RiderController::payrolls');
    $routes->get('rider/submissions', 'Api\RiderController::submissions');
    $routes->get('rider/announcements', 'Api\RiderController::announcements');
    $routes->get('rider/remittance-accounts', 'Api\RiderController::remittanceAccounts');
    $routes->post('rider/delivery-submissions', 'Api\RiderController::storeSubmission');
    $routes->post('rider/payroll/(:num)/confirm', 'Api\RiderController::confirmPayrollReceipt/$1');
});
