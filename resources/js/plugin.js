var iCWP_WPSF_OptionsPages = new function () {

	var showWaiting = function ( event ) {
		iCWP_WPSF_BodyOverlay.show();
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( document ).on( 'click', 'a.nav-link.module', showWaiting );

			/** Track active tab */
			jQuery( document ).on( 'click', '#ModuleOptionsNav a.nav-link', function ( e ) {
				e.preventDefault();
				jQuery( this ).tab( 'show' );
				jQuery( 'html,body' ).scrollTop( 0 );
			} );
			jQuery( document ).on( 'shown.bs.tab', '#ModuleOptionsNav a.nav-link', function ( e ) {
				window.location.hash = jQuery( e.target ).attr( "href" ).substr( 1 );
			} );

			jQuery( document ).on( 'odp-optsrender', onOptsTabRender );
		} );
	};

	var onOptsTabRender = function ( evt ) {
		var sActiveTabHash = window.location.hash;
		if ( typeof sActiveTabHash !== 'undefined' ) {
			jQuery( '#ModuleOptionsNav a[href="' + sActiveTabHash + '"]' ).tab( 'show' );
			jQuery( 'html,body' ).scrollTop( 0 );
		}

		jQuery( function () {
			jQuery( 'a.section_title_info' ).popover( {
				placement: 'bottom',
				trigger: 'click',
				delay: 50,
				html: true
			} );
			jQuery( '[data-toggle="tooltip"]' ).tooltip( {
				placement: 'left',
				trigger: 'hover focus',
				delay: 150,
				html: false
			} );
		} );
	};
}();

let iCWP_WPSF_OptsPageRender = new function () {
	this.renderForm = function ( reqData ) {
		iCWP_WPSF_BodyOverlay.show();
		jQuery.ajax(
			{
				type: "POST",
				url: ajaxurl,
				data: reqData,
				dataType: "text",
				success: function ( rawResponse ) {
					let response = iCWP_WPSF_ParseAjaxResponse.parseIt( rawResponse );
					jQuery( '#ColumnOptions .content-options' )
					.html( response.data.html )
					.trigger( 'odp-optsrender' );
				}
			}
		).always(
			function () {
				iCWP_WPSF_BodyOverlay.hide();
			}
		);
	};
}();

var iCWP_WPSF_Toaster = new function () {

	this.showMessage = function ( msg, success ) {
		let $oNewToast = jQuery( '#icwpWpsfOptionsToast' );
		let $oToastBody = jQuery( '.toast-body', $oNewToast );
		$oToastBody.html( '' );

		jQuery( '<span></span>' ).html( msg )
								 .addClass( success ? 'text-dark' : 'text-danger' )
								 .appendTo( $oToastBody );

		$oNewToast.css( 'z-index', 1000 );
		$oNewToast.toast( 'show' );
		$oNewToast.on( 'hidden.bs.toast', function () {
			$oNewToast.css( 'z-index', -10 )
		} );
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( '.toast.icwp-toaster' ).toast( {
				autohide: true,
				delay: 3000
			} );
		} );
	};
}();
iCWP_WPSF_Toaster.initialise();

var iCWP_WPSF_OptionsFormSubmit = new function () {

	let bRequestCurrentlyRunning = false;
	var reqParams = icwp_wpsf_vars_base.ajax.mod_options;

	this.submit = function ( msg, success ) {
		let $oDiv = createDynDiv( success ? 'success' : 'failed' );
		$oDiv.fadeIn().html( msg );
		setTimeout( function () {
			$oDiv.fadeOut( 5000 );
			$oDiv.remove();
		}, 4000 );
	};

	this.updateAjaxReqParams = function ( params ) {
		reqParams = params;
	};

	/**
	 * First try with base64 and failover to lz-string upon abject failure.
	 * This works around mod_security rules that even unpack b64 encoded params and look
	 * for patterns within them.
	 */
	var sendForm = function ( $form, useCompression = false ) {

		let formData = $form.serialize();
		if ( useCompression ) {
			formData = LZString.compress( formData );
		}

		/** Required since using dynamic AJAX loaded page content **/
		if ( !$form.data( 'mod_slug' ) ) {
			alert( 'Missing form data' );
			return false;
		}
		reqParams.mod_slug = $form.data( 'mod_slug' );
		let reqs = jQuery.extend(
			reqParams,
			{
				'form_params': Base64.encode( formData ),
				'enc_params': useCompression ? 'lz-string' : 'b64',
				'apto_wrap_response': 1
			}
		);

		jQuery.ajax(
			{
				type: "POST",
				url: ajaxurl,
				data: reqs,
				dataType: "text",
				success: function ( raw ) {
					handleResponse( raw );
				},
			}
		).fail( function () {
			if ( useCompression ) {
				handleResponse( raw );
			}
			else {
				iCWP_WPSF_Toaster.showMessage( 'The request was blocked. Retrying an alternative...', false );
				sendForm( $form, true );
			}

		} ).always( function () {
			bRequestCurrentlyRunning = false;
			setTimeout( function () {
				location.reload();
			}, 1000 );
		} );

	};

	var handleResponse = function ( raw ) {
		let response = iCWP_WPSF_ParseAjaxResponse.parseIt( raw );
		let msg;
		if ( response === null || typeof response.data === 'undefined'
			|| typeof response.data.message === 'undefined' ) {
			msg = response.success ? 'Success' : 'Failure';
		}
		else {
			msg = response.data.message;
		}
		iCWP_WPSF_Toaster.showMessage( msg, response.success );
	};

	var submitOptionsForm = function ( event ) {
		iCWP_WPSF_BodyOverlay.show();

		if ( bRequestCurrentlyRunning ) {
			return false;
		}
		bRequestCurrentlyRunning = true;
		event.preventDefault();

		var $form = jQuery( this );

		var $passwordsReady = true;
		jQuery( 'input[type=password]', $form ).each( function () {
			var $oPass = jQuery( this );
			var $oConfirm = jQuery( '#' + $oPass.attr( 'id' ) + '_confirm', $form );
			if ( typeof $oConfirm.attr( 'id' ) !== 'undefined' ) {
				if ( $oPass.val() && !$oConfirm.val() ) {
					$oConfirm.addClass( 'is-invalid' );
					alert( 'Form not submitted due to error: password confirmation field not provided.' );
					$passwordsReady = false;
				}
			}
		} );

		if ( $passwordsReady ) {
			sendForm( $form, false );
		}
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( document ).on( "submit", 'form.icwpOptionsForm', submitOptionsForm );
		} );
	};
}();

iCWP_WPSF_OptionsPages.initialise();
iCWP_WPSF_OptionsFormSubmit.initialise();

jQuery.fn.icwpWpsfAjaxTable = function ( aOptions ) {

	this.reloadTable = function () {
		renderTableRequest();
	};

	var createTableContainer = function () {
		$oTableContainer = jQuery( '<div />' ).appendTo( $oThis );
		$oTableContainer.addClass( 'icwpAjaxTableContainer' );
	};

	var refreshTable = function ( evt ) {
		evt.preventDefault();

		var query = this.search.substring( 1 );
		var aTableRequestParams = {
			paged: extractQueryVars( query, 'paged' ) || 1,
			order: extractQueryVars( query, 'order' ) || 'desc',
			orderby: extractQueryVars( query, 'orderby' ) || 'created_at',
			tableaction: jQuery( evt.currentTarget ).data( 'tableaction' )
		};

		renderTableRequest( aTableRequestParams );
	};

	var extractQueryVars = function ( query, variable ) {
		var vars = query.split( "&" );
		for ( var i = 0; i < vars.length; i++ ) {
			var pair = vars[ i ].split( "=" );
			if ( pair[ 0 ] === variable ) {
				return pair[ 1 ];
			}
		}
		return false;
	};

	this.renderTableFromForm = function ( $oForm ) {
		renderTableRequest( { 'form_params': $oForm.serialize() } );
	};

	var renderTableRequest = function ( aTableRequestParams ) {
		if ( bReqRunning ) {
			return false;
		}
		bReqRunning = true;
		iCWP_WPSF_BodyOverlay.show();

		jQuery.post( ajaxurl, jQuery.extend( aOpts[ 'ajax_render' ], aOpts[ 'req_params' ], aTableRequestParams ),
			function ( oResponse ) {
				$oTableContainer.html( oResponse.data.html )
			}
		).always(
			function () {
				bReqRunning = false;
				iCWP_WPSF_BodyOverlay.hide();
			}
		);
	};

	var setHandlers = function () {
		$oThis.on( "click", 'a.tableActionRefresh', refreshTable );
		$oThis.on( 'click', '.tablenav-pages a, .manage-column.sortable a, .manage-column.sorted a', refreshTable );

		var timer;
		var delay = 1000;
		jQuery( document ).on( 'keyup', 'input[name=paged]', function ( event ) {
			// If user hit enter, we don't want to submit the form
			// We don't preventDefault() for all keys because it would
			// also prevent to get the page number!
			if ( 13 === event.which )
				event.preventDefault();

			// This time we fetch the variables in inputs
			var $eThis = jQuery( event.currentTarget );
			var aTableRequestParams = {
				paged: isNaN( $eThis.val() ) ? 1 : $eThis.val(),
				order: jQuery( 'input[name=order]', $eThis ).val() || 'desc',
				orderby: jQuery( 'input[name=orderby]', $eThis ).val() || 'created_at'
			};
			// Now the timer comes to use: we wait a second after
			// the user stopped typing to actually send the call. If
			// we don't, the keyup event will trigger instantly and
			// thus may cause duplicate calls before sending the intended
			// value
			renderTableRequest( aTableRequestParams );
		} );
	};

	var initialise = function () {
		jQuery( document ).ready( function () {
			createTableContainer();
			renderTableRequest();
			setHandlers();
		} );
	};

	var $oThis = this;
	var $oTableContainer;
	var bReqRunning = false;
	var aOpts = jQuery.extend( {}, aOptions );
	initialise();

	return this;
};

if ( typeof icwp_wpsf_vars_plugin !== 'undefined' ) {

	jQuery( document ).ready( function () {
		jQuery( document ).on( 'click', 'a.shield_file_download, a.shield_file_download ', function ( evt ) {
			evt.preventDefault();
			/** Cache busting **/
			let url = jQuery( this ).attr( 'href' ) + '&rand='
				+ Math.floor( 10000 * Math.random() );
			jQuery.fileDownload( url, {
				preparingMessageHtml: icwp_wpsf_vars_plugin.strings.downloading_file,
				failMessageHtml: icwp_wpsf_vars_plugin.strings.downloading_file_problem
			} );
			return false;
		} );
	} );
}

jQuery( document ).ready( function () {
	jQuery( document ).icwpWpsfTours();
	jQuery( document ).icwpWpsfPluginNavigation();
	jQuery( '.select2picker' ).select2( {
		width: 'resolve'
	} );

	jQuery( document ).on( 'click', 'a.beacon-article', function ( evt ) {
		evt.preventDefault();
		let link = jQuery( evt.currentTarget );
		let id = link.data( 'beacon-article-id' );
		if ( id ) {
			let format = '';
			if ( link.data( 'beacon-article-format' ) ) {
				format = link.data( 'beacon-article-format' );
			}
			Beacon( 'article', String( id ), { type: format } );
		}
		return false;
	} );
} );