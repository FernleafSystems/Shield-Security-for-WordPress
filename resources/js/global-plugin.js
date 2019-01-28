var iCWP_WPSF_JSErrorTrack = new function () {
	var bHasError = false;
	this.initialise = function () {
		window.onerror = function ( error ) {
			bHasError = true;
		};
	};
	this.hasError = function () {
		return bHasError;
	};
}();
iCWP_WPSF_JSErrorTrack.initialise();

var iCWP_WPSF_SecurityAdmin = new function () {

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( document ).on( "submit", '#SecurityAdminForm', submit_admin_access );
		} );
	};

	var submit_admin_access = function ( event ) {
		iCWP_WPSF_BodyOverlay.show();
		event.preventDefault();

		var $oForm = jQuery( event.target );

		jQuery.post( ajaxurl, $oForm.serialize(), function ( oResponse ) {
			if ( oResponse.success ) {
				location.reload( true );
			}
			else {
				alert( 'Security Access Key was not recognised.' );
				iCWP_WPSF_BodyOverlay.hide();
			}
		} ).always( function () {
			}
		);

		return false;
	};
}();

/** only run when HackGuard module is processing enqueues **/
if ( typeof icwp_wpsf_vars_hp !== 'undefined' ) {
	var iCWP_WPSF_HackGuard_Reinstall = new function () {

		var sActiveFile;
		var bActivate;

		this.initialise = function () {
			jQuery( document ).ready( function () {

				var $oTr;
				jQuery( 'table.wp-list-table.plugins > tbody  > tr' ).each( function ( nIndex ) {
					$oTr = jQuery( this );
					if ( $oTr.data( 'plugin' ) !== undefined
						&& icwp_wpsf_vars_hp.reinstallable.indexOf( $oTr.data( 'plugin' ) ) >= 0 ) {
						$oTr.addClass( 'reinstallable' );
					}
				} );

				jQuery( document ).on( "click", 'tr.reinstallable .row-actions .icwp-reinstall a', promptReinstall );
				jQuery( document ).on( "click", 'tr.reinstallable .row-actions .activate a', promptActivate );

				var oShareSettings = {
					title: 'Re-Install Plugin',
					dialogClass: 'wp-dialog',
					autoOpen: false,
					draggable: false,
					width: 'auto',
					modal: true,
					resizable: false,
					closeOnEscape: true,
					position: {
						my: "center",
						at: "center",
						of: window
					},
					open: function () {
						// close dialog by clicking the overlay behind it
						jQuery( '.ui-widget-overlay' ).bind( 'click', function () {
							jQuery( this ).dialog( 'close' );
						} )
					},
					create: function () {
						// style fix for WordPress admin
						jQuery( '.ui-dialog-titlebar-close' ).addClass( 'ui-button' );
					}
				};

				var $oReinstallDialog = jQuery( '#icwpWpsfReinstall' );
				oShareSettings[ 'buttons' ] = {
					"Okay, Re-Install It": function () {
						jQuery( this ).dialog( "close" );
						reinstall_plugin( 1 );
					},
					"Cancel": function () {
						jQuery( this ).dialog( "close" );
					}
				};
				$oReinstallDialog.dialog( oShareSettings );

				var $oActivateReinstallDialog = jQuery( '#icwpWpsfActivateReinstall' );
				oShareSettings[ 'buttons' ] = {
					"Re-Install First, Then Activate": function () {
						jQuery( this ).dialog( "close" );
						reinstall_plugin( 1 );
					},
					"Activate Only": function () {
						jQuery( this ).dialog( "close" );
						reinstall_plugin( 0 );
					}
				};
				$oActivateReinstallDialog.dialog( oShareSettings );
			} );
		};

		var promptReinstall = function ( event ) {
			event.preventDefault();
			bActivate = 0;
			sActiveFile = jQuery( event.target ).closest( 'tr' ).data( 'plugin' );
			jQuery( '#icwpWpsfReinstall' ).dialog( 'open' );
			return false;
		};

		var promptActivate = function ( event ) {
			event.preventDefault();
			bActivate = 1;
			sActiveFile = jQuery( event.target ).closest( 'tr' ).data( 'plugin' );
			jQuery( '#icwpWpsfActivateReinstall' ).dialog( 'open' );
			return false;
		};

		var reinstall_plugin = function ( bReinstall ) {
			iCWP_WPSF_BodyOverlay.show();

			var $aData = icwp_wpsf_vars_hp.ajax_plugin_reinstall;
			$aData[ 'file' ] = sActiveFile;
			$aData[ 'reinstall' ] = bReinstall;
			$aData[ 'activate' ] = bActivate;

			jQuery.post( ajaxurl, $aData, function ( oResponse ) {

			} ).always( function () {
					location.reload( true );
					bActivate = null;
				}
			);

			return false;
		};
	}();
	iCWP_WPSF_HackGuard_Reinstall.initialise();
}

if ( typeof icwp_wpsf_vars_lg !== 'undefined' ) {
	var iCWP_WPSF_LoginGuard_BackupCodes = new function () {
		this.initialise = function () {
			jQuery( document ).ready( function () {
				jQuery( document ).on( "click", "a#IcwpWpsfGenBackupLoginCode", genBackupCode );
				jQuery( document ).on( "click", "a#IcwpWpsfDelBackupLoginCode", deleteBackupCode );
			} );
		};

		var genBackupCode = function ( event ) {
			event.preventDefault();
			iCWP_WPSF_BodyOverlay.show();

			jQuery.post( ajaxurl, icwp_wpsf_vars_lg.ajax_gen_backup_codes,
				function ( oResponse ) {
					alert( 'Your login backup code: ' + oResponse.data.code );
				}
			).always( function () {
					location.reload( true );
				}
			);

			return false;
		};

		var deleteBackupCode = function ( event ) {
			event.preventDefault();
			iCWP_WPSF_BodyOverlay.show();

			jQuery.post( ajaxurl, icwp_wpsf_vars_lg.ajax_del_backup_codes,
				function ( oResponse ) {
				}
			).always( function () {
					location.reload( true );
					// iCWP_WPSF_BodyOverlay.hide();
				}
			);

			return false;
		};
	}();
	iCWP_WPSF_LoginGuard_BackupCodes.initialise();
}

/** TODO: THIS AJAX IS NOT COMPLETE **/
var iCWP_WPSF_Autoupdates = new function () {

	var bRequestCurrentlyRunning = false;

	var togglePluginUpdate = function ( event ) {
		if ( bRequestCurrentlyRunning ) {
			return false;
		}

		$oInput = jQuery( this );

		if ( $oInput.data( 'disabled' ) !== 'no' ) {
			iCWP_WPSF_Growl.showMessage( $oInput.data( 'disabled' ), false );
			return false;
		}

		return sendTogglePluginAutoupdate( $oInput.data( 'pluginfile' ), $oInput.data( 'nonce' ) );
	};

	var sendTogglePluginAutoupdate = function ( sPluginFile ) {
		bRequestCurrentlyRunning = true;

		var requestData = {
			'action': 'icwp_wpsf_TogglePluginAutoupdate',
			'pluginfile': sPluginFile
		};

		jQuery.post( ajaxurl, requestData,
			function ( oResponse ) {
				iCWP_WPSF_Growl.showMessage( oResponse.data.message, oResponse.success );
			}
		).always( function () {
				bRequestCurrentlyRunning = false;
			}
		);

		return true;
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( document ).on( "click", "input.icwp-autoupdate-plugin", togglePluginUpdate );
		} );
	};

}();

var iCWP_WPSF_Growl = new function () {

	this.showMessage = function ( sMessage, bSuccess ) {
		var $oDiv = createDynDiv( bSuccess ? 'success' : 'failed' );
		$oDiv.show().addClass( 'shown' );
		setTimeout( function () {
			$oDiv.html( sMessage );
		}, 380 );
		setTimeout( function () {
			$oDiv.css( 'width', 0 );

			setTimeout( function () {
				$oDiv.html( '' )
					 .fadeOut();
			}, 500 );
		}, 4000 );
	};

	/**
	 */
	var createDynDiv = function ( sClass ) {
		var $oDiv = jQuery( '<div />' ).appendTo( 'body' );
		$oDiv.attr( 'id', 'icwp-growl-notice' + Math.floor( (Math.random() * 100) + 1 ) );
		$oDiv.addClass( sClass ).addClass( 'icwp-growl-notice' );
		return $oDiv;
	};

}();

var iCWP_WPSF_BodyOverlay = new function () {

	this.show = function () {
		jQuery( 'div#icwp-fade-wrapper' ).fadeIn( 1000 );
	};

	this.hide = function () {
		jQuery( 'div#icwp-fade-wrapper' ).stop().fadeOut();
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			var $oDiv = jQuery( '<div />' )
			.attr( 'id', 'icwp-fade-wrapper' )
			.html( '<div class="icwp-waiting"><div style="width: 4rem; height: 4rem;" class="spinner-grow text-success"></div></div>' )
			.appendTo( 'body' );
		} );
	};

}();

// iCWP_WPSF_Autoupdates.initialise();
iCWP_WPSF_BodyOverlay.initialise();
iCWP_WPSF_SecurityAdmin.initialise();

if ( typeof icwp_wpsf_vars_plugin !== 'undefined' ) {

	var iCWP_WPSF_Plugin_Deactivate_Survey = new function () {

		this.initialise = function () {
			jQuery( document ).ready( function () {

				if ( !iCWP_WPSF_JSErrorTrack.hasError() ) {
					jQuery( document ).on( "click",
						'[data-plugin="' + icwp_wpsf_vars_plugin.file + '"] span.deactivate a',
						promptSurvey
					);
				}

				var oShareSettings = {
					title: 'Care To Share?',
					dialogClass: 'wp-dialog',
					autoOpen: false,
					draggable: false,
					width: 'auto',
					modal: true,
					resizable: false,
					closeOnEscape: true,
					position: {
						my: "center",
						at: "center",
						of: window
					},
					open: function () {
						// close dialog by clicking the overlay behind it
						jQuery( '.ui-widget-overlay' ).bind( 'click', function () {
							jQuery( this ).dialog( 'close' );
						} )
					},
					create: function () {
						// style fix for WordPress admin
						jQuery( '.ui-dialog-titlebar-close' ).addClass( 'ui-button' );
					},
					close: function () {
						window.location.href = icwp_wpsf_vars_plugin.hrefs.deactivate;
					}
				};

				var $oSurveyDialog = jQuery( '#icwpWpsfSurvey' );
				oShareSettings[ 'buttons' ] = {
					"Close (I don't want to help)": function () {
						jQuery( this ).dialog( "close" );
					},
					"Yes (Send my feedback)": function () {
						send_survey_deactivate();
						jQuery( this ).dialog( "close" );
					}
				};
				$oSurveyDialog.dialog( oShareSettings );
			} );
		};

		var promptSurvey = function ( event ) {
			event.preventDefault();
			iCWP_WPSF_BodyOverlay.show();
			jQuery( '#icwpWpsfSurvey' ).dialog( 'open' );
			return false;
		};

		var send_survey_deactivate = function () {

			var $aData = icwp_wpsf_vars_plugin.ajax.send_deactivate_survey;
			jQuery.each( jQuery( '#icwpWpsfSurveyForm' ).serializeArray(),
				function ( _, kv ) {
					$aData[ kv.name ] = kv.value;
				}
			);

			jQuery.post( ajaxurl, $aData );
			setTimeout( function () {
			}, 2000 ); // give the request time to complete

			return false;
		};
	}();
	iCWP_WPSF_Plugin_Deactivate_Survey.initialise();
}