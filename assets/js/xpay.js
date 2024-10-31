/**
 * Copyright (c) 2019 Nexi Payments S.p.A.
 *
 * @author      iPlusService S.r.l.
 * @category    Payment Module
 * @package     Nexi XPay
 * @version     6.0.0
 * @copyright   Copyright (c) 2019 Nexi Payments S.p.A. (https://ecommerce.nexi.it)
 * @license     GNU General Public License v3.0
 */

(function ($) {
    $(document).ready(function () {
        receipt_form = $("#nexi_xpay_receipt_form");

        if (receipt_form.length > 0) {
            receipt_form.submit();
        }

        setTimeout(() => {
            if (!window.ApplePaySession) {
                if ($(".payment_method_xpay_applepay").length) {
                    $(".payment_method_xpay_applepay").remove();
                }

                if ($(".payment_method_xpay_npg_applepay").length) {
                    $(".payment_method_xpay_npg_applepay").remove();
                }
            }
        }, 500);

        $(document).on('change', '#pagodil-installments-number', function () {
            window.localStorage.setItem('lastSelectedInstallments', parseInt($('#pagodil-installments-number').val()));

            installmentsCalc();

            if ($("#installments")) {
                $("#installments").val($("#pagodil-installments-number").val());
            }
        });
    });
})(jQuery);

function installmentsCalc() {
    var admin_url = jQuery("#xpay_admin_url").val();

    jQuery.ajax({
        type: 'POST',
        data: {
            action: 'calc_installments',
            installments: jQuery("#pagodil-installments-number").val()
        },
        url: admin_url + "admin-ajax.php",
        success: function (response) {
            jQuery("#pagodil-installment-info").html(response.installmentsLabel);
        },
        complete: function () { }
    });
}