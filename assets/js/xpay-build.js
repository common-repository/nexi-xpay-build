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

var card = {};

function renderXpayBuild() {
    if (jQuery("#payment_method_xpay_build").is(":checked")) {
        checkCanSavePaymentMethod();

        jQuery("input[name='wc-xpay_build-payment-token']").on("change", function () {
            checkCanSavePaymentMethod();
        });

        XPay.init();

        CreateXpayBuildForm();

        jQuery(".xpay-card-cvv").each(function () {
            CreateXpayBuildForm(jQuery(this).attr("id"), true);
        });
    }
}

// Handler per la gestione degli errori di validazione carta
window.addEventListener("XPay_Card_Error", function (event) {
    var displayError = document.getElementById("xpay-card-errors");

    if (event.detail.errorMessage) {
        // Visualizzo il messaggio di errore
        displayError.innerHTML = event.detail.errorMessage;

        if (jQuery("#xpay_build_border_color_error").val()) {
            jQuery("#xpay-card").css(
                "border",
                "1px solid " + jQuery("#xpay_build_border_color_error").val()
            );
        }
    } else {
        // Nessun errore nascondo eventuali messaggi rimasti
        displayError.textContent = "";

        if (jQuery("#xpay_build_border_color_default").val()) {
            jQuery("#xpay-card").css(
                "border",
                "1px solid " + jQuery("#xpay_build_border_color_default").val()
            );
        }
    }
});

window.addEventListener("XPay_Nonce", function (event) {
    var response = event.detail;

    if (response.esito && response.esito === "OK") {
        document.getElementById("xpayNonce").setAttribute("value", response.xpayNonce);
        document.getElementById("xpayIdOperazione").setAttribute("value", response.idOperazione);
        document.getElementById("xpayTimeStamp").setAttribute("value", response.timeStamp);
        document.getElementById("xpayEsito").setAttribute("value", response.esito);
        document.getElementById("xpayMac").setAttribute("value", response.mac);

        form = document.getElementById("wc-xpay_build-cc-form");

        for (var prop in response.dettaglioCarta) {
            var x = document.createElement("INPUT");
            x.setAttribute("type", "hidden");
            x.setAttribute("name", "dettaglioCarta[" + prop + "]");
            x.setAttribute("value", response.dettaglioCarta[prop]);
            form.appendChild(x);
        }

        // Submit del form contenente il nonce verso il server del merchant
        document.getElementById("place_order").click();
    } else {
        // Visualizzazione errore creazione nonce e ripristino bottone form
        var displayError = document.getElementById("xpay-card-errors");

        displayError.textContent = "[" + response.errore.codice + "] " + response.errore.messaggio;
        document.getElementById("place_order").disabled = false;

        htmlErr =
            '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">\n<ul class="woocommerce-error" role="alert"><li>' +
            jQuery("#xpay_msg_err").val() +
            "</li></ul></div>";

        jQuery("form.checkout").prepend(htmlErr);
        jQuery("html,body").animate({ scrollTop: 0 }, "slow");
        jQuery("body").trigger("update_checkout");
    }
});

function xPayNonce() {
    if (jQuery("#payment_method_xpay_build").is(":checked")) {
        if (jQuery("#xpayNonce").val().length === 0) {
            if (
                typeof jQuery('input[name="wc-xpay_build-payment-token"]:checked').val() ===
                    "undefined" ||
                "new" === jQuery('input[name="wc-xpay_build-payment-token"]:checked').val()
            ) {
                jQuery("#xpay_build_transactionId").val(
                    jQuery("#xpay_build_transactionId").attr("data-new-card-value")
                );

                jQuery("#xpay_build_num_contratto").val(
                    jQuery("#xpay_build_num_contratto").attr("data-new-card-value")
                );

                XPay.createNonce("wc-xpay-cc-form", card["xpay-card"]);
            } else {
                selectedSavedCard = jQuery(
                    'input[name="wc-xpay_build-payment-token"]:checked'
                ).val();
                tokenObject = jQuery("div[data-wc-id='" + selectedSavedCard + "']");

                tokenValue = tokenObject.attr("data-token");
                instanceId = "xpay-card-cvv-" + tokenValue;

                jQuery("#xpay_build_transactionId").val(tokenObject.attr("data-codTransCvv"));

                jQuery("#xpay_build_num_contratto").val("");

                XPay.createNonce("wc-xpay-cc-form", card[instanceId]);
            }
            return false;
        } else {
            return true;
        }
    }
}

function checkCanSavePaymentMethod() {
    if (
        jQuery("#wc-xpay_build-payment-token-new").length &&
        !jQuery("#wc-xpay_build-payment-token-new").is(":checked")
    ) {
        jQuery("#save-card").removeAttr("checked");

        jQuery("#save-card").attr("disabled", true);
    } else {
        jQuery("#save-card").attr("disabled", false);
    }
}

jQuery(document).ready(function () {
    var checkout_form = jQuery("form.checkout");

    checkout_form.on("checkout_place_order_xpay_build", function () {
        // Return true to continue the submission or false to prevent it return true;
        return xPayNonce();
    });

    jQuery("#place_order").click(function () {
        return xPayNonce();
    });

    jQuery(document).on("payment_method_selected", function () {
        renderXpayBuild();
    });

    jQuery(document).on("change", 'input[name="wc-xpay_build-new-payment-method"]', function () {
        var requestType = "PA";

        if (jQuery(this).is(":checked")) {
            requestType = "PP";
        }

        XPay.updateConfig(card["xpay-card"], {
            serviceType: "paga_oc3d",
            requestType: requestType,
        });
    });

    // if any error is returned from the payment process, forces form to refresh and reload the build form
    jQuery(document.body).on("checkout_error", function () {
        if (
            jQuery("#payment_method_xpay_build").is(":checked") &&
            ("new" === jQuery('input[name="wc-xpay_build-payment-token"]:checked').val() ||
                typeof jQuery('input[name="wc-xpay_build-payment-token"]:checked').val() ===
                    "undefined")
        ) {
            if (jQuery("#xpayNonce").val().length !== 0) {
                jQuery("form.checkout").trigger("update");
                jQuery("#xpayNonce").val("");
            }
        }
    });
});

function CreateXpayBuildForm(identifier = "xpay-card", isSavedMethod = false) {
    try {
        xpay_new_payment_info_object = document.getElementById("xpay_new_payment_info");

        if (xpay_new_payment_info_object === undefined || xpay_new_payment_info_object === null) {
            return;
        }

        xpay_new_payment_info_json = xpay_new_payment_info_object.value;

        xpay_new_payment_info = JSON.parse(xpay_new_payment_info_json);

        // Configurazione del pagamento
        xpayConfig = {
            baseConfig: {
                apiKey: xpay_new_payment_info.apiKey,
                enviroment: xpay_new_payment_info.enviroment,
            },
            paymentParams: {
                amount: xpay_new_payment_info.amount,
                currency: xpay_new_payment_info.divisa,
                url: "",
                urlPost: "",
                urlBack: "",
            },
            customParams: {},
            language: xpay_new_payment_info.language,
        };

        var tds_param = {
            buyer: {},
            destinationAddress: {},
            billingAddress: {},
            cardHolderAcctInfo: {}
        };
    
        if (xpay_new_payment_info.Buyer_email != '') {
            tds_param.buyer.email = xpay_new_payment_info.Buyer_email;
        }
        if (xpay_new_payment_info.Buyer_homePhone != '') {
            tds_param.buyer.homePhone = xpay_new_payment_info.Buyer_homePhone;
        }
        if (xpay_new_payment_info.Buyer_account != '') {
            tds_param.buyer.account = xpay_new_payment_info.Buyer_account;
        }
    
        if (xpay_new_payment_info.Dest_city != '') {
            tds_param.destinationAddress.city = xpay_new_payment_info.Dest_city;
        }
        if (xpay_new_payment_info.Dest_country != '') {
            tds_param.destinationAddress.countryCode = xpay_new_payment_info.Dest_country;
        }
        if (xpay_new_payment_info.Dest_street != '') {
            tds_param.destinationAddress.street = xpay_new_payment_info.Dest_street;
        }
        if (xpay_new_payment_info.Dest_street2 != '') {
            tds_param.destinationAddress.street2 = xpay_new_payment_info.Dest_street2;
        }
        if (xpay_new_payment_info.Dest_cap != '') {
            tds_param.destinationAddress.postalCode = xpay_new_payment_info.Dest_cap;
        }
        if (xpay_new_payment_info.Dest_state != '') {
            tds_param.destinationAddress.stateCode = xpay_new_payment_info.Dest_state;
        }
    
        if (xpay_new_payment_info.Bill_city != '') {
            tds_param.billingAddress.city = xpay_new_payment_info.Bill_city;
        }
        if (xpay_new_payment_info.Bill_country != '') {
            tds_param.billingAddress.countryCode = xpay_new_payment_info.Bill_country;
        }
        if (xpay_new_payment_info.Bill_street != '') {
            tds_param.billingAddress.street = xpay_new_payment_info.Bill_street;
        }
        if (xpay_new_payment_info.Bill_street2 != '') {
            tds_param.billingAddress.street2 = xpay_new_payment_info.Bill_street2;
        }
        if (xpay_new_payment_info.Bill_cap != '') {
            tds_param.billingAddress.postalCode = xpay_new_payment_info.Bill_cap;
        }
        if (xpay_new_payment_info.Bill_state != '') {
            tds_param.billingAddress.stateCode = xpay_new_payment_info.Bill_state;
        }
    
        if (xpay_new_payment_info.chAccDate != '') {
            tds_param.cardHolderAcctInfo.chAccDate = xpay_new_payment_info.chAccDate;
        }
        if (xpay_new_payment_info.chAccAgeIndicator != '') {
            tds_param.cardHolderAcctInfo.chAccAgeIndicator = xpay_new_payment_info.chAccAgeIndicator;
        }
        if (xpay_new_payment_info.nbPurchaseAccount != '') {
            tds_param.cardHolderAcctInfo.nbPurchaseAccount = xpay_new_payment_info.nbPurchaseAccount;
        }
        if (xpay_new_payment_info.destinationAddressUsageDate != '') {
            tds_param.cardHolderAcctInfo.destinationAddressUsageDate = xpay_new_payment_info.destinationAddressUsageDate;
        }
        if (xpay_new_payment_info.destinationNameIndicator != '') {
            tds_param.cardHolderAcctInfo.destinationNameIndicator = xpay_new_payment_info.destinationNameIndicator;
        }
    
        if (Object.keys(tds_param.buyer).length === 0) {
            delete tds_param.buyer;
        }
        if (Object.keys(tds_param.destinationAddress).length === 0) {
            delete tds_param.destinationAddress;
        }
        if (Object.keys(tds_param.billingAddress).length === 0) {
            delete tds_param.billingAddress;
        }
        if (Object.keys(tds_param.cardHolderAcctInfo).length === 0) {
            delete tds_param.cardHolderAcctInfo;
        }

        var enabled3ds = document.getElementById("xpay_build_3ds").value;
    
        if (enabled3ds == '1') {
            xpayConfig.informazioniSicurezza = tds_param;
        }

        if (isSavedMethod) {
            cvvDiv = jQuery("#" + identifier);
            xpayConfig.paymentParams.transactionId = cvvDiv.attr("data-codTransCvv");
            xpayConfig.paymentParams.timeStamp = cvvDiv.attr("data-timestampCvv");
            xpayConfig.paymentParams.mac = cvvDiv.attr("data-macCvv");
            xpayConfig.customParams.num_contratto = "" + cvvDiv.attr("data-token");
            xpayConfig.serviceType = "paga_oc3d";
            xpayConfig.requestType = "PR";
        } else {
            xpayConfig.paymentParams.transactionId = xpay_new_payment_info.transactionId;
            xpayConfig.paymentParams.timeStamp = xpay_new_payment_info.timestamp;
            xpayConfig.paymentParams.mac = xpay_new_payment_info.mac;
        }

        // Configurazione SDK
        XPay.setConfig(xpayConfig);

        // Creazione dell elemento carta
        style = JSON.parse(jQuery("#xpay_build_style").val());

        card[identifier] = XPay.create(XPay.OPERATION_TYPES.CARD, style);

        document.getElementById(identifier).innerHTML = "";

        card[identifier].mount(identifier);
    } catch (error) {
        console.error(error);
    }
}
