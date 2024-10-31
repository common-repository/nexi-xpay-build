<div id="order_xpay_details" class="panel">
    <div class="order_data_column_container">
        <div class="order_data_column">
            <h3><?php echo __("Cardholder", 'woocommerce-gateway-nexi-xpay') ?></h3>
            <p>
                <?php if ($custumerDisplayName != "") { ?>
                    <strong><?php echo __("Name: ", 'woocommerce-gateway-nexi-xpay') ?></strong> <?php echo htmlentities($custumerDisplayName) ?> <br>
                <?php } ?>

                <?php if ($custumerEmail != '') { ?>
                    <strong>Mail:</strong> <?php echo htmlentities($custumerEmail) ?> <br>
                <?php } ?>
            </p>
        </div>

        <div class="order_data_column">
            <h3><?php echo __("Card detail", 'woocommerce-gateway-nexi-xpay') ?></h3>
            <p>
                <?php if ($paymentCardBrand != '') { ?>
                    <strong><?php echo __("Card: ", 'woocommerce-gateway-nexi-xpay') ?></strong> <?php echo htmlentities($paymentCardBrand) ?> <br>
                <?php } ?>
                <?php if ($paymentCardBrandNazionalita != '') { ?>
                    <strong><?php echo __("Nationality: ", 'woocommerce-gateway-nexi-xpay') ?></strong> <?php echo htmlentities($paymentCardBrandNazionalita) ?> <br>
                <?php } ?>
                <?php if ($paymentCardBrandPan != '') { ?>
                    <strong><?php echo __("Card pan: ", 'woocommerce-gateway-nexi-xpay') ?></strong> <?php echo htmlentities($paymentCardBrandPan) ?> <br>
                <?php } ?>
                <?php if ($paymentCardBrandExpiration != '') { ?>
                    <strong><?php echo __("Expiry date: ", 'woocommerce-gateway-nexi-xpay') ?></strong> <?php echo htmlentities($paymentCardBrandExpiration) ?> <br>
                <?php } ?>
            </p>
        </div>

        <div class="order_data_column">
            <h3><?php echo __("Transaction detail", 'woocommerce-gateway-nexi-xpay') ?></h3>
            <p>
                <?php if ($transactionDate != '') { ?>
                    <strong><?php echo __("Date: ", 'woocommerce-gateway-nexi-xpay') ?></strong> <?php echo htmlentities($transactionDate) ?><br>
                <?php } ?>
                <?php if ($transactionValue) { ?>
                    <strong><?php echo __("Amount: ", 'woocommerce-gateway-nexi-xpay') ?></strong> <?php echo number_format($transactionValue, 2, ',', '.') . " " . $currencySign ?><br>
                <?php } ?>
                <?php if ($transactionCodTrans != '') { ?>
                    <strong> <?php echo __("Transaction code: ", 'woocommerce-gateway-nexi-xpay') ?></strong> <?php echo htmlentities($transactionCodTrans) ?><br>
                <?php } ?>
                <?php if ($transactionNumContratto != '') {
                ?>
                    <strong> <?php echo __("Contract number: ", 'woocommerce-gateway-nexi-xpay') ?></strong> <?php echo htmlentities($transactionNumContratto) ?><br>
                <?php } ?>
                <?php if ($transactionStatus != '') { ?>
                    <strong><?php echo __("Status: ", 'woocommerce-gateway-nexi-xpay') ?> </strong><?php echo htmlentities($transactionStatus) ?><br>
                <?php } ?>
            </p>
        </div>
    </div>

    <?php
    $showOperations = is_array($operazioni) && count($operazioni) > 0;
    if ($showOperations || $canAccount) {
    ?>
        <?php if ($showOperations) { ?>
            <h3><?php echo __("Accounting operations", 'woocommerce-gateway-nexi-xpay') ?></h3>
        <?php } else if ($canAccount) { ?>
            <h3><?php echo __("New accounting operation", 'woocommerce-gateway-nexi-xpay') ?></h3>
        <?php } ?>
        <div class="woocommerce_subscriptions_related_orders operation-detail-container">
            <?php if ($showOperations) { ?>
                <div class="operation-detail-table">
                    <table class="wp-list-table widefat fixed striped table-view-list posts">
                        <thead>
                            <tr>
                                <th><?php echo __("Date", 'woocommerce-gateway-nexi-xpay') ?></th>
                                <th><?php echo __("Type of operation", 'woocommerce-gateway-nexi-xpay') ?></th>
                                <th><?php echo __("Amount", 'woocommerce-gateway-nexi-xpay') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($operazioni as $operazione) {
                            ?>
                                <tr>
                                    <td>
                                        <?php
                                        $oData = new \DateTime($operazione['dataOperazione']);
                                        echo $oData->format("d/m/Y H:i")
                                        ?>
                                    </td>
                                    <td><?php echo $operazione['tipoOperazione'] ?></td>
                                    <td><?php echo number_format(\Nexi\WC_Nexi_Helper::div_bcdiv($operazione['importo'], 100), 2, ",", ".") . ' ' . $this->get_currency_sign($operazione['divisa']) ?></td>
                                </tr>

                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
            <?php if ($canAccount) { ?>
                <div class="accounting-container">
                    <div class="accounting-action-container">
                        <div class="input-group">
                            <input class="wc_input_price" id="xpay_account_account_input" value="" type="number" min="0.00" max="<?php echo number_format($transactionValue, 2, ".", ""); ?>" step="0.01">
                            <div class="input-currency">
                                <?php
                                echo $currencySign;
                                ?>
                            </div>
                        </div>

                        <input type="hidden" id="xpay_account_form_api_url" value=" <?php echo $accountUrl . ''; ?>">
                        <input type="hidden" id="xpay_account_form_currency_label" value="<?php echo $currencyLabel . ''; ?>">
                        <input type="hidden" id="xpay_account_form_question" value=" <?php echo htmlentities(__('Do you confirm to account', 'woocommerce-gateway-nexi-xpay')); ?>">
                        <input type="hidden" id="xpay_account_form_success_message" value="<?php echo htmlentities(sprintf(__("Accounting of transaction %s successful", 'woocommerce-gateway-nexi-xpay'), $transactionCodTrans)) . ''; ?>">

                        <button type="button" id="xpay_account_form_btn" class="button button-primary accounting-btn">
                            <?php echo __("Account", 'woocommerce-gateway-nexi-xpay') ?>
                        </button>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>