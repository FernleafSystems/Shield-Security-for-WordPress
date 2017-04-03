<html>
<head>
    <link rel="stylesheet" href="<?php echo $hrefs['css_bootstrap']; ?>" />
    <title><?php echo $strings['page_title']; ?></title>
    <link rel="icon" type="image/png" href="<?php echo $hrefs['favicon']; ?>" />

    <style>
        .message {
            padding: 15px;
            margin-bottom: 30px;
        }
        .submit.form-group {
            margin-top: 25px;
        }
        .input-group-addon a {
            font-weight: bold;
            display: block;
        }
        #countdown {
            font-weight: bolder;
        }
        #TimeRemaining {
            margin-top: 30px;
            padding: 10px;
        }
    </style>

<!--    <script type="text/javascript" src="--><?php //echo $hrefs['js_bootstrap']; ?><!--"></script>-->

    <script>
        // Set the date we're counting down to
        var timeRemaining = <?php echo $data['time_remaining']; ?>;
        // Update the count down every 1 second
        var x = setInterval(function() {
                timeRemaining -= 1;
                var timeRemainingText = '';
                if ( timeRemaining < 0 ) {
                    timeRemainingText = '<?php echo $strings['login_expired']; ?>';
                    clearInterval(x);
                    loginExpired();
                }
                else {
                    var minutes = Math.floor( timeRemaining / 60 );
                    var seconds = Math.floor( timeRemaining % 60 );
                    if ( minutes > 0 ) {
                        timeRemainingText = minutes+" minutes and " + seconds +" <?php echo $strings['seconds']; ?>";
                    }
                    else {
                        timeRemainingText = timeRemaining.toFixed(0)+" <?php echo $strings['seconds']; ?>";
                    }
                }
                document.getElementById("countdown").innerHTML = timeRemainingText;
            },
            1000
        );

        function loginExpired() {
            document.getElementById("mainSubmit").setAttribute( 'disabled', 'disabled' );
            document.getElementById("TimeRemaining").className = "text-center bg-danger";
        }
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
                <input type="hidden" name="<?php echo $data['login_intent_flag']; ?>" value="1" />
                <input type="hidden" name="redirect_to" value="<?php echo $hrefs['redirect_to']; ?>" />

				<?php foreach ( $data['login_fields'] as $aField ) : ?>
                    <div class="form-group">
                        <label for="<?php echo $aField['name']; ?>" class="control-label">
                            <?php echo $aField['text']; ?>
                        </label>
                        <div class="input-group">
                            <input type="<?php echo $aField['type']; ?>"
                                   name="<?php echo $aField['name']; ?>"
                                   value="<?php echo $aField['value']; ?>"
                                   class="form-control"
                                   id="<?php echo $aField['name']; ?>"
                                   placeholder="<?php echo $aField['text']; ?>"
                            />
                            <div class="input-group-addon">
                                <a href="<?php echo $aField['help_link']; ?>" target="_blank" class="input-help">&quest;</a>
                            </div>
                        </div>
                    </div>
				<?php endforeach; ?>

                <div class="form-group submit">
                    <div class="row">
                        <div class="col-md-6 pull-right">
                            <button type="submit" id="mainSubmit" class="pull-right btn btn-default"><?php echo $strings['verify_my_login']; ?></button>
                        </div>
                        <div class="col-md-6 pull-left">
                            <button class="btn btn-link" name="cancel" value="1">&larr; <?php echo $strings['cancel']; ?></button>
                        </div>
                    </div>
                </div>
            </form>
            <p id="TimeRemaining" class="text-center bg-warning">
				<?php echo $strings['time_remaining']; ?>:
                <span id="countdown"><?php echo $strings['calculating']; ?></span>
            </p>

        </div>
    </div>
</div>

</body>
</html>