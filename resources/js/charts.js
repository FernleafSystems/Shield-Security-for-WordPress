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
				new Chartist.Line( '.icwpAjaxContainerChart', oResponse.data.chart_data, {} );
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