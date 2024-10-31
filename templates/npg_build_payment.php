<input type="hidden" id="xpay_admin_url" value="<?php echo admin_url() ?>">

<div class="loader-container">
    <p class="loading"></p>
</div>

<fieldset id="wc-<?php echo esc_attr($this->id) ?>-cc-form" class="wc-credit-card-form wc-payment-form">
    <input type="hidden" id="npg-orderId" name="orderId" value="">
    <input type="hidden" id="validation-error" value="<?php echo __('Incorrect or missing data', 'woocommerce-gateway-nexi-xpay'); ?>">
    <input type="hidden" id="session-error" value="<?php echo __('XPay Build session expired', 'woocommerce-gateway-nexi-xpay'); ?>">

    <div class="build-field-row">
        <div id="CARD_NUMBER"></div>
    </div>

    <div class="build-field-row">
        <div id="EXPIRATION_DATE"></div>
        <div id="SECURITY_CODE"></div>
    </div>

    <div class="build-field-row">
        <div id="CARDHOLDER_NAME"></div>
        <div id="CARDHOLDER_SURNAME"></div>
    </div>

    <div class="build-field-row">
        <div id="CARDHOLDER_EMAIL"></div>
    </div>
</fieldset>

<div class="npg-build-error-msg-container"></div>

<input type="hidden" id="reload-npg-build">

<script>
    jQuery(function($) {
        $(document).ready(function() {
            $("#reload-npg-build").trigger("change");

            var interval = setInterval(() => {
                if ($('input[name="payment_method"]:checked').val() == "xpay_build") {
                    $("#place_order").attr("disabled", true);
                } else {
                    $("#place_order").attr("disabled", false);
                }
            }, 500);

            setTimeout(() => {
                clearInterval(interval);
            }, 2000);
        });
    });
</script>