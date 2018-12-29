<?php

use src\Api\Exceptions\WrongDataException;
use src\Api\Exceptions\WrongRequestException;
use src\Api\Invoices\GetInvoiceById\Request\GetInvoiceByIdRequest;
use src\Api\Payments\CancelPayment\Request\CancelPaymentRequest;
use src\Api\Payments\CapturePayment\Request\CapturePaymentRequest;
use src\Api\Payments\CreateRefund\Request\CreateRefundRequest;
use src\Api\Search\SearchPayments\Request\SearchPaymentsRequest;
use src\Api\Search\SearchPayments\Response\Payment;
use src\Client\Client;
use src\Client\Sender;
use src\Exceptions\RequestException;
use src\Helpers\Logger;

class RbkMoneyAdmin
{

    protected $modulePath;
    protected $moduleFolder;

    protected $settings;

    protected $form;

    /**
     * @var array
     */
    protected $transactions = [];

    /**
     * @var string | null
     */
    protected $nextUrl;

    /**
     * @var array
     */
    protected $recurrent = [];

    /**
     * @var array
     */
    protected $recurrentItems = [];

    /**
     * @var nc_Core
     */
    protected $nc_core;

    public function __construct()
    {
        $this->nc_core = nc_Core::get_object();

        $this->moduleFolder = $this->nc_core->MODULE_FOLDER;
        $this->modulePath = str_replace(
                $this->nc_core->DOCUMENT_ROOT,
                '',
                $this->nc_core->MODULE_FOLDER
            ) . 'rbkmoney/';

        include dirname(__DIR__) . '/rbkmoney/src/autoload.php';
        include dirname(__DIR__) . '/rbkmoney/settings.php';

        $this->settings = $this->nc_core->get_settings('', 'rbkmoney');
    }

    /**
     * @return string
     */
    protected function getRecurrentItems()
    {
        $payments = $this->nc_core->db->get_results("SELECT `article` FROM `RBKmoney_Recurrent_Items`");

        $result = '';

        if (empty($payments)) {
            return $result;
        }

        foreach ($payments as $payment) {
            $result .= $payment->article . PHP_EOL;
        }

        return trim($result);
    }

    public function info_show()
    {
        require_once($this->moduleFolder . 'rbkmoney/page_info.php');
    }

    /**
     * @return boolean
     */
    public function info_save()
    {
        return true;
    }

    /**
     * @return void
     */
    public function logs_show()
    {
        $logger = new Logger();
        $logs = $logger->getLog();

        require_once($this->moduleFolder . 'rbkmoney/page_logs.php');
    }

    public function logs_save()
    {
        // Заглушка для проверки существования метода
    }

    public function deleteLogs()
    {
        $logger = new Logger();

        if ($logger->deleteLog()) {
            nc_print_status(LOGS_DELETED, 'ok');
        } else {
            nc_print_status(LOGS_DELETE_ERROR, 'error');
        }
    }

    public function downloadLogs() {
        $logger = new Logger();
        $logger->downloadLog();
    }

    /**
     * Tab "General settings".
     *
     * @global $UI_CONFIG
     */
    public function settings_show()
    {
        global $UI_CONFIG;
        $UI_CONFIG->add_settings_toolbar();
        $this->getForm();

        require_once($this->moduleFolder . 'rbkmoney/page_settings.php');
    }

    public function transactions_save()
    {
        // Заглушка для проверки существования метода
    }

    /**
     * @param $invoiceId
     * @param $paymentId
     *
     * @throws WrongRequestException
     */
    public function capturePayment($invoiceId, $paymentId)
    {
        $capturePayment = new CapturePaymentRequest(
            $invoiceId,
            $paymentId,
            CAPTURED_BY_ADMIN
        );

        $client = new Client($this->settings['apiKey'], $this->settings['shopId'], RBK_MONEY_API_URL_SETTING);
        $sender = new Sender($client);

        try {
            $sender->sendCapturePaymentRequest($capturePayment);
            nc_print_status(PAYMENT_CONFIRMED, 'ok');
        } catch (RequestException $exception) {
            nc_print_status(PAYMENT_CAPTURE_ERROR, 'error');
        }
    }

    /**
     * @param $invoiceId
     * @param $paymentId
     *
     * @throws WrongRequestException
     */
    public function cancelPayment($invoiceId, $paymentId)
    {
        $capturePayment = new CancelPaymentRequest(
            $invoiceId,
            $paymentId,
            CANCELLED_BY_ADMIN
        );

        $client = new Client($this->settings['apiKey'], $this->settings['shopId'], RBK_MONEY_API_URL_SETTING);
        $sender = new Sender($client);

        try {
            $sender->sendCancelPaymentRequest($capturePayment);
            nc_print_status(PAYMENT_CANCELLED, 'ok');
        } catch (RequestException $exception) {
            nc_print_status(PAYMENT_CANCELLED_ERROR, 'error');
        }
    }

    /**
     * @param $invoiceId
     * @param $paymentId
     *
     * @throws WrongDataException
     * @throws WrongRequestException
     */
    public function createRefund($invoiceId, $paymentId)
    {
        $capturePayment = new CreateRefundRequest(
            $invoiceId,
            $paymentId,
            REFUNDED_BY_ADMIN
        );

        $client = new Client($this->settings['apiKey'], $this->settings['shopId'], RBK_MONEY_API_URL_SETTING);
        $sender = new Sender($client);

        try {
            $sender->sendCreateRefundRequest($capturePayment);
            nc_print_status(REFUND_CREATED, 'ok');
        } catch (RequestException $exception) {
            nc_print_status(REFUND_CREATE_ERROR, 'error');
        }
    }

    /**
     * @param DateTime     $fromTime
     * @param DateTime     $toTime
     * @param string |null $token
     * @param int          $limit
     *
     * @throws RequestException
     * @throws WrongDataException
     * @throws WrongRequestException
     */
    public function transactions_show(
        DateTime $fromTime,
        DateTime $toTime,
        $token = null,
        $limit = 10
    ) {
        try {
            if (!$this->settings['apiKey']) {
                throw new WrongDataException(ERROR_API_KEY_IS_NOT_VALID, HTTP_CODE_BAD_REQUEST);
            }
            if (!$this->settings['shopId']) {
                throw new WrongDataException(ERROR_SHOP_ID_IS_NOT_VALID, HTTP_CODE_BAD_REQUEST);
            }
        } catch (WrongDataException $exception) {
            echo $exception->getMessage();
            die;
        }
        if ($fromTime->getTimestamp() > $toTime->getTimestamp()) {
            $fromTime = new DateTime('today');
        }
        if ($fromTime->getTimestamp() >= $toTime->getTimestamp()) {
            $toTime = new DateTime();
            $toTime = $toTime->setTime(23, 59, 59);
        }

        $shopId = $this->settings['shopId'];

        $sender = new Sender(new Client($this->settings['apiKey'], $shopId, RBK_MONEY_API_URL_SETTING));

        $paymentRequest = new SearchPaymentsRequest($shopId, $fromTime, $toTime, $limit);

        if (!empty($token)) {
            $paymentRequest->setContinuationToken($token);
        }

        $payments = $sender->sendSearchPaymentsRequest($paymentRequest);

        $statuses = [
            'started' => STATUS_STARTED,
            'processed' => STATUS_PROCESSED,
            'captured' => STATUS_CAPTURED,
            'cancelled' => STATUS_CANCELLED,
            'charged back' => STATUS_CHARGED_BACK,
            'refunded' => STATUS_REFUNDED,
            'failed' => STATUS_FAILED,
        ];

        /**
         * @var $payment Payment
         */
        foreach ($payments->result as $payment) {
            $invoiceRequest = new GetInvoiceByIdRequest($payment->invoiceId);
            $invoice = $sender->sendGetInvoiceByIdRequest($invoiceRequest);
            $metadata = $invoice->metadata->metadata;
            $this->transactions[] = [
                'orderId' => $metadata['orderId'],
                'invoiceId' => $invoice->id,
                'paymentId' => $payment->id,
                'product' => $invoice->product,
                'flowStatus' => $payment->flow->type,
                'paymentStatus' => $payment->status->getValue(),
                'status' => $statuses[$payment->status->getValue()],
                'amount' => number_format($payment->amount/100, 2, '.', ''),
                'createdAt' => $payment->createdAt->format(FULL_DATE_FORMAT),
            ];
        }

        if (!empty($payments->continuationToken)) {
            $domain = nc_core('catalogue')->get_current('Domain');
            $rbkMoneyPath = nc_core('catalogue')->get_url_by_host_name($domain) . nc_module_path('rbkmoney');
            $nextPagePath =  "{$rbkMoneyPath}admin.php?view=transactions&token=$payments->continuationToken";
            $nextUrl = "$nextPagePath&date_from={$fromTime->format('d.m.Y')}&date_to={$toTime->format('d.m.Y')}";
        }

        $this->nextUrl = empty($nextUrl) ? null : $nextUrl;

        require_once($this->moduleFolder . 'rbkmoney/page_transactions.php');
    }

    /**
     * @return void
     */
    public function recurrent_items_save()
    {
        $ids = $this->nc_core->input->fetch_get_post('recurrentIds');

        $ids = array_map(function($value) {
            return trim($value);
        }, explode(PHP_EOL, $ids));

        // Удаляем из массива всё, кроме цифр
        $ids = array_filter($ids, function($value) {
            if (preg_match('/^\d+$/', $value)) {
                return true;
            }

            return false;
        });

        $this->nc_core->db->query('TRUNCATE TABLE `RBKmoney_Recurrent_Items`');

        foreach ($ids as $id) {
            $this->setRecurrent($id);
        }
    }

    /**
     * Сохранение id товаров регулярных платежей
     *
     * @param string $value
     *
     * @return bool
     */
    public function setRecurrent($value)
    {
        // подготовка записи в БД
        $value = $this->nc_core->db->escape($value);

        $id = $this->nc_core->db->get_var("SELECT `id` FROM `RBKmoney_Recurrent_Items` WHERE `article` = '$value'");

        if (!$id) {
            $this->nc_core->db->query("INSERT INTO `RBKmoney_Recurrent_Items` (`article`) VALUES ('$value')");
        }

        return true;
    }

    /**
     * Вывод страницы "Товары для регулярных платежей"
     */
    public function recurrent_items_show()
    {
        global $UI_CONFIG;

        $this->recurrentItems = [
            'recurrentIds' => [
                'label' => ITEM_IDS,
                'value' => $this->getRecurrentItems(),
                'placeholder' => ITEM_IDS,
            ],
        ];

        require_once($this->moduleFolder . 'rbkmoney/page_recurrent_items.php');
    }

    /**
     * Вывод страницы "Регулярные платежи"
     */
    public function recurrent_show()
    {
        foreach ($this->getRecurrentPayments() as $payment) {
            $customer = $this->getCustomer($payment->recurrent_customer_id);
            $ncUser = new nc_User;
            $user = $ncUser->get_by_id($customer['user_id']);

            $this->recurrent[$payment->id] = [
                'user_name' => $user['Login'],
                'user' => "/netcat/admin/user/index.php?phase=4&UserID={$customer['user_id']}",
                'status' => $payment->status,
                'amount' => $payment->amount,
                'name' => $payment->name,
                'date' => $payment->date,
            ];
        }

        require_once($this->moduleFolder . 'rbkmoney/page_recurrent.php');
    }

    /**
     * @param int $recurrentId
     */
    public function recurrentDelete($recurrentId)
    {
        $this->nc_core->db->query("DELETE FROM `RBKmoney_Recurrent` WHERE `id` = '$recurrentId'");

        nc_print_status(RECURRENT_DELETED, 'ok');
    }

    public function recurrent_save()
    {
        // Заглушка для проверки существования метода
    }

    /**
     * @return array
     */
    protected function getRecurrentPayments()
    {
        $result = $this->nc_core->db->get_results("SELECT * FROM `RBKmoney_Recurrent`");

        if (empty($result)) {
            return [];
        }

        return $result;
    }

    /**
     * @param $id
     *
     * @return array | null
     */
    protected function getCustomer($id)
    {
        return (array) $this->nc_core->db->get_row("SELECT `user_id`, `status` FROM `RBKmoney_Recurrent_Customers` WHERE `id` = '$id'");
    }


    /**
     * @return void
     */
    protected function getForm()
    {
        $nc_core = nc_Core::get_object();

        $save = false;

        $act = $nc_core->input->fetch_get_post('act');

        if (!empty($act) && 'save' === $act) {
            $save = true;
        }

        $this->form = [
            'apiKey' => [
                'label' => API_KEY,
                'type' => 'textarea',
                'value' => ($save ? $nc_core->input->fetch_get_post('apiKey')
                    : $this->settings['apiKey']),
                'placeholder' => API_KEY,
            ],
            'shopId' => [
                'label' => SHOP_ID,
                'type' => 'input',
                'value' => ($save ? $nc_core->input->fetch_get_post('shopId')
                    : $this->settings['shopId']),
                'placeholder' => SHOP_ID,
            ],
            'successUrl' => [
                'label' => SUCCESS_URL,
                'type' => 'input',
                'value' => ($save ? $nc_core->input->fetch_get_post('successUrl')
                    : $this->settings['successUrl']),
                'placeholder' => SUCCESS_URL,
            ],
            'paymentType' => [
                'label' => PAYMENT_TYPE,
                'type' => 'select',
                'value' => ($save ? $nc_core->input->fetch_get_post('paymentType')
                    : $this->settings['paymentType']),
                'options' => [PAYMENT_TYPE_HOLD, PAYMENT_TYPE_INSTANTLY],
                'placeholder' => PAYMENT_TYPE,
            ],
            'holdExpiration' => [
                'label' => HOLD_EXPIRATION,
                'type' => 'select',
                'value' => ($save ? $nc_core->input->fetch_get_post('holdExpiration')
                    : $this->settings['holdExpiration']),
                'options' => [EXPIRATION_PAYER, EXPIRATION_SHOP],
                'placeholder' => HOLD_EXPIRATION,
            ],
            'holdStatus' => [
                'label' => HOLD_STATUS,
                'type' => 'select',
                'value' => ($save ? $nc_core->input->fetch_get_post('holdStatus')
                    : $this->settings['holdStatus']),
                'options' => [PROCESSED, PAID],
                'placeholder' => HOLD_STATUS,
            ],
            'cardHolder' => [
                'label' => CARD_HOLDER,
                'type' => 'select',
                'value' => ($save ? $nc_core->input->fetch_get_post('cardHolder')
                    : $this->settings['cardHolder']),
                'options' => [SHOW_PARAMETER, NOT_SHOW_PARAMETER],
                'placeholder' => CARD_HOLDER,
            ],
            'shadingCvv' => [
                'label' => SHADING_CVV,
                'type' => 'select',
                'value' => ($save ? $nc_core->input->fetch_get_post('shadingCvv')
                    : $this->settings['shadingCvv']),
                'options' => [SHOW_PARAMETER, NOT_SHOW_PARAMETER],
                'placeholder' => SHADING_CVV,
            ],
            'fiscalization' => [
                'label' => FISCALIZATION,
                'type' => 'select',
                'value' => ($save ? $nc_core->input->fetch_get_post('fiscalization')
                    : $this->settings['fiscalization']),
                'options' => [FISCALIZATION_USE, FISCALIZATION_NOT_USE],
                'placeholder' => FISCALIZATION,
            ],
            'saveLogs' => [
                'label' => SAVE_LOGS,
                'type' => 'select',
                'value' => ($save ? $nc_core->input->fetch_get_post('saveLogs')
                    : $this->settings['saveLogs']),
                'options' => [NOT_SHOW_PARAMETER, SHOW_PARAMETER],
                'placeholder' => SAVE_LOGS,
            ],
        ];
    }

    /**
     * Save tab "General settings".
     */
    public function settings_save()
    {
        $this->getForm();
        $this->nc_core = nc_core::get_object();
        foreach ($this->form as $key => $v) {
            $val = $this->nc_core->input->fetch_get_post($key);
            if (!is_array($val)) {
                $this->nc_core->set_settings($key, $val, 'rbkmoney');
            } else {
                $this->nc_core->set_settings($key, serialize($val), 'rbkmoney');
            }
        }

        $this->settings = $this->nc_core->get_settings('', 'rbkmoney');
    }

}
