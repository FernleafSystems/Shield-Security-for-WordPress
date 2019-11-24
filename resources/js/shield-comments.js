/** @var shield_comments object */
if ( typeof shield_comments !== 'undefined' ) {
	var iCWP_WPSF_ShieldCommentGuard = new function () {

		var submitButton;
		var origButtonValue;
		var nTimerCounter;
		var sCountdownTimer;

		this.initialise = function () {
			jQuery( document ).ready( function () {
				insertPlaceHolder_Gasp( this );
			} );
		};

		var reEnableButton = function () {
			let nRemaining = shield_comments.vars.cooldown - nTimerCounter;
			submitButton.value = shield_comments.strings.js_comment_wait.replace( "%s", nRemaining );
			if ( nTimerCounter >= shield_comments.vars.cooldown ) {
				submitButton.value = origButtonValue;
				submitButton.disabled = false;
				clearInterval( sCountdownTimer );
			}
			nTimerCounter++;
		};

		var assignElements = function ( shiep ) {
			var maybecheckbox = document.getElementById( '_shieldcb_nombre' );
			if ( typeof (maybecheckbox) === "undefined" || maybecheckbox === null ) {
				var cbnombre = document.createElement( "input" );
				cbnombre.type = "hidden";
				cbnombre.id = "_shieldcb_nombre";
				cbnombre.name = "cb_nombre";
				cbnombre.value = shield_comments.vars.cbname;
				var inputBotts = document.createElement( "input" );
				inputBotts.type = "hidden";
				inputBotts.name = "botts";
				inputBotts.value = shield_comments.vars.botts;
				var inputToken = document.createElement( "input" );
				inputToken.type = "hidden";
				inputToken.name = "comment_token";
				inputToken.value = shield_comments.vars.token;

				shiep.appendChild( cbnombre );
				shiep.appendChild( inputBotts );
				shiep.appendChild( inputToken );
			}
		};

		var reDisableButton = function () {
			submitButton.value = shield_comments.strings.comment_reload;
			submitButton.disabled = true;
		};

		var insertPlaceHolder_Gasp = function ( form ) {
			var shiep = document.getElementById( shield_comments.vars.uniq );
			if ( typeof (shiep) === "undefined" || shiep === null ) {
				return;
			}

			var shieThe_cb = document.createElement( "input" );
			shieThe_cb.type = "checkbox";
			shieThe_cb.value = "Y";
			shieThe_cb.name = shield_comments.vars.cbname;
			shieThe_cb.id = '_' + shieThe_cb.name;
			shieThe_cb.onchange = function () {
				assignElements( shiep );
			};

			var shieThe_lab = document.createElement( "label" );
			var shieThe_labspan = document.createElement( "span" );
			shieThe_labspan.innerHTML = shield_comments.strings.label;

			shieThe_lab.appendChild( shieThe_cb );
			shieThe_lab.appendChild( shieThe_labspan );

			var shishoney = document.createElement( "input" );
			shishoney.type = "hidden";
			shishoney.name = "sugar_sweet_email";

			shiep.appendChild( shishoney );
			shiep.appendChild( shieThe_lab );

			var comForm = shieThe_cb.form;
			var subbuttonList = comForm.querySelectorAll( 'input[type="submit"]' );

			if ( typeof (subbuttonList) !== "undefined" ) {

				submitButton = subbuttonList[ 0 ];

				if ( typeof (submitButton) !== "undefined" ) {

					if ( shield_comments.vars.cooldown > 0 ) {
						submitButton.disabled = true;
						origButtonValue = submitButton.value;
						nTimerCounter = 0;
						reEnableButton();
						sCountdownTimer = setInterval( reEnableButton, 1000 );
					}
					if ( shield_comments.vars.expires > 0 ) {
						setTimeout( reDisableButton, (1000 * shield_comments.vars.expires - 1000) );
					}
				}
			}

			shieThe_cb.form.onsubmit = function () {
				if ( shieThe_cb.checked !== true ) {
					alert( shield_comments.strings.alert );
					return false;
				}
				return true;
			};
		};
	}();
	iCWP_WPSF_ShieldCommentGuard.initialise();
}