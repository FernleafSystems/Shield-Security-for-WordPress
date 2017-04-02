<html>
<head>
    <link rel="stylesheet" href="<?php echo $hrefs['css_bootstrap']; ?>" />

    <style>
        .message {
            padding: 15px;
        }
    </style>

<!--    <script type="text/javascript" src="--><?php //echo $hrefs['js_bootstrap']; ?><!--"></script>-->

    <script>
        // Set the date we're counting down to
        var timeRemaining = <?php echo $data['time_remaining']; ?>;
        // Update the count down every 1 second
        var x = setInterval(function() {
                timeRemaining -= 1;

                if ( timeRemaining < 0 ) {
                    timeRemainingText = '<?php echo $strings['login_expired']; ?>';
                    clearInterval(x);
                }
                else {
                    timeRemainingText = timeRemaining.toFixed(0)+" <?php echo $strings['seconds']; ?>";
                }
                document.getElementById("countdown").innerHTML = timeRemainingText;
            },
            1000
        );
    </script>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6 col-md-offset-3">
            <img id="ShieldLogo" class="img-responsive" src="<?php echo $hrefs['shield_logo']; ?>" />
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 col-md-offset-4">

            <p class="message bg-<?php echo $data['message_type']; ?>"> <?php echo $strings['message']; ?></p>

            <form action="<?php echo $hrefs['form_action']; ?>" method="post">
                <input type="hidden" name="login-intent-form" value="1" />

				<?php foreach ( $data['login_fields'] as $aField ) : ?>
                    <div class="form-group">
                        <label for="<?php echo $aField['name']; ?>" class="control-label">
                            <?php echo $aField['text']; ?></label>
                            <input type="<?php echo $aField['type']; ?>"
                                   name="<?php echo $aField['name']; ?>"
                                   value="<?php echo $aField['value']; ?>"
                                   class="form-control"
                                   id="<?php echo $aField['name']; ?>"
                                   placeholder="<?php echo $aField['text']; ?>"
                            />
                    </div>
				<?php endforeach; ?>

                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 pull-right">
                            <button type="submit" class="pull-right btn btn-default"><?php echo $strings['verify_my_login']; ?></button>
                        </div>
                        <div class="col-md-6 pull-left">
                            <button class="btn btn-link" name="cancel" value="1">&larr; <?php echo $strings['cancel']; ?></button>
                        </div>
                    </div>
                </div>
            </form>
            <p>
				<?php echo $strings['time_remaining']; ?>:
                <span id="countdown"><?php echo $data['time_remaining']; ?> <?php echo $strings['seconds']; ?></span>
            </p>

        </div>
    </div>
</div>

</body>
</html>