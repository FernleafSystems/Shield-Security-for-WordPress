<?php
/** @var string $sUniqElem */
/** @var string $sUniqId */
/** @var string $sCbName */
/** @var string $sLabel */
/** @var string $sAlert */
/** @var string $sMustJs */
?>
<style>
	#<?php echo $sUniqElem; ?> {
		clear: both;
		border: 1px solid #dddddd;
		padding: 6px 8px 4px 10px;
		margin: 0 0 12px !important;
		border-radius: 2px;
		background-color: #f9f9f9;
	}
	#<?php echo $sUniqElem; ?> input {
		margin-right: 5px;
	}
	#<?php echo $sUniqElem; ?>label {
		display: block;
	}
</style>

<p id="<?php echo $sUniqElem; ?>" class="icwpImHuman_<?php echo $sUniqId; ?>"></p>

<script type="text/javascript">

	var bRun<?php echo $sUniqElem; ?> = false;

	window.addEventListener( 'load', function () {
		if ( bRun<?php echo $sUniqElem; ?> === true ) {
			return;
		}
		bRun<?php echo $sUniqElem; ?> = true;
		var icwp_wpsf_login_p<?php echo $sUniqId; ?> = document.getElementById( "<?php echo $sUniqElem; ?>" );
		var icwp_wpsf_login_cb<?php echo $sUniqId; ?> = document.createElement( "input" );
		var icwp_wpsf_login_lb<?php echo $sUniqId; ?> = document.createElement( "label" );
		var icwp_wpsf_login_text<?php echo $sUniqId; ?> = document.createTextNode( " <?php echo $sLabel; ?>" );
		icwp_wpsf_login_cb<?php echo $sUniqId; ?>.type = "checkbox";
		icwp_wpsf_login_cb<?php echo $sUniqId; ?>.id = "_<?php echo $sCbName; ?>";
		icwp_wpsf_login_cb<?php echo $sUniqId; ?>.name = "<?php echo $sCbName; ?>";
		icwp_wpsf_login_p<?php echo $sUniqId; ?>.appendChild( icwp_wpsf_login_lb<?php echo $sUniqId; ?> );
		icwp_wpsf_login_lb<?php echo $sUniqId; ?>.appendChild( icwp_wpsf_login_cb<?php echo $sUniqId; ?> );
		icwp_wpsf_login_lb<?php echo $sUniqId; ?>.appendChild( icwp_wpsf_login_text<?php echo $sUniqId; ?> );

		icwp_wpsf_login_cb<?php echo $sUniqId; ?>.form.onsubmit = function () {
			if ( icwp_wpsf_login_cb<?php echo $sUniqId; ?>.checked !== true ) {
				alert( "<?php echo $sAlert; ?>" );
				return false;
			}
			return true;
		}
	}, false );

</script>
<noscript><?php echo $sMustJs; ?></noscript>
<input type="hidden" id="icwp_wpsf_login_email" name="icwp_wpsf_login_email" value="" />

