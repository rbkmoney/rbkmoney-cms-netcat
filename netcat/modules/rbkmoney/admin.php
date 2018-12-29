<?php

$NETCAT_FOLDER = $_SERVER['DOCUMENT_ROOT'];
include_once ($NETCAT_FOLDER.'/vars.inc.php');
include_once($ROOT_FOLDER . 'connect_io.php');
require ($ADMIN_FOLDER . 'function.inc.php');

require_once ($MODULE_FOLDER . 'rbkmoney/admin.inc.php');

require_once ($ADMIN_FOLDER . 'modules/ui.php');
require_once ($MODULE_FOLDER . 'rbkmoney/ui_config.php');
require_once ($MODULE_FOLDER . 'rbkmoney/ru.lang.php');

$rbkMoneyAdmin = new RbkMoneyAdmin();

if (!$view || $view === 'rbkmoney.settings') {
    $view = 'settings';
}

$title1 = NETCAT_MODULES;
$title2 = RBK_MONEY;

$AJAX_SAVER = ($perm->isGuest() || $view == 'settings');

BeginHtml ($title2, $title1, "http://$DOC_DOMAIN/settings/modules/rbkmoney/", 'rbkmoney');

$perm->ExitIfNotAccess(NC_PERM_MODULE, 0, 0, 0, 1);
$UI_CONFIG = new ui_config_module_rbkmoney($view, '');

// Name methods
$methodShow = $view . '_show';
$methodSave = $view . '_save';

if (!is_callable([$rbkMoneyAdmin, $methodShow]) || !is_callable([$rbkMoneyAdmin, $methodSave])) {
    nc_print_status('Incorrect view: '.  htmlspecialchars($view), 'error');
    exit;
}

if ($nc_core->input->fetch_get_post('act') === 'capturePayment') {
    $rbkMoneyAdmin->capturePayment($invoiceId, $paymentId);
}

if ($nc_core->input->fetch_get_post('act') === 'cancelPayment') {
    $rbkMoneyAdmin->cancelPayment($invoiceId, $paymentId);
}

if ($nc_core->input->fetch_get_post('act') === 'createRefund') {
    $rbkMoneyAdmin->createRefund($invoiceId, $paymentId);
}

if ($nc_core->input->fetch_get_post('act') === 'recurrentDelete') {
    $rbkMoneyAdmin->recurrentDelete($recurrentId);
}

if ($nc_core->input->fetch_get_post('act') === 'deleteLogs') {
    $rbkMoneyAdmin->deleteLogs();
}

if ($nc_core->input->fetch_get_post('act') === 'downloadLogs') {
    $rbkMoneyAdmin->downloadLogs();
}

// Save information
if ($nc_core->input->fetch_get_post('act') === 'save') {
    try {
        $rbkMoneyAdmin->$methodSave();
        nc_print_status(SETTINGS_SAVED, 'ok');
    } catch (Exception $exception) {
        nc_print_status($exception->getMessage(), 'error');
    }
}

if ('transactions' === $view) {
    $pageNumber = (empty($page) || $page < 1) ? 1 : $page;

    if (empty($date_from)) {
        $dateFrom = new DateTime('today');
    } else {
        $dateFrom = new DateTime($date_from);
    }

    if (empty($date_to)) {
        $dateTo = new DateTime();
        $dateTo->setTime(23, 59, 59);
    } else {
        $dateTo = new DateTime($date_to);
    }

    $rbkMoneyAdmin->transactions_show(
        $dateFrom,
        $dateTo,
        empty($token) ? null : $token
    );
} else {
// Show form method
    $rbkMoneyAdmin->$methodShow();
}


EndHtml();
