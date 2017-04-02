<html>
<head>
	<link rel="stylesheet" href="<?php echo $hrefs['css_bootstrap']; ?>" />

	<style>
		body {
			width: auto;
			margin: 15% auto 0;
			text-align: center;
		}
	</style>
</head>
<body>
<form action="#" method="post">
	<input type="hidden" name="login-intent-form" value="1" />
	<?php
	foreach ( $data['login_fields'] as $sField ) {
		echo $sField;
	}
	?>
	<p>
		<a href="">&larr; <?php echo $strings['cancel']; ?></a>
        <button type="submit" name="submit"><?php echo $strings['verify_my_login']; ?></button>
	</p>
</form>

<form class="form-horizontal">
    <div class="form-group">
        <label for="inputEmail3" class="col-sm-2 control-label">Email</label>
        <div class="col-sm-10">
            <input type="email" class="form-control" id="inputEmail3" placeholder="Email">
        </div>
    </div>
    <div class="form-group">
        <label for="inputPassword3" class="col-sm-2 control-label">Password</label>
        <div class="col-sm-10">
            <input type="password" class="form-control" id="inputPassword3" placeholder="Password">
        </div>
    </div>
    <div class="form-group">
        <div class="col-sm-offset-2 col-sm-10">
            <div class="checkbox">
                <label>
                    <input type="checkbox"> Remember me
                </label>
            </div>
        </div>
    </div>
    <div class="form-group">
        <div class="col-sm-offset-2 col-sm-10">
            <button type="submit" class="btn btn-default">Sign in</button>
        </div>
    </div>
</form>


<p>
    <?php echo $strings['time_remaining']; ?>:
	<span id="countdown"><?php echo $data['time_remaining']; ?> <?php echo $strings['seconds']; ?></span>
</p>

<script>
    // Set the date we're counting down to
    var timeRemaining = <?php echo $data['time_remaining']; ?>;
    // Update the count down every 1 second
    var x = setInterval(function() {
            timeRemaining -= 0.1;

            if ( timeRemaining < 0 ) {
                timeRemainingText = '<?php echo $strings['login_expired']; ?>';
                clearInterval(x);
            }
            else {
                timeRemainingText = timeRemaining.toFixed(1)+" <?php echo $strings['seconds']; ?>";
            }
            document.getElementById("countdown").innerHTML = timeRemainingText;
        },
        100
    );
</script>

</body>
</html>