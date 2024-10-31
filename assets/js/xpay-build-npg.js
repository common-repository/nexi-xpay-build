jQuery(function ($) {
    var reloadingFields = false;

    var checkAndloadBuild = function () {
        if ($('input[name="payment_method"]:checked').val() == "xpay_build") {
            buildFields();
        } else {
            enableSubmitButton();
        }
    };

    var disableSubmitButton = function () {
        $("#place_order").attr("disabled", true);
    };

    var enableSubmitButton = function () {
        $("#place_order").attr("disabled", false);
    };

    var showLoading = function () {
        $(".loader-container").removeClass("nexi-hide");
    };

    var hideLoading = function () {
        $(".loader-container").addClass("nexi-hide");
    };

    var buildFields = function () {
        $("#wc-xpay_build-cc-form").hide();
        showLoading();

        cleanErrorMsg();
        cleanBuildFields();

        var admin_url = $("#xpay_admin_url").val();

        jQuery.ajax({
            type: "POST",
            data: {
                action: "get_build_fields",
                orderId: `${$("#npg-orderId").val()}`,
            },
            url: `${admin_url}admin-ajax.php`,
            beforeSend: function () {
                showLoading();

                disableSubmitButton();
            },
            success: function (response) {
                disableSubmitButton();

                hideLoading();

                if (response.error_msg) {
                    $(".npg-build-error-msg-container").html(`<p>${response.error_msg}</p>`);
                } else {
                    $("#wc-xpay_build-cc-form #npg-orderId").val(response.orderId);

                    var fields = response.fields;

                    for (var i = 0; i < fields.length; i++) {
                        var iframe = document.createElement("iframe");

                        iframe.src = fields[i].src;
                        iframe.className = "iframe-field";

                        $(`#${fields[i].id}`).append(iframe);
                    }

                    $("#wc-xpay_build-cc-form").show();
                }

                reloadingFields = false;
            },
            complete: function () {
                hideLoading();
            },
        });
    };

    var cleanBuildFields = function () {
        $(".build-field-row").each(function (_i, fRow) {
            $(fRow)
                .children("div")
                .children("iframe")
                .each(function (_j, field) {
                    $(field).remove();
                });
        });
    };

    var cleanErrorMsg = function () {
        $(".npg-build-error-msg-container").html("");
    };

    var setErrorMsg = function (error) {
        $(".npg-build-error-msg-container").html(`${error}`);
    };

    $("form.checkout").on("change", 'input[name="payment_method"]', function () {
        checkAndloadBuild();
    });

    $(document).on("change", "#reload-npg-build", function () {
        if (!reloadingFields) {
            reloadingFields = true;

            checkAndloadBuild();
        }
    });

    window.addEventListener("message", function (event) {
        if ("event" in event.data && "state" in event.data) {
            // Nexi sta notificando che si è pronti per il pagamento
            if (
                event.data.event === "BUILD_FLOW_STATE_CHANGE" &&
                event.data.state === "READY_FOR_PAYMENT"
            ) {
                $("#place_order").attr("disabled", false);
            }
        }

        if (event.data.event === "BUILD_ERROR") {
            if (event.data.errorCode == "HF0001") {
                setErrorMsg($("#validation-error").val());
            } else if (event.data.errorCode == "HF0003") {
                setErrorMsg($("#session-error").val());
            } else {
                console.error(event.data);
            }
        } else {
            cleanErrorMsg();
        }
    });
});
