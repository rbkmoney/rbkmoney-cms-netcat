<form class="nc-form nc--horizontal" action="admin.php" method="get">
    <input type="hidden" name="view" value="transactions">
    <div style="display: inline-block;">
        <label style="display: block; margin-bottom: 2px;"><? echo RBK_MONEY_DATE_FILTER . ' ' . RBK_MONEY_DATE_FILTER_FROM; ?></label>
        <input size="8" style="margin-top: 0;" type="text" name="date_from" value="<?php echo htmlspecialchars($fromTime->format(TRANSACTION_DATE_FORMAT)); ?>"/>
    </div>
    <div style="display: inline-block;">
        <label style="display: block; margin-bottom: 2px;"><?php echo RBK_MONEY_DATE_FILTER_TO; ?></label>
        <input size="8" style="margin-top: 0;" type="text" name="date_to" value="<?php echo htmlspecialchars($toTime->format(TRANSACTION_DATE_FORMAT)); ?>"/>
    </div>
    <button type="submit" style="height: 30px"><?php echo RBK_MONEY_FILTER_SUBMIT; ?></button>
    <script>
        $nc(function () {
            $nc('INPUT[name=date_from], INPUT[name=date_to]').datepicker();
        });
    </script>
</form>
<div>
    <table class="nc-table nc--striped nc--wide">
        <tr>
            <th><?php echo RBK_MONEY_TRANSACTION_ID; ?></th>
            <th><?php echo RBK_MONEY_TRANSACTION_PRODUCT; ?></th>
            <th><?php echo RBK_MONEY_TRANSACTION_STATUS; ?></th>
            <th><?php echo RBK_MONEY_TRANSACTION_AMOUNT; ?></th>
            <th><?php echo RBK_MONEY_TRANSACTION_CREATED_AT; ?></th>
            <th width="50"></th>
        </tr>
        <?php

        /**
         * @var $transaction \src\Api\Invoices\InvoiceResponse\InvoiceResponse
         */
        foreach ($this->transactions as $transaction) {
            $button = '';
            $statusHold = \src\Api\Payments\PaymentResponse\Flow::HOLD;
            $statusCaptured = \src\Api\Status::CAPTURED;
            $statusProcessed = \src\Api\Status::PROCESSED;

            if ($statusProcessed === $transaction['paymentStatus'] && $statusHold === $transaction['flowStatus']) {
                $button = '<form action="admin.php">
                                <input type="hidden" name="view" value="transactions">
                                <input type="hidden" name="act" value="capturePayment">
                                <input type="hidden" name="date_from" value="'.$fromTime->format(TRANSACTION_DATE_FORMAT).'">
                                <input type="hidden" name="date_to" value="'.$toTime->format(TRANSACTION_DATE_FORMAT).'">
                                <input type="hidden" name="invoiceId" value="'.$transaction['invoiceId'].'">
                                <input type="hidden" name="paymentId" value="'.$transaction['paymentId'].'">
                                <button type="submit" style="height: 30px">' . CONFIRM_PAYMENT . '</button>
                          </form><br>';
                $button .= '<form action="admin.php">
                                <input type="hidden" name="view" value="transactions">
                                <input type="hidden" name="act" value="cancelPayment">
                                <input type="hidden" name="date_from" value="'.$fromTime->format(TRANSACTION_DATE_FORMAT).'">
                                <input type="hidden" name="date_to" value="'.$toTime->format(TRANSACTION_DATE_FORMAT).'">
                                <input type="hidden" name="invoiceId" value="'.$transaction['invoiceId'].'">
                                <input type="hidden" name="paymentId" value="'.$transaction['paymentId'].'">
                                <button type="submit" style="height: 30px">' . CANCEL_PAYMENT . '</button>
                          </form>';
            } elseif ($statusCaptured === $transaction['paymentStatus']) {
                $button = '<form action="admin.php">
                                <input type="hidden" name="view" value="transactions">
                                <input type="hidden" name="act" value="createRefund">
                                <input type="hidden" name="date_from" value="'.$fromTime->format(TRANSACTION_DATE_FORMAT).'">
                                <input type="hidden" name="date_to" value="'.$toTime->format(TRANSACTION_DATE_FORMAT).'">
                                <input type="hidden" name="invoiceId" value="'.$transaction['invoiceId'].'">
                                <input type="hidden" name="paymentId" value="'.$transaction['paymentId'].'">
                                <button type="submit" style="height: 30px">' . CREATE_PAYMENT_REFUND . '</button>
                          </form>';
            }

            echo "<tr class='nc-infoblock-object'>
                      <td>{$transaction['orderId']}</td>
                      <td>{$transaction['product']}</td>
                      <td>{$transaction['status']}</td>
                      <td>{$transaction['amount']}</td>
                      <td>{$transaction['createdAt']}</td>
                      <td>$button</td>
                  </tr>";
        }
        ?>
    </table>
    <table>
        <tr>
            <?php
                if (!empty($this->nextUrl)) {
                    echo '<td><a href="' . $this->nextUrl . '">' . NEXT . ' >></a></td>';
                }
            ?>
        </tr>
    </table>
</div>