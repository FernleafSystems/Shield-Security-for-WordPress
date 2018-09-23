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

<p id="<?php echo $sUniqElem; ?>" class="icwpImHuman_<?php echo $sUniqId; ?>">
	<?php echo $strings[ 'loading' ]; ?> ...</p>

<script type="text/javascript">

	var bRun<?php echo $sUniqElem; ?> = false;

	window.addEventListener( 'load', function () {
		if ( bRun<?php echo $sUniqElem; ?> === true ) {
			return;
		}
		bRun<?php echo $sUniqElem; ?> = true;

		var the_p<?php echo $sUniqId; ?> = document.getElementById( "<?php echo $sUniqElem; ?>" );
		if ( typeof the_p<?php echo $sUniqId; ?> === 'undefined' ) {
			console.log( '[Shield Security] GASP Checkbox: Problem on your page and perhaps Javascript is breaking.' );
			return;
		}

		var hon<?php echo $sUniqId; ?> = document.createElement( "input" );
		hon<?php echo $sUniqId; ?>.type = "hidden";
		hon<?php echo $sUniqId; ?>.name = "icwp_wpsf_login_email";
		
		the_p<?php echo $sUniqId; ?>.innerHTML = '';
		the_p<?php echo $sUniqId; ?>.appendChild( hon<?php echo $sUniqId; ?> );

		{ /* Prevent multiple checkboxes within the same form */
			var oThisForm = hon<?php echo $sUniqId; ?>.form;
			var nHoneyCount = 0;
			Array.prototype.forEach.call( oThisForm.elements, function ( element ) {
				if ( element.type === 'hidden' && element.name === 'icwp_wpsf_login_email' ) {
					nHoneyCount++;
				}
			} );

			if ( nHoneyCount > 1 ) {
				oThisForm.removeChild( the_p<?php echo $sUniqId; ?>);
				return;
			}
		}

		var the_cb<?php echo $sUniqId; ?> = document.createElement( "input" );
		var the_lab<?php echo $sUniqId; ?> = document.createElement( "label" );
		var the_txt<?php echo $sUniqId; ?> = document.createTextNode( " <?php echo $sLabel; ?>" );
		the_cb<?php echo $sUniqId; ?>.type = "checkbox";
		the_cb<?php echo $sUniqId; ?>.id = "_<?php echo $sCbName; ?>";
		the_cb<?php echo $sUniqId; ?>.name = "<?php echo $sCbName; ?>";
		the_p<?php echo $sUniqId; ?>.appendChild( the_lab<?php echo $sUniqId; ?> );
		the_lab<?php echo $sUniqId; ?>.appendChild( the_cb<?php echo $sUniqId; ?> );
		the_lab<?php echo $sUniqId; ?>.appendChild( the_txt<?php echo $sUniqId; ?> );

		oThisForm.onsubmit = function () {
			if ( the_cb<?php echo $sUniqId; ?>.checked !== true ) {
				alert( "<?php echo $sAlert; ?>" );
				return false;
			}
			return true;
		};
	}, false );

</script>
<noscript><?php echo $sMustJs; ?></noscript>