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
				jQuery( 'html,body' ).scrollTop( 0 );
			} );
			jQuery( document ).on( 'shown.bs.tab', '#ModuleOptionsNav a.nav-link', function ( e ) {
				window.location.hash = jQuery( e.target ).data( 'bs-target' ).substr( 1 );
				jQuery( '.offcanvas-body' ).scrollTop( 0 );
			} );
		} );
	};
}();

let iCWP_WPSF_Modals = new function () {
	let workingData = {};

	this.renderModalIpAdd = function ( params = [] ) {
		iCWP_WPSF_BodyOverlay.show();
		jQuery.ajax( {
			type: "POST",
			url: ajaxurl,
			data: workingData.modal_ip_rule_add.ajax.render_ip_rule_add,
			dataType: "json",
			success: function ( raw ) {
				iCWP_WPSF_Modals.display( raw.data );
			},
		} )
			  .fail( function () {
			  } )
			  .always( function () {
				  iCWP_WPSF_BodyOverlay.hide();
			  } );
	};

	this.display = function ( params ) {
		let modal = document.getElementById( 'ShieldGeneralPurposeDialog' );
		if ( typeof params.modal_class === typeof undefined ) {
			params.modal_class = 'modal-xl';
		}
		if ( params.modal_static ) {
			modal.setAttribute( 'data-bs-backdrop', 'static' );
		}
		else {
			modal.removeAttribute( 'data-bs-backdrop' );
		}
		jQuery( '.modal-dialog', modal ).addClass( params.modal_class );
		jQuery( '.modal-title', modal ).html( params.title );
		jQuery( '.modal-body .col', modal ).html( params.body );
		(new bootstrap.Modal( modal )).show();
	};

	this.setData = function ( key, data ) {
		workingData[ key ] = data;
	};

	this.initialise = function () {
		jQuery( document ).on( 'click', '.render_ip_analysis', function ( evt ) {
			evt.preventDefault();
			iCWP_WPSF_OffCanvas.renderIpAnalysis( jQuery( evt.currentTarget ).data( 'ip' ) );
			return false;
		} );
	};
}();

var iCWP_WPSF_Toaster = new function () {

	let toasterContainer;

	this.showMessage = function ( msg, success ) {
		let $toaster = jQuery( toasterContainer );

		$toaster.removeClass( 'text-bg-success text-bg-warning' );
		$toaster.addClass( success ? 'text-bg-success' : 'text-bg-warning' );

		let $toastBody = jQuery( '.toast-body', $toaster );
		$toastBody.html( '' );

		jQuery( '<span></span>' ).html( msg )
								 .appendTo( $toastBody );

		$toaster.css( 'z-index', 100000000 );
		$toaster.on( 'hidden.bs.toast', function () {
			$toaster.css( 'z-index', -10 )
		} );
		bootstrap.Toast.getInstance( toasterContainer ).show();
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			toasterContainer = document.getElementById( 'icwpWpsfOptionsToast' );
			new bootstrap.Toast( toasterContainer, {
				autohide: true,
				delay: 3000
			} );
		} );
	};
}();
iCWP_WPSF_Toaster.initialise();

var iCWP_WPSF_OptionsFormSubmit = new function () {

	let $form;
	let workingData;
	let requestRunning = false;

	this.submit = function ( msg, success ) {
		let theDiv = createDynDiv( success ? 'success' : 'failed' );
		theDiv.fadeIn().html( msg );
		setTimeout( function () {
			theDiv.fadeOut( 5000 );
			theDiv.remove();
		}, 4000 );
	};

	/**
	 * First try with base64 and failover to lz-string upon abject failure.
	 * This works around mod_security rules that even unpack b64 encoded params and look
	 * for patterns within them.
	 */
	let sendForm = function ( useCompression = false ) {

		let formData = $form.serialize();
		if ( useCompression ) {
			formData = LZString.compress( formData );
		}

		/** Required since using dynamic AJAX loaded page content **/
		if ( !$form.data( 'mod_slug' ) ) {
			alert( 'Missing form data' );
			return false;
		}

		let reqData = jQuery.extend(
			workingData.ajax.mod_options_save,
			{
				'form_params': Base64.encode( formData ),
				'enc_params': useCompression ? 'lz-string' : 'b64',
				'apto_wrap_response': 1
			}
		);

		iCWP_WPSF_BodyOverlay.show();
		jQuery.ajax(
			{
				type: 'POST',
				url: ajaxurl,
				data: reqData,
				dataType: 'text',
				success: function ( raw ) {
					handleResponse( raw );
				},
			}
		).fail( function ( jqXHR, textStatus ) {
			if ( useCompression ) {
				handleResponse( raw );
			}
			else {
				iCWP_WPSF_Toaster.showMessage( 'The request was blocked. Retrying an alternative...', false );
				sendForm( true );
			}

		} ).always( function () {
			requestRunning = false;
			iCWP_WPSF_BodyOverlay.hide();
		} );
	};

	let handleResponse = function ( raw ) {
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

		setTimeout( function () {
			if ( $form.data( 'context' ) === 'offcanvas' ) {
				iCWP_WPSF_OffCanvas.closeCanvas();
			}
			else {
				window.location.reload();
			}
		}, 1000 );
	};

	let submitOptionsForm = function ( evt ) {
		evt.preventDefault();

		if ( requestRunning ) {
			return false;
		}
		requestRunning = true;

		$form = jQuery( this );

		let $passwordsReady = true;
		jQuery( 'input[type=password]', $form ).each( function () {
			let $pass = jQuery( this );
			let $confirm = jQuery( '#' + $pass.attr( 'id' ) + '_confirm', $form );
			if ( typeof $confirm.attr( 'id' ) !== 'undefined' ) {
				if ( $pass.val() && !$confirm.val() ) {
					$confirm.addClass( 'is-invalid' );
					alert( 'Form not submitted due to error: password confirmation field not provided.' );
					$passwordsReady = false;
				}
			}
		} );

		if ( $passwordsReady ) {
			sendForm( false );
		}

		return false;
	};

	this.initialise = function ( data ) {
		workingData = data;
		jQuery( document ).on( "submit", 'form.icwpOptionsForm', submitOptionsForm );
	};
}();

iCWP_WPSF_OptionsPages.initialise();

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
		jQuery( document ).on( 'click', 'a.shield_file_download', function ( evt ) {
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

let iCWP_WPSF_OffCanvas = new function () {

	let data;
	let offCanvas;
	let $offCanvas;
	let bsCanvas;
	let allTypes = [
		'ip_analysis',
		'meter_analysis',
		'mod_config'
	];
	let canvasTracker = [];

	this.renderConfig = function ( config_item ) {
		this.renderCanvas( {
			offcanvas_type: 'mod_config',
			config_item: config_item
		} );
	};

	this.renderIpAnalysis = function ( ip ) {
		this.renderCanvas( {
			offcanvas_type: 'ip_analysis',
			ip: ip
		} );
	};

	this.renderMeterAnalysis = function ( meter ) {
		this.renderCanvas( {
			offcanvas_type: 'meter_analysis',
			meter: meter
		} );
	};

	this.renderCanvas = function ( canvasProperties ) {
		iCWP_WPSF_BodyOverlay.show();

		canvasTracker.push( canvasProperties );

		$offCanvas.html( '<div class="d-flex justify-content-center align-items-center"><div class="spinner-border text-success m-5" role="status"><span class="visually-hidden">Loading...</span></div></div>' );
		bsCanvas.show();

		$offCanvas.removeClass( allTypes );

		jQuery.ajax(
			{
				type: "POST",
				url: ajaxurl,
				data: jQuery.extend(
					data.ajax.render_offcanvas,
					canvasProperties
				),
				dataType: "text",
				success: function ( raw ) {
					let response = iCWP_WPSF_ParseAjaxResponse.parseIt( raw );
					$offCanvas.addClass( canvasProperties.offcanvas_type );
					$offCanvas.html( response.data.html );
				}
			}
		).always(
			function () {
				iCWP_WPSF_BodyOverlay.hide();
			}
		);
	};

	this.closeCanvas = function () {
		bsCanvas.hide();
	};

	this.initialise = function ( workingData ) {
		data = workingData;
		offCanvas = document.getElementById( 'ShieldOffcanvas' );
		$offCanvas = jQuery( offCanvas );
		if ( $offCanvas.length > 0 ) {
			bsCanvas = new bootstrap.Offcanvas( document.getElementById( 'ShieldOffcanvas' ) );
			offCanvas.addEventListener( 'hidden.bs.offcanvas', event => {
				canvasTracker.pop(); // remove the one we just closed.
				if ( canvasTracker.length > 0 ) {
					this.renderCanvas( canvasTracker.pop() ); // re-open the latest.
				}
			} );
		}
	};
}();

let iCWP_WPSF_Helpscout = new function () {
	this.initialise = function ( workingData ) {
		beaconInit();
		window.Beacon( 'init', workingData.beacon_id );
		Beacon( 'navigate', '/' );

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
	};

	let beaconInit = function () {
		!function ( e, t, n ) {
			function a() {
				var e = t.getElementsByTagName( "script" )[ 0 ], n = t.createElement( "script" );
				n.type = "text/javascript", n.async = !0, n.src = "https://beacon-v2.helpscout.net", e.parentNode.insertBefore( n, e )
			}

			if ( e.Beacon = n = function ( t, n, a ) {
				e.Beacon.readyQueue.push( { method: t, options: n, data: a } )
			}, n.readyQueue = [], "complete" === t.readyState ) return a();
			e.attachEvent ? e.attachEvent( "onload", a ) : e.addEventListener( "load", a, !1 )
		}( window, document, window.Beacon || function () {
		} );
	};
}();

let jQueryDoc = jQuery( 'document' );

jQueryDoc.ready( function () {

	/** Progress Meters: */
	(new CircularProgressBar( 'pie' )).initial();

	if ( typeof icwp_wpsf_vars_plugin.components.offcanvas !== 'undefined' ) {
		iCWP_WPSF_OffCanvas.initialise( icwp_wpsf_vars_plugin.components.offcanvas );
	}

	if ( typeof icwp_wpsf_vars_plugin.components.mod_options !== 'undefined' ) {
		iCWP_WPSF_OptionsFormSubmit.initialise( icwp_wpsf_vars_plugin.components.mod_options );
	}

	if ( typeof icwp_wpsf_vars_plugin.components.helpscout !== 'undefined' ) {
		iCWP_WPSF_Helpscout.initialise( icwp_wpsf_vars_plugin.components.helpscout );
	}

	iCWP_WPSF_Modals.initialise();
	if ( typeof icwp_wpsf_vars_ips.components.modal_ip_rule_add !== 'undefined' ) {
		iCWP_WPSF_Modals.setData( 'modal_ip_rule_add', icwp_wpsf_vars_ips.components.modal_ip_rule_add );

		if ( typeof jQueryDoc.icwpWpsfIpAnalyse !== 'undefined' ) {
			jQueryDoc.icwpWpsfIpAnalyse( icwp_wpsf_vars_ips.components.ip_analysis.ajax );
		}
		if ( typeof jQueryDoc.icwpWpsfIpRules !== 'undefined' ) {
			jQueryDoc.icwpWpsfIpRules( icwp_wpsf_vars_ips.components.ip_rules );
		}
	}

	jQuery( document ).ajaxComplete( function () {
		let popoverTriggerList = [].slice.call( document.querySelectorAll( '[data-bs-toggle="popover"]' ) )
		popoverTriggerList.map( function ( popoverTriggerEl ) {
			return new bootstrap.Popover( popoverTriggerEl );
		} );

		let tooltipTriggerList = [].slice.call( document.querySelectorAll( '[data-bs-toggle="tooltip"]' ) )
		tooltipTriggerList.map( function ( tooltipTriggerEl ) {
			return new bootstrap.Tooltip( tooltipTriggerEl );
		} );
	} );

	jQuery( document ).icwpWpsfTours();
	jQuery( document ).icwpWpsfPluginNavigation();
	jQuery( '#SuperSearchBox select' ).select2( {
		minimumInputLength: 3,
		language: {
			inputTooShort: function () {
				return icwp_wpsf_vars_plugin.components.select_search.strings.enter_at_least_3_chars;
			}
		},
		placeholder: icwp_wpsf_vars_plugin.components.select_search.strings.placeholder,
		templateResult: function ( val ) {
			return (typeof val.icon === 'undefined' ? '' : ' <span class="svg-container me-2">' + val.icon + '</span>')
				+ val.text;
		},
		escapeMarkup: function ( content ) {
			return content;
		},
		ajax: {
			delay: 750,
			url: icwp_wpsf_vars_plugin.components.select_search.ajax.select_search.ajaxurl,
			contentType: "application/json; charset=utf-8",
			dataType: 'json',
			data: function ( params ) {
				let query = icwp_wpsf_vars_plugin.components.select_search.ajax.select_search;
				query.search = params.term;
				return query;
			},
			processResults: function ( response ) {
				return {
					results: response.data.results
				};
			},
		}
	} );
	jQuery( document ).on( 'select2:open', () => {
		document.querySelector( '.select2-search__field' ).focus();
	} );
	jQuery( document ).on( '#SuperSearchBox select2:select', ( evt ) => {
		let optResultData = evt.params.data;

		if ( optResultData.ip ) {
			iCWP_WPSF_OffCanvas.renderIpAnalysis( optResultData.ip );
		}
		else if ( optResultData.new_window ) {
			window.open( evt.params.data.href );
		}
		else {
			window.location.href = evt.params.data.href;
		}
	} );
	jQuery( '#IpReviewSelect' ).select2( {
		minimumInputLength: 2,
		ajax: {
			url: ajaxurl,
			method: 'POST',
			data: function ( params ) {
				let reqParams = jQuery( this ).data( 'ajaxparams' );
				reqParams.search = params.term;
				return reqParams;
			},
			processResults: function ( data ) {
				return {
					results: data.data.ips
				};
			}
		}
	} );
} );