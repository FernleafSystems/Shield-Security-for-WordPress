jQuery.fn.icwpWpsfChartWithFilters = function ( aOptions ) {

	let resetFilters = function ( evt ) {
		jQuery( 'input[type=text]', $oForm ).each( function () {
			jQuery( this ).val( '' );
		} );
		jQuery( 'select', $oForm ).each( function () {
			jQuery( this ).prop( 'selectedIndex', 0 );
		} );
		jQuery( 'input[type=checkbox]', $oForm ).each( function () {
			jQuery( this ).prop( 'checked', false );
		} );
		aOpts[ 'table' ].renderChartFromForm( $oForm );
	};

	let submitFilters = function ( evt ) {
		evt.preventDefault();
		aOpts[ 'table' ].renderChartFromForm( $oForm );
		return false;
	};

	let initialise = function () {
		jQuery( document ).ready( function () {
			$oForm = jQuery( aOpts[ 'selector_filter_form' ] );
			$oForm.on( 'change', submitFilters );
			$oForm.on( 'click', 'a#ClearForm', resetFilters );
		} );
	};

	let $oThis = this;
	let aOpts = jQuery.extend( {}, aOptions );
	let $oForm;
	initialise();

	return this;
};

jQuery.fn.icwpWpsfAjaxChart = function ( aOptions ) {

	this.reloadChart = function () {
		reqRenderChart();
	};

	let createTableContainer = function () {
		$oChartContainer = jQuery( '<div />' ).appendTo( $oThis );
		$oChartContainer.addClass( 'icwpAjaxContainerChart' )
						.addClass( 'ct-chart' );
	};

	let refreshChart = function ( event ) {
		event.preventDefault();
		let aChartRequestParams = {};
		reqRenderChart( aChartRequestParams );
	};

	this.renderChartFromForm = function ( $oForm ) {
		reqRenderChart( { 'form_params': $oForm.serialize() } );
	};

	let reqRenderChart = function ( aTableRequestParams ) {
		if ( bReqRunning ) {
			return false;
		}
		bReqRunning = true;

		jQuery.post( ajaxurl, jQuery.extend( aOpts[ 'ajax_render' ], aOpts[ 'req_params' ], aTableRequestParams ),
			function ( oResponse ) {
				new Chartist.Line(
					'.icwpAjaxContainerChart',
					oResponse.data.chart.data,
					{
						plugins: [
							Chartist.plugins.legend( {
								legendNames: oResponse.data.chart.legend_names
							} )
						]
					}
				);
			}
		).always(
			function () {
				bReqRunning = false;
			}
		);
	};

	let setHandlers = function () {
	};

	let initialise = function () {
		jQuery( document ).ready( function () {
			createTableContainer();
			reqRenderChart();
			setHandlers();
		} );
	};

	let $oThis = this;
	let $oChartContainer;
	let bReqRunning = false;
	let aOpts = jQuery.extend( {}, aOptions );
	initialise();

	return this;
};