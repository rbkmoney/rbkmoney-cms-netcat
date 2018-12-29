<?php
define('RBK_MONEY_API_URL_SETTING', 'https://api.rbk.money/v2');
define('RBK_MONEY_CHECKOUT_URL_SETTING', 'https://checkout.rbk.money/checkout.js');
define('INVOICE_LIFETIME_DATE_INTERVAL_SETTING', 'PT2H');
define('END_INVOICE_INTERVAL_SETTING', 'PT5M');
define('MODULE_NAME_SETTING', 'RBKmoney');
define('MODULE_VERSION_SETTING', '1.1');
define('DELIVERY_VAT_SETTING', '18');
define('FULL_DATE_FORMAT', 'Y-m-d H:i:s');
define('NETSHOP_STATUS_SUCCESS', 3);
define('NETSHOP_STATUS_CANCELLED', 2);
define('NETSHOP_STATUS_WAITING', 1);
define('PROPERTY_PAYMENT_TOOL_DETAILS', 'paymentToolDetails');
define('PROPERTY_CLIENT_INFO', 'clientInfo');
define('PROPERTY_PHONE_NUMBER', 'phoneNumber');
define('PROPERTY_EMAIL', 'email');
define('PROPERTY_IP', 'ip');
define('PROPERTY_TAX_MODE', 'taxMode');
define('PROPERTY_COST', 'cost');
define('PROPERTY_ID', 'id');
define('PROPERTY_STATUS', 'status');
define('PROPERTY_ACTIVE', 'active');
define('PROPERTY_PUBLIC_KEY', 'publicKey');
define('PROPERTY_ERROR', 'error');
define('PROPERTY_REASON', 'reason');
define('PROPERTY_DESCRIPTION', 'description');
define('PROPERTY_INVOICE_TEMPLATE_ID', 'invoiceTemplateID');
define('PROPERTY_CART', 'cart');
define('PROPERTY_PAYMENT_TOOL', 'paymentTool');
define('PROPERTY_CONTINUATION_TOKEN', 'continuationToken');
define('PROPERTY_RESULT', 'result');
define('PROPERTY_SHOP_ID', 'shopID');
define('PROPERTY_FEE', 'fee');
define('PROPERTY_GEO_LOCATION_INFO', 'geoLocationInfo');
define('PROPERTY_METADATA', 'metadata');
define('LOG_FILE_PATH', "{$_SERVER['DOCUMENT_ROOT']}/netcat/modules/rbkmoney/logs");
define('LOG_FILE_NAME', 'logs.txt');
define('RECURRENT_READY_STATUS', 'ready');
define('RECURRENT_UNREADY_STATUS', 'unready');
define('TRANSACTION_DATE_FORMAT', 'd.m.Y');
define('MINIMAL_PHP_VERSION', 50500);
define('HTTP_CODE_OK', 200);
define('HTTP_CODE_BAD_REQUEST', 400);
define('HTTP_CODE_FORBIDDEN', 403);
define('LOG_FILE_COMMENT', 'Отправьте этот файл в support@rbkmoney.ru');

if (!defined('PHP_VERSION_ID')) {
    $version = explode('.', PHP_VERSION);

    define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
}