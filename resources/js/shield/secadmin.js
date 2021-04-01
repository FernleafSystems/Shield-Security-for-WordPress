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
				if ( confirm( shield_vars_secadmin.strings.confirm ) ) {
					window.location.reload();
				}
				else {
					iCWP_WPSF_BodyOverlay.hide(); // Do nothing!
				}
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

	this.initialise = function () {

		if ( shield_vars_secadmin.flags.run_checks ) {
			scheduleSecAdminCheck();
			jQuery( document ).on( 'shield-sec_admin_check', handleSecAdminCheck );
		}

		jQuery( document ).on( "submit", '#SecurityAdminForm',
			function ( evt ) {
				evt.preventDefault();
				iCWP_WPSF_StandardAjax.send_ajax_req( jQuery( evt.target ).serialize() );
				return false;
			}
		);

		jQuery( document ).on( "click", '#SecAdminRemoveConfirmEmail',
			function ( evt ) {
				evt.preventDefault();
				if ( confirm( shield_vars_secadmin.strings.are_you_sure ) ) {
					iCWP_WPSF_StandardAjax.send_ajax_req( shield_vars_secadmin.ajax.req_email_remove );
				}
				return false;
			}
		);
	};
}();

jQuery( document ).ready( function () {
	iCWP_WPSF_SecurityAdmin.initialise();
} );