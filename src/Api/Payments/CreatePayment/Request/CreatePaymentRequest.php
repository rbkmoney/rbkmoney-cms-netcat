<?php

namespace src\Api\Payments\CreatePayment\Request;

use src\Api\Interfaces\FlowRequestInterface;
use src\Api\Interfaces\PayerRequestInterface;
use src\Api\Interfaces\PostRequestInterface;
use src\Api\RbkDataObject;

class CreatePaymentRequest extends RbkDataObject implements PostRequestInterface
{

    const PATH = '/processing/invoices/{invoiceID}/payments';

    /**
     * @var string
     */
    protected $flow;

    /**
     * @var PayerRequestInterface
     */
    protected $payer;

    /**
     * @var string
     */
    protected $invoiceId;

    /**
     * @param FlowRequestInterface $flow
     * @param PayerRequestInterface $payer
     * @param string $invoiceId
     */
    public function __construct(
        FlowRequestInterface $flow,
        PayerRequestInterface $payer,
        $invoiceId
    ) {
        $this->flow = $flow;
        $this->payer = $payer;
        $this->invoiceId = $invoiceId;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array(
            'flow' => $this->flow->toArray(),
            'payer' => $this->payer,
        );
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return preg_replace('/{invoiceID}/', $this->invoiceId, self::PATH);
    }

}
