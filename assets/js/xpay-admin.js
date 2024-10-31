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
    jQuery(document).ready(function () {
        const GATEWAY_XPAY = "xpay";
        const GATEWAY_NPG = "npg";

        hideShowGatewayProperties($(".gateway-input").length ? $(".gateway-input option:selected").val() : false);

        $(".gateway-input, .installments-enabled").on("change", function () {
            hideShowGatewayProperties($(".gateway-input option:selected").val());
        });

        function hideShowGatewayProperties(flag) {
            if (flag === GATEWAY_NPG) {
                $(".xpay-only").each(function () {
                    $($(this).parents("tr")[0]).hide();
                });

                $(".npg-only").each(function () {
                    $($(this).parents("tr")[0]).show();
                });

                $(".xpay-only-text").each(function () {
                    $(this).hide();

                    $(this).next("p").hide();
                });

                $(".npg-only-text").each(function () {
                    $(this).show();
                });

                if ($('input[name$="nexi_xpay_installments_enabled"]').is(':checked')) {
                    $(".installments-only").each(function () {
                        $($(this).parents("tr")[0]).show();
                    });
                } else {
                    $(".installments-only").each(function () {
                        $($(this).parents("tr")[0]).hide();
                    });
                }
            } else if (flag === false || flag === GATEWAY_XPAY) {
                $(".npg-only").each(function () {
                    $($(this).parents("tr")[0]).hide();
                });

                $(".installments-only").each(function () {
                    $($(this).parents("tr")[0]).hide();
                });

                $(".xpay-only").each(function () {
                    $($(this).parents("tr")[0]).show();
                });

                $(".npg-only-text").each(function () {
                    $(this).hide();
                });

                $(".xpay-only-text").each(function () {
                    $(this).show();

                    $(this).next("p").show();
                });
            }
        }

        var renderPreview = function (element) {
            var styles = [
                "font-family",
                "font-size",
                "font-style",
                "font-variant",
                "letter-spacing",
                "border-color",
                "placeholder-color",
                "color"
            ];

            if (element.hasClass("build_style")) {
                for (var i = 0; i < styles.length; i++) {
                    if (element.hasClass(styles[i])) {
                        if (styles[i] === "border-color") {
                            $(".stylePreview .content-anteprima div.Bricks").css(
                                styles[i],
                                element.val()
                                );
                        } else if (styles[i] === "placeholder-color") {
                            $("#dynamicStyle").html(
                                ".stylePreview .content-anteprima .Bricks input::placeholder { color: " +
                                element.val() +
                                "}"
                                );
                        } else {
                            $(".stylePreview .content-anteprima .Bricks input").css(
                                styles[i],
                                element.val()
                                );
                        }
                        break;
                    }
                }
            }
        };

        $("input.build_style, select.build_style").each(function (index, element) {
            renderPreview($(element));
        });

        $("select.build_style").on("change", function () {
            renderPreview($(this));
        });

        $("input.build_style").on("focusout", function () {
            renderPreview($(this));
        });

        if ($(".categories-select2").length) {
            $(".categories-select2").select2({
                closeOnSelect: false,
                scrollAfterSelect: true,
                templateSelection: formatState
            });
        }

        function formatState(state) {
            var temp = state.text.split("->");

            return temp[temp.length - 1];
        }

        var checkCategoriesSelectVisibility = function () {
            if ($(".check-mode-categories").val() === "selected_categories") {
                $(".categories-select2").attr("disabled", false);
            } else {
                $(".categories-select2").attr("disabled", true);
            }
        };

        checkCategoriesSelectVisibility();

        $(".check-mode-categories").change(function () {
            checkCategoriesSelectVisibility();
        });

        jQuery("#xpay_account_form_btn").on("click", function () {
            jQuery("#xpay_account_form_btn").attr("disabled", true);

            var api_url = jQuery("#xpay_account_form_api_url").val();
            var amount = jQuery("#xpay_account_account_input").val();
            var success_message = jQuery("#xpay_account_form_success_message").val();

            if (jQuery.isNumeric(amount) && amount > 0) {
                jQuery("#xpay_account_account_input").attr("disabled", true);

                var domanda = confirm(
                    jQuery("#xpay_account_form_question").val() +
                    " " +
                    amount +
                    " " +
                    jQuery("#xpay_account_form_currency_label").val() +
                    " ?"
                    );
                if (domanda === true) {
                    $("html, body").css("cursor", "wait");

                    jQuery.ajax({
                        type: "POST",
                        dataType: "json",
                        url: api_url,
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader("X-WP-Nonce", wpApiSettings.nonce);
                        },
                        data: {
                            amount: amount
                        },
                        success: function (json) {
                            $("html, body").css("cursor", "default");

                            alert(success_message);
                            window.location.reload();
                        },
                        error: function (xhr, textStatus, err) {
                            $("html, body").css("cursor", "default");

                            var data = xhr.responseJSON;
                            alert(data.message);
                            window.location.reload();
                        }
                    });
                }
            } else {
                alert("Importo non valido!");
                jQuery("#xpay_account_account_input").val("");
                jQuery("#xpay_account_account_input").focus();
                jQuery("#xpay_account_form_btn").attr("disabled", false);
            }
        });

        function inputText(name, value) {
            return '<td><input type="text" name="' + name + '" value="' + value + '" /></td>';
        }

        function addVariation(toAmount, nInstallments) {
            var randomId = 'row-variation-' + Math.floor(Math.random() * 10000000);

            var row = '<tr id="' + randomId + '">';

            row += inputText("to_amount", toAmount);
            row += inputText("n_installments", nInstallments);
            row += '<td><button class="button delete-ranges-variation" data-target="#' + randomId + '">' + $('#ranges-delete-label').val() + '</button></td>';

            row += '</tr>';

            $('#installments-ranges-variations-container table tbody').append(row);
        }

        $(document).on('click', '#add-ranges-variation', function (event) {
            event.preventDefault();

            addVariation("", "");

            writeRangesVariation();
        });

        $(document).on('click', '.delete-ranges-variation', function (event) {
            event.preventDefault();

            $($(this).data('target')).remove();

            writeRangesVariation();
        });

        function writeRangesVariation() {
            if ($('#nexi_xpay_installments_ranges').length) {
                var variations = [];

                $('#installments-ranges-variations-container table tbody tr').each(function () {
                    variations.push({
                        to_amount: $('#' + $(this).attr('id') + ' input[name="to_amount"]').val(),
                        n_installments: $('#' + $(this).attr('id') + ' input[name="n_installments"]').val()
                    });
                });

                $('#nexi_xpay_installments_ranges').val(JSON.stringify(variations));
            }
        }

        $(document).on('change', '#installments-ranges-variations-container input', function () {
            writeRangesVariation();
        });

        if ($('#nexi_xpay_installments_ranges').length) {
            var rangesVariations = JSON.parse($('#nexi_xpay_installments_ranges').val());

            for (var variation of rangesVariations) {
                addVariation(variation.to_amount, variation.n_installments);
            }
        }
    });
})(jQuery);
