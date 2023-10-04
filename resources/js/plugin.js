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
			if ( $form.data( 'context' ) !== 'offcanvas' || response.data.page_reload ) {
				window.location.reload();
			}
			else {
				iCWP_WPSF_OffCanvas.closeCanvas();
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

let iCWP_Print_Div = function ( divID ){
	let divContents = document.getElementById(divID).innerHTML;
	let a = window.open('', '', 'height=800, width=800');
	a.document.write(`<html lang=en><body>${divContents}</body></html>`);
	a.print();
}

let iCWP_WPSF_OffCanvas = new function () {

	let data;
	let offCanvas;
	let $offCanvas;
	let bsCanvas;
	let canvasTracker = [];

	this.renderConfig = function ( config_item ) {
		this.renderCanvas( {
			render_slug: data.mod_config,
			config_item: config_item
		} );
	};

	this.renderIpAnalysis = function ( ip ) {
		this.renderCanvas( {
			render_slug: data.ip_analysis,
			ip: ip
		} );
	};

	this.renderIpRuleAddForm = function ( ip ) {
		this.renderCanvas( {
			render_slug: data.form_ip_rule_add
		} );
	};

	this.renderReportCreate = function ( ip ) {
		this.renderCanvas( {
			render_slug: data.form_report_create
		} );
	};

	this.renderMeterAnalysis = function ( meter ) {
		this.renderCanvas( {
			render_slug: data.meter_analysis,
			meter: meter
		} );
	};

	this.renderCanvas = function ( canvasProperties, params = {} ) {
		iCWP_WPSF_BodyOverlay.show();

		canvasTracker.push( canvasProperties );

		let spinner = document.getElementById( 'ShieldWaitSpinner' ).cloneNode( true );
		spinner.id = '';
		spinner.classList.remove( 'd-none' );

		$offCanvas.html( spinner );
		$offCanvas.removeClass( Object.values( data ) );
		bsCanvas.show();

		Shield_AjaxRender
		.send_ajax_req( canvasProperties )
		.then( ( response ) => {
			if ( response.success ) {
				$offCanvas.addClass( canvasProperties.render_slug );
				$offCanvas.html( response.data.html );
			}
			else if ( typeof response.data.error !== 'undefined' ) {
				alert( response.data.error );
			}
			else {
				alert( 'There was a problem displaying the page.' );
				console.log( response );
			}
		} )
		.catch( ( error ) => {
			console.log( error );
		} )
		.finally( ( response ) => {
			iCWP_WPSF_BodyOverlay.hide();
		} );
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

	let searchTimeout;
	let searchModal;

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

	if ( typeof jQueryDoc.icwpWpsfIpAnalyse !== 'undefined' ) {
		jQueryDoc.icwpWpsfIpAnalyse( icwp_wpsf_vars_plugin.components.ip_analysis.ajax );
	}
	if ( typeof jQueryDoc.icwpWpsfIpRules !== 'undefined' ) {
		jQueryDoc.icwpWpsfIpRules( icwp_wpsf_vars_plugin.components.ip_rules );
	}

	jQuery( document ).on( 'click', '#SuperSearchLaunch input', function ( evt ) {
		evt.preventDefault();

		if ( typeof searchModal === 'undefined' ) {
			let theModal = document.getElementById( 'ModalSuperSearchBox' );
			theModal.addEventListener( 'shown.bs.modal', event => {
				theModal.getElementsByTagName( 'input' )[ 0 ].focus();
			} )
			searchModal = new bootstrap.Modal( theModal );
		}
		searchModal.show();
		return false;
	} );

	jQuery( document ).on( 'keyup', '#ModalSuperSearchBox input.search-text', function ( evt ) {
		let $this = jQuery( evt.currentTarget );
		let current = $this.val();

		if ( searchTimeout ) {
			clearTimeout( searchTimeout );
		}

		if ( current !== '' ) {
			searchTimeout = setTimeout( function () {
				jQuery( '#ModalSuperSearchBox .modal-body' ).html(
					'<div class="d-flex justify-content-center align-items-center"><div class="spinner-border text-success m-5" role="status"><span class="visually-hidden">Loading...</span></div></div>'
				);
				Shield_AjaxRender
				.send_ajax_req( {
					render_slug: icwp_wpsf_vars_plugin.components.super_search.vars.render_slug,
					search: current
				} )
				.then( ( response ) => {
					if ( response.success ) {
						console.log( response );
						jQuery( '#ModalSuperSearchBox .modal-body' ).html( response.data.render_output );
					}
					else {
						alert( response.data.error );
					}
				} )
				.catch( ( error ) => {
					alert( 'Sorry, something went wrong with the request.' );
					console.log( error );
				} );
			}, 700 );
		}
	} );

	jQuery( document ).on( 'click', '.render_ip_analysis', function ( evt ) {
		evt.preventDefault();
		iCWP_WPSF_OffCanvas.renderIpAnalysis( jQuery( evt.currentTarget ).data( 'ip' ) );
		return false;
	} );

	jQuery( document ).on( 'click', '.option-video', function ( evt ) {
		evt.preventDefault();
		BigPicture( {
			el: evt.target,
			vimeoSrc: jQuery( evt.currentTarget ).data( 'vimeoid' ),
		} );
		return false;
	} );

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

	jQuery( document ).on( 'click', 'a.shield_dynamic_action_button', function ( evt ) {
		evt.preventDefault()
		let data = evt.currentTarget.dataset;
		if ( !(data[ 'confirm' ] ?? false) || confirm( 'Are you sure?' ) ) {
			delete data[ 'confirm' ];
			iCWP_WPSF_StandardAjax.send_ajax_req( data );
		}
		return false;
	} );

	/** TODO: test this fully */
	jQuery( document ).on( "submit", 'form.icwp-form-dynamic-action', function ( evt ) {
		evt.currentTarget.action = window.location.href;
	} );

	jQuery( document ).on( 'click', '.progress-meter .description', function ( evt ) {
		let $this = jQuery( this );
		jQuery( '.toggleable', $this ).toggleClass( 'hidden' );
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

(function ( $ ) {
	$( document ).ready( function () {

		let requestRunning = false;

		jQuery( document ).on( "submit", 'form#FileScanMalaiQuery', function ( evt ) {
			evt.preventDefault();

			if ( requestRunning ) {
				return false;
			}
			requestRunning = true;

			let ready = true;

			let $form = $( this );
			$( 'input[type=checkbox]', $form ).each( function () {
				if ( !$( this ).is( ':checked' ) ) {
					ready = ready && false;
				}
			} );

			if ( !ready ) {
				alert( 'Please check the box to agree.' );
				requestRunning = false;
			}
			else {
				iCWP_WPSF_BodyOverlay.show();
				jQuery.ajax(
					{
						type: 'POST',
						url: ajaxurl,
						data: $( this ).serialize(),
						dataType: 'text',
						success: function ( raw ) {
							let resp = iCWP_WPSF_ParseAjaxResponse.parseIt( raw );
							alert( resp.data.message );
						},
					}
				).fail( function ( jqXHR, textStatus ) {
				} ).always( function () {
					requestRunning = false;
					iCWP_WPSF_BodyOverlay.hide();
				} );
			}

			return false;

		} );
	} );
})( jQuery );