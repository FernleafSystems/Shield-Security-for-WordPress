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
		aOpts[ 'chart' ].renderChartFromForm( $oForm );
	};

	let submitFilters = function ( evt ) {
		evt.preventDefault();
		aOpts[ 'chart' ].renderChartFromForm( $oForm );
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

	let createChartContainer = function () {
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

	let reqRenderChart = function ( aRequestParams ) {
		if ( bReqRunning ) {
			return false;
		}
		bReqRunning = true;

		$oChartContainer.html( 'Loading...' );

		jQuery.post( ajaxurl, jQuery.extend( aOpts[ 'ajax_render' ], aOpts[ 'req_params' ], aRequestParams ),
			function ( oResponse ) {

				$oChartContainer.html('');
				new Chartist.Line(
					$oThis.selector+' .icwpAjaxContainerChart',
					oResponse.data.chart.data,
					{
						height: '100px',
						fullWidth: true,
						showArea: false,
						chartPadding: {
							top: 10,
							right: 10,
							bottom: 10,
							left: 10
						},
						axisX: {
							offset: 5,
							showLabel: false,
							showGrid: false,
						},
						axisY: {
							offset: 20,
							onlyInteger: true,
							showLabel: true,
							labelInterpolationFnc: function ( value ) {
								return value;
							}
						},
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
			createChartContainer();
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