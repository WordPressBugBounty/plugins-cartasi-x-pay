jQuery(function ($) {
    var reloadingFields = false,
        buildReady = false,
        xpayBuild;

    var showLoading = function () {
        $(".loader-container").removeClass("nexi-hide");
    };

    var hideLoading = function () {
        $(".loader-container").addClass("nexi-hide");
    };

    const createBuildSdk = function (cssLink) {
        buildReady = false;

        xpayBuild = new Build({
            onBuildSuccess: function (evtData) {
                if (evtData.event === "BUILD_SUCCESS") {
                    document.getElementById(evtData.id).classList.remove("with-errors");
                }
            },
            onBuildError: function (evtData) {
                if (evtData.event === "BUILD_ERROR") {
                    document.getElementById(evtData.id).classList.add("with-errors");
                }
            },
            onConfirmError: function (evtData) {
                if ("validationStatus" in evtData) {
                    evtData.validationStatus.forEach(function (item) {
                        if (document.getElementById(item.id)) {
                            if (item.valid) {
                                document.getElementById(item.id).classList.remove("with-errors");
                            } else {
                                document.getElementById(item.id).classList.add("with-errors");
                            }
                        }
                    });
                }

                setErrorMsg(`Confirmation error`);

                $.unblockUI();
            },
            onBuildFlowStateChange: function (evtData) {
                if (evtData.event === "BUILD_FLOW_STATE_CHANGE") {
                    if (
                        evtData.state === "READY_FOR_PAYMENT" ||
                        evtData.state === "PAYMENT_COMPLETE"
                    ) {
                        buildReady = true;

                        $.unblockUI();

                        $("#place_order").trigger("click");
                    }
                }
            },
            cssLink: cssLink,
        });
    };

    var buildFields = function () {
        cleanErrorMsg();

        var admin_url = $("#xpay_admin_url").val();

        $.ajax({
            type: "POST",
            data: {
                action: "get_build_fields",
            },
            url: `${admin_url}admin-ajax.php`,
            beforeSend: function () {
                showLoading();

                $("#card-fieldset-build").hide();
            },
            success: function (response) {
                hideLoading();

                $("#card-fieldset-build").show();

                reloadingFields = false;

                if (response.error_msg) {
                    setErrorMsg(`<p>${response.error_msg}</p>`);
                } else {
                    createBuildSdk(response.cssLink);

                    for (const field of response.fields) {
                        const iframe = document.createElement("iframe");

                        iframe.src = field.src;
                        iframe.className = "iframe_input";

                        const containerElement = document.getElementById(field.id);

                        if (containerElement !== null && containerElement !== undefined) {
                            containerElement.innerHTML = "";
                            containerElement.appendChild(iframe);
                        } else {
                            const divFormInputRow = document.createElement("div");

                            divFormInputRow.className = "form--input__row";

                            const divFormWrapRow = document.createElement("div");

                            divFormWrapRow.className = "form--wrap__row";

                            divFormInputRow.appendChild(divFormWrapRow);

                            const divFormInputWrap = document.createElement("div");

                            divFormInputWrap.id = field.id;
                            divFormInputWrap.className = "form--input__wrap col-10";

                            divFormWrapRow.appendChild(divFormInputWrap);

                            divFormInputWrap.appendChild(iframe);

                            document
                                .getElementById("nexi-xpay-extra-fields-container")
                                .appendChild(divFormInputRow);
                        }
                    }

                    const surnameInputIsPresent = response.fields.find(function (field) {
                        return field.id === "CARDHOLDER_SURNAME";
                    });

                    if (surnameInputIsPresent) {
                        document.getElementById("CARDHOLDER_NAME").classList.remove("col-10");
                        document.getElementById("CARDHOLDER_NAME").classList.add("col-5");

                        document.getElementById("CARDHOLDER_SURNAME").classList.add("col-5");
                    } else {
                        document.getElementById("CARDHOLDER_NAME").classList.remove("col-5");
                        document.getElementById("CARDHOLDER_NAME").classList.add("col-10");

                        document.getElementById("CARDHOLDER_SURNAME").classList.remove("col-10");
                    }
                }
            },
            complete: function () {
                hideLoading();
            },
        });
    };

    var cleanErrorMsg = function () {
        setErrorMsg("");
    };

    var setErrorMsg = function (error) {
        $(".npg-build-error-msg-container").html(`${error}`);
    };

    var checkAndloadBuild = function () {
        if ($('input[name="payment_method"]:checked').val() === "xpay") {
            buildFields();
        }
    };

    $("form.checkout").on("change", 'input[name="payment_method"]', function () {
        checkAndloadBuild();
    });

    $(document.body).on("checkout_error", function () {
        checkAndloadBuild();
    });

    $(document).on("change", "#reload-npg-build", function () {
        if (!reloadingFields) {
            reloadingFields = true;

            checkAndloadBuild();
        }
    });

    $(document).on("click", "#place_order", function () {
        if ($("#payment_method_xpay").is(":checked")) {
            cleanErrorMsg();

            if (buildReady) {
                return true;
            } else {
                $.blockUI({ message: "" });

                xpayBuild.confirmData();

                return false;
            }
        } else {
            return true;
        }
    });
});
