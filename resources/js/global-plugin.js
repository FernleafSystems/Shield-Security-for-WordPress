/**
 * @var ajaxurl string
 */
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

var iCWP_WPSF_ParseAjaxResponse = new function () {
	this.parseIt = function ( raw ) {
		let parsed = {};
		try {
			parsed = JSON.parse( raw );
		}
		catch ( e ) {
			let openJsonTag = '##APTO_OPEN##';
			let closeJsonTag = '##APTO_CLOSE##';
			let start = 0;
			let end = 0;

			if ( raw.indexOf( openJsonTag ) >= 0 ) {
				start = raw.indexOf( openJsonTag ) + openJsonTag.length;
				end = raw.indexOf( closeJsonTag );
				try {
					parsed = JSON.parse( raw.substring( start, end ) );
				}
				catch ( e ) {
					start = raw.indexOf( '{' );
					end = raw.lastIndexOf( '}' ) + 1;
					parsed = JSON.parse( raw.substring( start, end ) );
				}
			}
		}
		return parsed;
	};
}();

var iCWP_WPSF_StandardAjax = new function () {

	this.send_ajax_req = function ( reqData, quiet = false, triggerEvent = '' ) {

		if ( !quiet ) {
			iCWP_WPSF_BodyOverlay.show();
		}

		reqData.apto_wrap_response = 1;

		jQuery.ajax( {
			type: "POST",
			url: ajaxurl,
			data: reqData,
			dataType: "text",
			success: function ( raw ) {
				let resp = iCWP_WPSF_ParseAjaxResponse.parseIt( raw );

				if ( typeof resp.data.show_toast === typeof undefined || resp.data.show_toast ) {

					if ( typeof resp.data.message === typeof undefined ) {
						resp.data.message = resp.success ?
							'' : 'The request failed';
					}

					if ( !quiet && resp.data.message.length > 0 ) {
						if ( typeof iCWP_WPSF_Toaster !== 'undefined' ) {
							iCWP_WPSF_Toaster.showMessage( resp.data.message, resp.success );
						}
						else {
							iCWP_WPSF_Growl.showMessage( resp.data.message, resp.success );
						}
					}
				}

				if ( triggerEvent.length > 0 ) {
					jQuery( document ).trigger( 'shield-' + triggerEvent, resp );
				}
				else if ( resp.data.page_reload ) {
					setTimeout( function () {
						location.reload();
					}, 2000 );
				}
				else {
					iCWP_WPSF_BodyOverlay.hide();
				}
			}
		} ).fail( function () {
			alert( 'Something went wrong with the request - it was either blocked or there was an error.' );
		} );
	};
}();

/** only run when HackGuard module is processing enqueues **/
if ( typeof icwp_wpsf_vars_hp !== 'undefined' ) {
	var iCWP_WPSF_HackGuard_Reinstall = new function () {
		let activeFile;
		let doActivate;
		let hrefActivate;

		this.initialise = function () {
			jQuery( document ).ready( function () {

				jQuery( 'table.wp-list-table.plugins > tbody  > tr' ).each( function ( nIndex ) {
					let $trRow = jQuery( this );
					if ( $trRow.data( 'plugin' ) !== undefined
						&& icwp_wpsf_vars_hp.reinstallable.indexOf( $trRow.data( 'plugin' ) ) >= 0 ) {
						$trRow.addClass( 'reinstallable' );
					}
				} );

				jQuery( document ).on( "click", 'tr.reinstallable .row-actions .shield-reinstall a', promptReinstall );
				jQuery( document ).on( "click", 'tr.reinstallable .row-actions .activate a', promptActivate );

				let commonSettings = {
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
						jQuery( '.ui-widget-overlay' ).on( 'click', function () {
							jQuery( this ).dialog( 'close' );
						} )
					},
					create: function () {
						// style fix for WordPress admin
						jQuery( '.ui-dialog-titlebar-close' ).addClass( 'ui-button' );
					}
				};

				let $dialogReinstall = jQuery( '#icwpWpsfReinstall' );
				commonSettings[ 'buttons' ] = [
					{
						text: icwp_wpsf_vars_hp.strings.okay_reinstall,
						id: 'btnOkayReinstall',
						click: function () {
							jQuery( this ).dialog( 'close' );
							reinstall_plugin( 1 );
						}
					},
					{
						text: icwp_wpsf_vars_hp.strings.cancel,
						id: 'btnCancel',
						click: function () {
							jQuery( this ).dialog( 'close' );
						}
					}
				];
				$dialogReinstall.dialog( commonSettings );

				let $dialogActivateReinstall = jQuery( '#icwpWpsfActivateReinstall' );
				commonSettings[ 'buttons' ] = [
					{
						text: icwp_wpsf_vars_hp.strings.reinstall_first,
						id: 'btnReinstallFirst',
						click: function () {
							jQuery( this ).dialog( 'close' );
							reinstall_plugin( 1 );
						}
					},
					{
						text: icwp_wpsf_vars_hp.strings.activate_only,
						id: 'btnActivateOnly',
						click: function () {
							window.location.assign( hrefActivate );
						}
					}
				];
				$dialogActivateReinstall.dialog( commonSettings );
			} );
		};

		var promptReinstall = function ( evt ) {
			evt.preventDefault();
			let $current = jQuery( evt.currentTarget ).closest( 'tr' );
			doActivate = 0;
			activeFile = $current.data( 'plugin' );
			hrefActivate = jQuery( 'span.activate > a', $current ).attr( 'href' )
			jQuery( '#icwpWpsfReinstall' ).dialog( 'open' );
			return false;
		};

		var promptActivate = function ( evt ) {
			evt.preventDefault();
			let $current = jQuery( evt.currentTarget ).closest( 'tr' );
			doActivate = 1;
			activeFile = $current.data( 'plugin' );
			hrefActivate = jQuery( 'span.activate > a', $current ).attr( 'href' )
			jQuery( '#icwpWpsfActivateReinstall' ).dialog( 'open' );
			return false;
		};

		var reinstall_plugin = function ( isReinstall ) {
			iCWP_WPSF_BodyOverlay.show();

			let data = icwp_wpsf_vars_hp.ajax_plugin_reinstall;
			data[ 'file' ] = activeFile;
			data[ 'reinstall' ] = isReinstall;

			jQuery.post( ajaxurl, data, function ( response ) {

			} ).always( function () {
					if ( hrefActivate ) {
						doActivate ? window.location.assign( hrefActivate ) : window.location.reload();
					}
				}
			);

			return false;
		};
	}();
	iCWP_WPSF_HackGuard_Reinstall.initialise();
}

var iCWP_WPSF_Growl = new function () {

	this.showMessage = function ( sMessage, bSuccess ) {
		let div = createDynDiv( bSuccess ? 'success' : 'failed' );
		div.show().addClass( 'shown' );
		setTimeout( function () {
			div.html( sMessage );
		}, 380 );
		setTimeout( function () {
			div.css( 'width', 0 );

			setTimeout( function () {
				div.html( '' )
				   .fadeOut();
			}, 500 );
		}, 4000 );
	};

	var createDynDiv = function ( sClass ) {
		let div = jQuery( '<div />' ).appendTo( 'body' );
		div.attr( 'id', 'icwp-growl-notice' + Math.floor( (Math.random() * 100) + 1 ) );
		div.addClass( sClass ).addClass( 'icwp-growl-notice' );
		return div;
	};

}();

let Shield_WP_Dashboard_Widget = new function () {
	let data;
	this.render = function ( refresh = 0 ) {

		let widgetContainer = jQuery( '#ShieldDashboardWidget' );

		if ( widgetContainer.length === 1 ) {
			widgetContainer.text( 'loading ...' );

			Shield_AjaxRender
			.send_ajax_req( {
				render_slug: data.ajax.render_dashboard_widget,
				refresh: refresh
			}, false )
			.then( ( response ) => {
				if ( response.success ) {
					widgetContainer.html( response.data.html );
				}
				else {
					widgetContainer.text( 'There was a problem loading the content.' )
				}
			} )
			.catch( ( error ) => {
				widgetContainer.text( 'There was a problem loading the content.' )
				console.log( error );
			} );
		}
	};
	this.initialise = function ( workingData ) {
		data = workingData;
		this.render();
	};
}();

let Shield_AjaxRender = new function () {

	let ajax_req_vars;

	this.send_ajax_req = function ( reqData, showOverlay = true ) {
		return new Promise(
			( resolve, reject ) => {
				if ( showOverlay ) {
					iCWP_WPSF_BodyOverlay.show();
				}

				reqData.apto_wrap_response = 1;

				jQuery.ajax( {
					type: 'POST',
					url: ajaxurl,
					data: jQuery.extend( ajax_req_vars, reqData ),
					dataType: "text",
					success: function ( data ) {
						if ( showOverlay ) {
							iCWP_WPSF_BodyOverlay.hide();
						}
						resolve( iCWP_WPSF_ParseAjaxResponse.parseIt( data ) );
					},
					error: function ( data ) {
						if ( showOverlay ) {
							iCWP_WPSF_BodyOverlay.hide();
						}
						reject( data )
					},
				} );
			}
		)
	};

	this.initialise = function ( params ) {
		ajax_req_vars = params;
	};
}();

var iCWP_WPSF_BodyOverlay = new function () {

	let overlays = 0;

	this.show = function () {
		overlays++;
		jQuery( 'div#icwp-fade-wrapper' ).fadeIn( 1000 );
		jQuery( 'body' ).addClass( 'shield-busy' );
	};

	this.hide = function () {
		overlays--;
		if ( overlays < 1 ) {
			overlays = 0;
			jQuery( 'div#icwp-fade-wrapper' ).stop().fadeOut();
		}
		jQuery( 'body' ).removeClass( 'shield-busy' );
	};

	this.initialise = function () {
		jQuery( '<div />' )
		.attr( 'id', 'icwp-fade-wrapper' )
		.html( '<div class="icwp-waiting"><div style="width: 4rem; height: 4rem;" class="spinner-grow text-success"></div></div>' )
		.appendTo( 'body' );
	};

}();

(function ( $ ) {

	let Shield_WP_Admin_notices = new function () {
		let self = this;
		let data;
		this.fireNoticeReq = function ( notice_action ) {
			iCWP_WPSF_StandardAjax.send_ajax_req( data.ajax[ notice_action ] );
		};

		this.initialise = function ( workingData ) {
			data = workingData;

			$( document ).on( 'click', 'a.shield_admin_notice_action', function ( evt ) {
				evt.preventDefault();
				self.fireNoticeReq( $( evt.currentTarget ).data( 'notice_action' ) );
				return false;
			} );
		};
	}();

	$( document ).ready( function () {
		iCWP_WPSF_BodyOverlay.initialise();

		if ( typeof icwp_wpsf_vars_globalplugin !== 'undefined' ) {

			let globalVars = icwp_wpsf_vars_globalplugin;

			if ( typeof globalVars.vars.ajax_render !== 'undefined' ) {
				Shield_AjaxRender.initialise( globalVars.vars.ajax_render );
			}

			/** Dashboard Widget **/
			if ( typeof globalVars.vars.dashboard_widget !== 'undefined' ) {
				Shield_WP_Dashboard_Widget.initialise( globalVars.vars.dashboard_widget );
			}

			if ( typeof globalVars.vars.notices !== 'undefined' ) {
				Shield_WP_Admin_notices.initialise( globalVars.vars.notices );
			}
		}

	} );
})( jQuery );