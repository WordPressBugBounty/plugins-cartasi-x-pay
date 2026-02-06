<html>

<head>
    <title>Nexi XPay</title>

    <script src="<?php echo $urlBuildSdk; ?>"></script>

    <style>
        #googlepay-iframe-container {
            height: 0;
            border: none;
            outline: none;
        }

        #googlepay-iframe-container iframe {
            width: 0;
            height: 0;
            border: none;
            outline: none;
        }
    </style>
</head>

<body>
    <div id="googlepay-iframe-container">
        <?php if ($googlePayResponse['iframe']['state'] == 'GDI_VERIFICATION') { ?>
            <?php foreach ($googlePayResponse['iframe']['fields'] as $field) { ?>
                <?php if ($field['type'] == 'GDI') { ?>
                    <iframe src="<?php echo $field['src']; ?>"></iframe>
                <?php } ?>
            <?php } ?>
        <?php } ?>
    </div>

    <script>
        new Build({
            onBuildFlowStateChange: function (evtData) {
                if (evtData.event === "BUILD_FLOW_STATE_CHANGE") {
                    if (evtData.data.state === "REDIRECTED_TO_EXTERNAL_DOMAIN" || evtData.data.state === "PAYMENT_COMPLETE") {
                        window.location.replace("<?php echo $redirectUrl; ?>");
                    }
                }
            }
        });
    </script>
</body>

</html>