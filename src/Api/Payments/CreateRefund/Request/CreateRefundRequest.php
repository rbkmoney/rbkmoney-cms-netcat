<?php

namespace src\Api\Payments\CreateRefund\Request;

use src\Api\Interfaces\PostRequestInterface;
use src\Api\RbkDataObject;

/**
 * Запрос на возврат указанного платежа
 */
class CreateRefundRequest extends RbkDataObject implements PostRequestInterface
{

    const PATH = '/processing/invoices/{invoiceID}/payments/{paymentID}/refunds';

    /**
     * @var string
     */
    protected $invoiceId;

    /**
     * @var string
     */
    protected $paymentId;

    /**
     * @var string
     */
    protected $reason;

    /**
     * @param string $invoiceId
     * @param string $paymentId
     * @param string $reason
     */
    public function __construct($invoiceId, $paymentId, $reason)
    {
        $this->invoiceId = $invoiceId;
        $this->paymentId = $paymentId;
        $this->reason = $reason;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array(
            'reason' => $this->reason,
        );
    }

    /**
     * @return string
     */
    public function getPath()
    {
        $path = preg_replace('/{invoiceID}/', $this->invoiceId, self::PATH);

        return preg_replace('/{paymentID}/', $this->paymentId, $path);
    }

}
