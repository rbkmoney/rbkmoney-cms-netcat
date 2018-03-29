<?php

use src\Api\Customers\CustomerResponse\Status as CustomerStatus;
use src\Api\Exceptions\WrongDataException;
use src\Api\Exceptions\WrongRequestException;
use src\Api\Invoices\CreateInvoice\Cart;
use src\Api\Invoices\CreateInvoice\Request\CreateInvoiceRequest;
use src\Api\Invoices\CreateInvoice\Response\CreateInvoiceResponse;
use src\Api\Invoices\CreateInvoice\TaxMode;
use src\Api\Metadata;
use src\Api\Payments\CreatePayment\HoldType;
use src\Api\Payments\CreatePayment\Request\CreatePaymentRequest;
use src\Api\Payments\CreatePayment\Request\CustomerPayerRequest;
use src\Api\Payments\CreatePayment\Request\PaymentFlowHoldRequest;
use src\Api\Payments\CreatePayment\Request\PaymentFlowInstantRequest;
use src\Api\Webhooks\CreateWebhook\Request\CreateWebhookRequest;
use src\Api\Webhooks\CustomersTopicScope;
use src\Api\Webhooks\GetWebhooks\Request\GetWebhooksRequest;
use src\Api\Webhooks\InvoicesTopicScope;
use src\Api\Webhooks\WebhookResponse\WebhookResponse;
use src\Client\Client;
use src\Client\Sender;
use src\Exceptions\RequestException;

class nc_payment_system_rbkmoney extends nc_payment_system
{

    protected $accepted_currencies = ['RUB', 'USD', 'EUR'];

    protected $currency_map = ['RUR' => 'RUB'];

    /**
     * @var Sender
     */
    private $sender;

    /**
     * @var nc_Core
     */
    private $nc_core;

    public function __construct()
    {
        $this->nc_core = nc_Core::get_object();
        $this->settings = nc_Core::get_object()->get_settings('', 'rbkmoney');

        include dirname(__DIR__) . '/../../rbkmoney/src/autoload.php';
        include dirname(__DIR__) . '/../../rbkmoney/settings.php';

        $this->sender = new Sender(new Client(
            $this->get_setting('apiKey'),
            $this->get_setting('shopId'),
            RBK_MONEY_API_URL_SETTING
        ));
    }

    public function validate_payment_request_parameters()
    {
        if (!$this->get_setting('apiKey')) {
            $this->add_error(ERROR_API_KEY_IS_NOT_VALID);
        }
        if (!$this->get_setting('shopId')) {
            $this->add_error(ERROR_SHOP_ID_IS_NOT_VALID);
        }
        if (!$this->get_setting('successUrl')) {
            $this->add_error(ERROR_SUCCESS_URL_IS_NOT_VALID);
        }
        if (!$this->get_setting('paymentType')) {
            $this->add_error(ERROR_PAYMENT_TYPE_IS_NOT_VALID);
        }
        if (PAYMENT_TYPE_HOLD === $this->get_setting('paymentType')) {
            if (!$this->get_setting('holdExpiration')) {
                $this->add_error(ERROR_HOLD_EXPIRATION_IS_NOT_VALID);
            }
            if (!$this->get_setting('holdStatus')) {
                $this->add_error(ERROR_HOLD_STATUS_IS_NOT_VALID);
            }
        }
    }

    /**
     * @param nc_payment_invoice|null $invoice
     *
     * @throws nc_record_exception
     */
    public function on_response(nc_payment_invoice $invoice = null)
    {
        $message = file_get_contents('php://input');
        $callback = json_decode($message);
        $invoice = new nc_payment_invoice();

        if (!isset($callback->invoice->metadata->orderId)) {
            $invoice->load($callback->invoice->metadata->orderId);
        }

        $invoiceStatus = $invoice->get('status');

        if(!empty($invoiceStatus) && $invoice::STATUS_SUCCESS === $invoiceStatus){
            $this->on_payment_success($invoice);
        } elseif (!empty($invoiceStatus) && $invoice::STATUS_SUCCESS !== $invoiceStatus) {
            include dirname(__DIR__) . '/../../rbkmoney/page_order_waiting.php';
        } else {
            $this->on_payment_failure($invoice);
        }
    }

    /**
     * @param nc_payment_invoice | null $invoice
     *
     * @throws Exception
     * @throws RequestException
     * @throws WrongDataException
     * @throws WrongRequestException
     * @throws nc_record_exception
     */
    public function validate_payment_callback_response(nc_payment_invoice $invoice = null)
    {
        $signature = $this->getSignatureFromHeader(getenv('HTTP_CONTENT_SIGNATURE'));

        if (empty($signature)) {
            throw new WrongDataException(WRONG_VALUE . ' `Content-Signature`');
        }

        $signDecode = base64_decode(strtr($signature, '-_,', '+/='));

        $message = file_get_contents('php://input');

        if (empty($message)) {
            throw new WrongDataException(WRONG_VALUE . ' `callback`');
        }

        if (!$this->verificationSignature($message, $signDecode)) {
            throw new WrongDataException(WRONG_SIGNATURE);
        }

        $callback = json_decode($message);

        if (isset($callback->invoice)) {
            $this->paymentCallback($callback);
        } elseif (isset($callback->customer)) {
            $this->customerCallback($callback->customer);
        }
    }

    /**
     * @param stdClass $customer
     *
     * @throws RequestException
     * @throws WrongDataException
     * @throws WrongRequestException
     */
    private function customerCallback(stdClass $customer)
    {
        $this->updateCustomerStatus($customer);

        if ($holdType = (PAYMENT_TYPE_HOLD === $this->get_setting('paymentType'))) {
            $paymentFlow = new PaymentFlowHoldRequest($this->getHoldType());
        } else {
            $paymentFlow = new PaymentFlowInstantRequest();
        }

        $payRequest = new CreatePaymentRequest(
            $paymentFlow,
            new CustomerPayerRequest($customer->id),
            $customer->metadata->firstInvoiceId
        );

        $this->sender->sendCreatePaymentRequest($payRequest);
    }

    /**
     * @return HoldType
     *
     * @throws WrongDataException
     */
    private function getHoldType()
    {
        $holdType = (EXPIRATION_PAYER === $this->get_setting('holdExpiration'))
            ? HoldType::CANCEL : HoldType::CAPTURE;

        return new HoldType($holdType);
    }

    /**
     * @param stdClass $customer
     *
     * @return void
     *
     * @throws WrongDataException
     */
    private function updateCustomerStatus(stdClass $customer)
    {
        $status = new CustomerStatus($customer->status);
        $customerId = $this->nc_core->db->escape($customer->id);

        $this->nc_core->db->query(
            "UPDATE `RBKmoney_Recurrent_Customers`
                  SET `status` = '{$status->getValue()}'
                  WHERE `customer_id` = '$customerId'"
        );
    }

    /**
     * @param stdClass $callback
     *
     * @throws Exception
     * @throws nc_record_exception
     */
    private function paymentCallback(stdClass $callback)
    {
        $invoice = new nc_payment_invoice();

        if (isset($callback->invoice->metadata->orderId)) {
            $invoice->load($callback->invoice->metadata->orderId);
            $ncNetshop = nc_netshop::get_instance();
            $netshopOrder = $ncNetshop->load_order($callback->invoice->metadata->orderId);

            if (isset($callback->eventType)) {
                $type = $callback->eventType;

                $holdStatus = $this->get_setting('holdStatus');

                if (PAID === $holdStatus) {
                    $invoiceStatus = $invoice::STATUS_SUCCESS;
                    $netshopStatus = NETSHOP_STATUS_SUCCESS;
                } elseif (PROCESSED === $holdStatus) {
                    $invoiceStatus = $invoice::STATUS_WAITING;
                    $netshopStatus = NETSHOP_STATUS_WAITING;
                } else {
                    throw new WrongDataException(ERROR_HOLD_STATUS_IS_NOT_VALID);
                }

                if (in_array($type, [
                    InvoicesTopicScope::INVOICE_PAID,
                    InvoicesTopicScope::PAYMENT_CAPTURED,
                ])) {
                    $invoice->set('status', $invoice::STATUS_SUCCESS)->save();
                    $netshopOrder->set('status', NETSHOP_STATUS_SUCCESS)->save();

                } elseif (in_array($type, [
                    InvoicesTopicScope::INVOICE_CANCELLED,
                    InvoicesTopicScope::PAYMENT_REFUNDED
                ])) {
                    $invoice->set('status', $invoice::STATUS_CANCELLED)->save();
                    $netshopOrder->set('status', NETSHOP_STATUS_CANCELLED)->save();

                } elseif (InvoicesTopicScope::PAYMENT_PROCESSED === $type) {
                    $invoice->set('status', $invoiceStatus)->save();
                    $netshopOrder->set('status', $netshopStatus)->save();
                }
            }
        }
    }

    /**
     * @param float $price
     *
     * @return string
     */
    private function prepareAmount($price)
    {
        return number_format($price, 2, '', '');
    }

    /**
     * @param string $tax
     *
     * @return string
     */
    private function getTaxSlash($tax)
    {
        return substr_replace($tax, '/', 2, 0);
    }

    /**
     * @param string $data
     * @param string $signature
     *
     * @return bool
     */
    function verificationSignature($data, $signature)
    {
        $publicKeyId = openssl_pkey_get_public($this->get_setting('publicKey'));

        if (empty($publicKeyId)) {
            return false;
        }

        $verify = openssl_verify($data, $signature, $publicKeyId, OPENSSL_ALGO_SHA256);

        return ($verify == 1);
    }

    /**
     * Возвращает сигнатуру из хедера для верификации
     *
     * @param string $contentSignature
     *
     * @return string
     *
     * @throws WrongDataException
     */
    private function getSignatureFromHeader($contentSignature)
    {
        $signature = preg_replace("/alg=(\S+);\sdigest=/", '', $contentSignature);

        if (empty($signature)) {
            throw new WrongDataException(WRONG_VALUE . ' `Content-Signature`');
        }

        return $signature;
    }

    /**
     * Проверка корректности счёта
     * @param nc_payment_invoice $invoice
     */
    protected function validate_invoice(nc_payment_invoice $invoice)
    {
        if (!($invoice->get_id())) {
            $this->add_error(NETCAT_MODULE_PAYMENT_ORDER_ID_IS_NULL);
        }

        if (!$this->is_amount_valid($this->prepareAmount($invoice->get_amount("%0.2F")))) {
            $this->add_error(NETCAT_MODULE_PAYMENT_INCORRECT_PAYMENT_AMOUNT);
        }

        if (!$this->is_currency_accepted($this->get_currency_code($invoice->get_currency()))) {
            $error = sprintf(NETCAT_MODULE_PAYMENT_INCORRECT_PAYMENT_CURRENCY,
                htmlspecialchars($invoice->get_currency()));

            $this->add_error($error);
        }
    }

    /**
     * @param nc_payment_invoice $order
     * @param string             $product
     *
     * @return CreateInvoiceResponse
     *
     * @throws Exception
     * @throws RequestException
     * @throws WrongDataException
     * @throws WrongRequestException
     * @throws nc_record_exception
     */
    private function createInvoice(nc_payment_invoice $order, $product)
    {
        $fiscalization = (FISCALIZATION_USE === $this->get_setting('fiscalization'));
        $carts = [];

        /**
         * @var $item nc_payment_invoice_item
         */
        foreach ($order->get_items() as $item) {
            $quantity = $item->get('qty');
            $itemName = $item->get('name');

            if ($fiscalization) {
                $sourceItemId = $item->get('source_item_id');

                if (empty($sourceItemId)) {
                    $tax = DELIVERY_VAT_SETTING;
                } else {
                    $tax = $item->get('vat_rate');
                }
                $taxSlash = $this->getTaxSlash($tax);

                if (in_array($taxSlash, TaxMode::$validValues)) {
                    $taxMode = new TaxMode($taxSlash);
                } elseif (in_array("$tax%", TaxMode::$validValues)) {
                    $taxMode = new TaxMode("$tax%");
                } else {
                    throw new WrongDataException(ERROR_TAX_RATE_IS_NOT_VALID . $itemName);
                }

                $carts[] = new Cart(
                    "$itemName ($quantity)",
                    $quantity,
                    $this->prepareAmount($item->get('item_price')),
                    $taxMode
                );
            }
        }

        $endDate = new DateTime();

        $createInvoice = new CreateInvoiceRequest(
            $this->get_setting('shopId'),
            $endDate->add(new DateInterval(INVOICE_LIFETIME_DATE_INTERVAL_SETTING)),
            $this->get_currency_code($order->get_currency()),
            $product,
            new Metadata([
                'orderId' => $order->get_id(),
                'cms' => "Netcat {$this->nc_core->get_edition_name()}",
                'cms_version' => $this->nc_core->get_full_version_number(),
                'module' => MODULE_NAME_SETTING,
                'module_version' => MODULE_VERSION_SETTING,
            ])
        );

        if ($fiscalization) {
            $createInvoice->addCarts($carts);
        } else {
            $createInvoice->setAmount($this->prepareAmount($order->get_amount('%0.2F')));
        }

        $invoice = $this->sender->sendCreateInvoiceRequest($createInvoice);

        $this->saveInvoice($invoice, $order);

        return $invoice;
    }

    /**
     * @param CreateInvoiceResponse $invoice
     * @param nc_payment_invoice    $order
     *
     * @return void
     */
    private function saveInvoice(CreateInvoiceResponse $invoice, nc_payment_invoice $order)
    {
        $this->nc_core->db->query(
            "INSERT INTO `RBKmoney_Invoice` (`invoice_id`, `payload`, `end_date`, `order_id`)
                  VALUES ('$invoice->id', '$invoice->payload', '{$invoice->endDate->format('Y-m-d H:i:s')}', '{$order->get_id()}')"
        );
    }

    /**
     * @param nc_payment_invoice $invoice
     *
     * @return array | null
     */
    private function getInvoice(nc_payment_invoice $invoice)
    {
        return $this->nc_core->db->get_results("SELECT *
            FROM `RBKmoney_Invoice`
            WHERE `order_id` = '{$invoice->get_id()}'");
    }

    /**
     * @param nc_payment_invoice $invoice
     *
     * @return void
     *
     * @throws Exception
     */
    public function execute_payment_request(nc_payment_invoice $invoice)
    {
        global $AUTH_USER_ID;

        $shopId = $this->get_setting('shopId');
        $orderId = $invoice->get_id();
        $product = ORDER_PAYMENT . " №$orderId " . nc_core('catalogue')->get_current('Domain');

        $necessaryWebhooks = $this->getNecessaryWebhooks();

        if (!empty($necessaryWebhooks[InvoicesTopicScope::INVOICES_TOPIC])) {
            $this->createPaymentWebhook(
                $shopId,
                $necessaryWebhooks[InvoicesTopicScope::INVOICES_TOPIC]
            );
        }

        $rbkMoneyInvoices = $this->getInvoice($invoice);

        // Даем пользователю 5 минут на заполнение даных карты
        $diff = new DateInterval('PT5M');

        foreach ($rbkMoneyInvoices as $rbkMoneyInvoice) {
            $endDate = new DateTime($rbkMoneyInvoice->end_date);

            if ($endDate->sub($diff) < new DateTime()) {
                continue;
            }

            $payload = $rbkMoneyInvoice->payload;
            $invoiceId = $rbkMoneyInvoice->invoice_id;

            break;
        }

        if (empty($payload)) {
            $invoiceResponse = $this->createInvoice($invoice, $product);

            // Создаем рекурентные платежи
            if (!empty($AUTH_USER_ID)) {
                if (!empty($necessaryWebhooks[CustomersTopicScope::CUSTOMERS_TOPIC])) {
                    $this->createCustomerWebhook(
                        $shopId,
                        $necessaryWebhooks[CustomersTopicScope::CUSTOMERS_TOPIC]
                    );
                }
                include dirname(__DIR__) . '/../../rbkmoney/customers.php';

                $customers = new Customers($this->sender);
                $customer = $customers->createRecurrent($invoice, $AUTH_USER_ID, $invoiceResponse);
            }

            $payload = $invoiceResponse->payload;
            $invoiceId = $invoiceResponse->id;
        }

        if (empty($customer)) {
            $out = 'data-invoice-id="' . $invoiceId . '"
            data-invoice-access-token="' . $payload . '"';
        } else {
            $out = $customer;
        }

        ob_end_clean();

        $holdExpiration = '';
        if ($holdType = (PAYMENT_TYPE_HOLD === $this->get_setting('paymentType'))) {
            $holdExpiration = 'data-hold-expiration="' . $this->getHoldType()->getValue() . '"';
        }

        $requireCardHolder = (NOT_SHOW_PARAMETER === $this->get_setting('cardHolder'));
        $shadingCvv = (NOT_SHOW_PARAMETER === $this->get_setting('shadingCvv'));

        $form = '<form action="' . $this->get_setting('successUrl') . '" name="pay_form" method="GET">
        <input type="hidden" name="orderId" value="' . $orderId . '">
        <input type="hidden" name="paySystem" value="' . get_class($this) . '">
    <script src="' . RBK_MONEY_CHECKOUT_URL_SETTING . '" class="rbkmoney-checkout"
            data-payment-flow-hold="' . $holdType . '"
            data-obscure-card-cvv="' . $shadingCvv . '"
            data-require-cardholder="'.$requireCardHolder.'"
            ' . $holdExpiration . '
            data-name="' . $product . '"
            data-description="' . $product . '"
            '.$out .'
            data-label="' . PAY . '">
    </script>
</form>
<script>window.onload = function() {
     document.getElementById("rbkmoney-button").style.display = "none";
     document.getElementById("rbkmoney-button").click();
  };
</script>';

        echo $form;
    }

    /**
     * @param string $shopId
     * @param array  $types
     *
     * @return void
     *
     * @throws RequestException
     * @throws WrongDataException
     * @throws WrongRequestException
     */
    private function createPaymentWebhook($shopId, array $types)
    {
        $invoiceScope = new InvoicesTopicScope($shopId, $types);

        $webhook = $this->sender->sendCreateWebhookRequest(
            new CreateWebhookRequest($invoiceScope, $this->get_callback_script_url())
        );

        nc_Core::get_object()->set_settings('publicKey', $webhook->publicKey, 'rbkmoney');
    }

    /**
     * @return array
     *
     * @throws RequestException
     * @throws WrongDataException
     * @throws WrongRequestException
     */
    private function getNecessaryWebhooks()
    {
        $webhooks = $this->sender->sendGetWebhooksRequest(new GetWebhooksRequest());

        $statuses = [
            InvoicesTopicScope::INVOICES_TOPIC => [
                InvoicesTopicScope::INVOICE_PAID,
                InvoicesTopicScope::PAYMENT_PROCESSED,
                InvoicesTopicScope::PAYMENT_CAPTURED,
                InvoicesTopicScope::INVOICE_CANCELLED,
                InvoicesTopicScope::PAYMENT_REFUNDED,
                InvoicesTopicScope::PAYMENT_PROCESSED,
            ],
            CustomersTopicScope::CUSTOMERS_TOPIC => [
                CustomersTopicScope::CUSTOMER_READY,
            ],
        ];

        /**
         * @var $webhook WebhookResponse
         */
        foreach ($webhooks->webhooks as $webhook) {
            if (empty($webhook) || $this->get_callback_script_url() !== $webhook->url) {
                continue;
            }
            if (InvoicesTopicScope::INVOICES_TOPIC === $webhook->scope->topic) {
                $statuses[InvoicesTopicScope::INVOICES_TOPIC] = array_diff(
                    $statuses[InvoicesTopicScope::INVOICES_TOPIC],
                    $webhook->scope->eventTypes
                );
            } else {
                $statuses[CustomersTopicScope::CUSTOMERS_TOPIC] = array_diff(
                    $statuses[CustomersTopicScope::CUSTOMERS_TOPIC],
                    $webhook->scope->eventTypes
                );
            }

            if (empty($statuses)) {
                nc_Core::get_object()->set_settings('publicKey', $webhook->publicKey, 'rbkmoney');
            }
        }

        return $statuses;
    }

    /**
     * @param string $shopId
     * @param array  $types
     *
     * @return void
     *
     * @throws RequestException
     * @throws WrongDataException
     * @throws WrongRequestException
     */
    private function createCustomerWebhook($shopId, array $types)
    {
        $scope = new CustomersTopicScope($shopId, $types);

        $webhook = $this->sender->sendCreateWebhookRequest(
            new CreateWebhookRequest($scope, $this->get_callback_script_url())
        );

        nc_Core::get_object()->set_settings('publicKey', $webhook->publicKey, 'rbkmoney');
    }

}
