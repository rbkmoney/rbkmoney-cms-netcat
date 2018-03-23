<?php

namespace src\Api\Payments\CapturePayment\Request;

use src\Api\Interfaces\PostRequestInterface;
use src\Api\RbkDataObject;

/**
 * Подтвердить указанный платеж.
 */
class CapturePaymentRequest extends RbkDataObject implements PostRequestInterface
{

    const PATH = '/processing/invoices/{invoiceID}/payments/{paymentID}/capture';

    /**
     * @var string
     */
    protected $invoiceId;

    /**
     * @var string
     */
    protected $paymentId;

    /**
     * Причина совершения операции
     *
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
        $this->paymentId = $paymentId;
        $this->reason = $reason;
        $this->invoiceId = $invoiceId;
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
