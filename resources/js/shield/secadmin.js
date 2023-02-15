var iCWP_WPSF_SecurityAdmin = new function () {

	var hasCheckInPlace = false;
	var isWarningShown = false;
	var timeoutInterval = 500 * shield_vars_secadmin.vars.time_remaining;

	var checkSecAdmin = function () {
		iCWP_WPSF_StandardAjax.send_ajax_req(
			shield_vars_secadmin.ajax.sec_admin_check, true, 'sec_admin_check'
		);
	};

	var handleSecAdminCheck = function ( evt, response ) {
		if ( response.data.success ) {
			var nLeft = response.data.time_remaining;
			timeoutInterval = Math.abs( Math.max( 3, (nLeft / 2) ) * 1000 );

			if ( !isWarningShown && nLeft < 20 && nLeft > 8 ) {
				isWarningShown = true;
				iCWP_WPSF_Toaster.showMessage( shield_vars_secadmin.strings.nearly, false );
				// iCWP_WPSF_Growl.showMessage( shield_vars_secadmin.strings.nearly, false );
			}

			hasCheckInPlace = false;
			scheduleSecAdminCheck();
		}
		else {
			iCWP_WPSF_BodyOverlay.show();
			setTimeout( function () {
				alert( shield_vars_secadmin.strings.confirm )
				window.location.reload();
			}, 1500 );
			iCWP_WPSF_Toaster.showMessage( shield_vars_secadmin.strings.expired, response.success );
			// iCWP_WPSF_Growl.showMessage( shield_vars_secadmin.strings.expired, response.success );
		}
	};

	let scheduleSecAdminCheck = function () {
		if ( !hasCheckInPlace ) {
			setTimeout( function () {
				checkSecAdmin();
			}, timeoutInterval );
			hasCheckInPlace = true;
		}
	};

	let restrictWPOptions = function () {
		if ( shield_vars_secadmin.flags.restrict_options ) {
			shield_vars_secadmin.vars.wp_options_to_restrict.forEach( function ( element ) {
				let $element = jQuery( 'input[name=' + element + ']' );
				$element.prop( 'disabled', true );
				$element.parents( 'tr' ).addClass( 'restricted-option-row' );
				$element.parents( 'td' ).append(
					'<div style="clear:both"></div><div class="restricted-option">' +
					'<span class="dashicons dashicons-lock"></span>' +
					shield_vars_secadmin.strings.editing_restricted +
					' ' + shield_vars_secadmin.strings.unlock_link +
					'</div>' );
			} );
		}
	};

	let performSecAdminDialogLogin = function () {

		let pinInput = document.getElementById( 'SecAdminPinInput' );
		shield_vars_secadmin.ajax.sec_admin_login.sec_admin_key = pinInput.value;

		let inputContainer = document.getElementById( 'SecAdminPinInputContainer' );
		inputContainer.innerHTML = '<div class="spinner"></div>';

		jQuery.post( ajaxurl, shield_vars_secadmin.ajax.sec_admin_login, function ( response ) {
			if ( response.success ) {
				location.reload();
			}
			if ( response.data ) {
				inputContainer.innerHTML = response.data.html;
				location.reload();
			}
			else {
				inputContainer.innerHTML = 'There was an unknown error';
			}
		} );
	};

	this.initialise = function () {

		restrictWPOptions();

		if ( shield_vars_secadmin.flags.run_checks ) {
			scheduleSecAdminCheck();
			jQuery( document ).on( 'shield-sec_admin_check', handleSecAdminCheck );
		}

		jQuery( document ).on( 'submit', '#SecurityAdminForm',
			function ( evt ) {
				evt.preventDefault();
				iCWP_WPSF_StandardAjax.send_ajax_req( jQuery( evt.target ).serialize() );
				return false;
			}
		);

		jQuery( document ).on( 'click', '#SecAdminRemoveConfirmEmail',
			function ( evt ) {
				evt.preventDefault();
				if ( confirm( shield_vars_secadmin.strings.confirm_disable ) ) {
					iCWP_WPSF_StandardAjax.send_ajax_req( shield_vars_secadmin.ajax.req_email_remove );
				}
				return false;
			}
		);

		jQuery( document ).on( 'click', '#SecAdminDialog button', performSecAdminDialogLogin );
	};
}();

jQuery( document ).ready( function () {
	iCWP_WPSF_SecurityAdmin.initialise();
} );