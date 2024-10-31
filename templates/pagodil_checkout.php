<div>
    <div id="installment-block">
        <p class="pagodil-p-size">
            <?php echo __('With PagoDIL by Cofidis, the merchant allows you to defer the payment in convenient installments without costs or interest.', 'woocommerce-gateway-nexi-xpay'); ?>
        </p>

        <?php if (count($installmentsNumber) !== 1) { ?>
            <script>
                var lastSelectedInstallments = parseInt(window.localStorage.getItem('lastSelectedInstallments'));

                if (lastSelectedInstallments && jQuery('#pagodil-installments-number option[value="' + lastSelectedInstallments + '"]').text()) {
                    jQuery('#pagodil-installments-number').val(lastSelectedInstallments);

                    jQuery('#pagodil-installments-number').trigger('change');
                } else {
                    jQuery('#pagodil-installments-number option:last').attr('selected', true);

                    jQuery('#pagodil-installments-number').trigger('change');
                }
            </script>

            <p id="pagodil-installments-number-title" class="pagodil-select pagodil-p-size"><?php echo __('Choose the number of installments', 'woocommerce-gateway-nexi-xpay'); ?></p>
            <select id="pagodil-installments-number" class="pagodil-select">
                <?php
                foreach ($installmentsNumber as $value) {
                    echo '<option value="' . $value . '">' . $value . '</option>';
                }
                ?>
            </select>
        <?php } ?>

        <p id="pagodil-installment-info" class="with-margin-top pagodil-p-size"><?php echo count($installmentsNumber) === 1 ? htmlentities($oneInstallmentInfo) : ""; ?></p>

    </div>

    <input type="hidden" id="xpay_admin_url" value="<?php echo admin_url() ?>">
    <input type="hidden" name="installments" id="installments" value="<?php echo end($installmentsNumber); ?>">
</div>