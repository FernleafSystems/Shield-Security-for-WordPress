<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo $data[ 'page_locale' ]; ?>">
<head profile="http://gmpg.org/xfn/11">
    <link rel="stylesheet" href="<?php echo $hrefs[ 'css_bootstrap' ]; ?>" />
    <title><?php echo $strings[ 'page_title' ]; ?></title>
    <link rel="icon" type="image/png" href="<?php echo $imgs[ 'favicon' ]; ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
		}
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
		a.input-help {
			display: inline-block;
			padding: 0 0.5rem;
		}
		#countdown {
			font-weight: bolder;
		}
		#TimeRemaining {
			margin-top: 30px;
			padding: 10px;
		}
		#WhatIsThis {
			margin: 30px 0;
			text-decoration: underline;
		}
		#skip_mfa {
			margin: 10px 10px 5px 20px;
		}
    </style>

	<!--    <script type="text/javascript" src="--><?php //echo $hrefs['js_bootstrap']; ?><!--"></script>-->

    <script>
        // Set the date we're counting down to
		var timeRemaining = <?php echo $data[ 'time_remaining' ]; ?>;
		// Update the count down every 1 second
		var x = setInterval( function () {
				timeRemaining -= 1;
				var timeRemainingText = '';
				if ( timeRemaining < 0 ) {
					timeRemainingText = '<?php echo $strings[ 'login_expired' ]; ?>';
					clearInterval( x );
					loginExpired();
				}
				else {
					var minutes = Math.floor( timeRemaining / 60 );
					var seconds = Math.floor( timeRemaining % 60 );
					if ( minutes > 0 ) {
						timeRemainingText = minutes + " minutes and " + seconds + " <?php echo $strings[ 'seconds' ]; ?>";
					}
					else {
						timeRemainingText = timeRemaining.toFixed( 0 ) + " <?php echo $strings[ 'seconds' ]; ?>";
					}
				}
				document.getElementById( "countdown" ).innerHTML = timeRemainingText;
			},
			1000
		);

		function loginExpired() {
			document.getElementById( "mainSubmit" ).setAttribute( 'disabled', 'disabled' );
			document.getElementById( "TimeRemaining" ).className = "text-center alert alert-danger";
		}
    </script>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-8 offset-2 col-md-6 offset-md-3 text-center">
            <img id="ShieldLogo" class="img-fluid" src="<?php echo $imgs[ 'banner' ]; ?>" alt="logo"/>
        </div>
    </div>
    <div class="row">
        <div class="col-12 col-md-8 offset-md-2 col-lg-6 offset-lg-3 col-xl-4 offset-xl-4">
			<div class="row">
				<div class="col">
            		<p class="alert alert-<?php echo $data[ 'message_type' ]; ?>"> <?php echo $strings[ 'message' ]; ?></p>
				</div>
			</div>

            <form action="<?php echo $hrefs[ 'form_action' ]; ?>" method="post" class="form-horizontal">
                <input type="hidden" name="<?php echo $data[ 'login_intent_flag' ]; ?>" value="1" />
                <input type="hidden" name="redirect_to" value="<?php echo $hrefs[ 'redirect_to' ]; ?>" />
                <input type="hidden" name="cancel_href" value="<?php echo $hrefs[ 'cancel_href' ]; ?>" />

				<?php foreach ( $data[ 'login_fields' ] as $aField ) : ?>
					<div class="form-row">
						<div class="form-group col">
							<label for="<?php echo $aField[ 'name' ]; ?>"><?php echo $aField[ 'text' ]; ?></label>
							<div class="input-group">
								<input type="<?php echo $aField[ 'type' ]; ?>"
									   name="<?php echo $aField[ 'name' ]; ?>"
									   value="<?php echo $aField[ 'value' ]; ?>"
									   class="form-control"
									   id="<?php echo $aField[ 'name' ]; ?>"
									   placeholder="<?php echo $aField[ 'placeholder' ]; ?>"
									   autocomplete="off"
									<?php
									if ( !isset( $sFocus ) ) :
										$sFocus = $aField[ 'name' ];
										echo 'autofocus';
									endif;
									?>
								/>
								<?php if ( $flags[ 'show_branded_links' ] ) : ?>
									<div class="input-group-append">
										<div class="input-group-text">
											<a href="<?php echo $aField[ 'help_link' ]; ?>"
											   target="_blank" class="input-help">&quest;</a>
										</div>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>

				<?php if ( $flags[ 'can_skip_mfa' ] ) : ?>
					<div class="form-row">
					<div class="form-group mb-0">
						<div class="input-group">
							<label for="skip_mfa">
								<input type="checkbox" value="Y" name="skip_mfa" id="skip_mfa">
								<?php echo $strings[ 'skip_mfa' ]; ?>
							</label>
						</div>
                    </div>
				</div>
				<?php endif; ?>

				<div class="form-group row submit">
					<div class="col-6 order-2 text-right">
						<button type="submit" id="mainSubmit" class="pull-right btn btn-success">
							<?php echo $strings[ 'verify_my_login' ]; ?></button>
					</div>
					<div class="col-6 order-1 text-left">
						<button class="btn btn-outline-danger" name="cancel" value="1">
							&larr; <?php echo $strings[ 'cancel' ]; ?></button>
					</div>
                </div>
            </form>

			<div class="row">
				<div class="col">
					<p id="TimeRemaining" class="text-center alert alert-warning">
						<?php echo $strings[ 'time_remaining' ]; ?>:
						<span id="countdown"><?php echo $strings[ 'calculating' ]; ?></span>
					</p>
				</div>
			</div>
			<?php if ( $flags[ 'show_branded_links' ] ) : ?>
				<div class="row">
					<div class="col">
						<p id="WhatIsThis" class="text-center">
							<a href="<?php echo $hrefs[ 'what_is_this' ]; ?>" class="btn btn-link"
							   target="_blank"><?php echo $strings[ 'what_is_this' ]; ?></a>
						</p>
					</div>
				</div>
			<?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>