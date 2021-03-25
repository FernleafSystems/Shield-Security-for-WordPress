jQuery.fn.icwpWpsfChartWithFilters = function ( aOptions ) {

	let resetFilters = function ( evt ) {
		jQuery( 'select', chartForm ).each( function () {
			jQuery( this ).prop( 'selectedIndex', 0 );
		} );
		opts[ 'chart' ].renderChartFromForm( chartForm );
	};

	let submitFilters = function ( evt ) {
		evt.preventDefault();
		opts[ 'chart' ].renderChartFromForm( chartForm );
		return false;
	};

	let initialise = function () {
		jQuery( document ).ready( function () {
			chartForm = jQuery( opts[ 'selector_filter_form' ] );
			chartForm.on( 'click', 'input[type=submit]', submitFilters );
			chartForm.on( 'click', 'a#ClearForm', resetFilters );
		} );
	};

	let $oThis = this;
	let opts = jQuery.extend( {}, aOptions );
	let chartForm;
	initialise();

	return this;
};

jQuery.fn.icwpWpsfAjaxChart = function ( options ) {

	this.reloadChart = function () {
		reqRenderChart();
	};

	let createChartContainer = function () {
		chartTitleContainer = jQuery( '#CustomChartTitle' );
		chartContainer = jQuery( '<div />' ).appendTo( $oThis );
		chartContainer.addClass( 'icwpAjaxContainerChart' )
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

	let reqRenderChart = function ( reqParams ) {
		if ( bReqRunning ) {
			return false;
		}
		bReqRunning = true;

		chartContainer.html( 'Loading...' );
		chartTitleContainer.html( '' );
		jQuery.post( ajaxurl, jQuery.extend( opts[ 'ajax_render' ], opts[ 'req_params' ], reqParams ),
			function ( response ) {

				if ( !response.success ) {
					alert( response.data.message );
				}
				else {
					if ( opts[ 'show_title' ] && typeof response.data.chart.title !== typeof undefined ) {
						chartTitleContainer.html( response.data.chart.title );
					}

					chartContainer.html( '' );
					new Chartist.Line(
						$oThis[ 0 ].querySelectorAll( '.icwpAjaxContainerChart' )[ 0 ],
						response.data.chart.data,
						jQuery.extend( {
							fullWidth: true,
							showArea: false,
							chartPadding: {
								top: 10,
								right: 10,
								bottom: 10,
								left: 10
							},
							axisX: {
								showLabel: true,
								showGrid: true,
							},
							axisY: {
								onlyInteger: true,
								showLabel: true,
								labelInterpolationFnc: function ( value ) {
									return value;
								}
							},
							plugins: [
								Chartist.plugins.legend( {
									legendNames: response.data.chart.legend
								} )
							]
						}, opts[ 'chart_options' ] )
					);
				}
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
			if ( opts[ 'init_render' ] ) {
				reqRenderChart();
			}
			setHandlers();
		} );
	};

	let $oThis = this;
	let chartContainer;
	let chartTitleContainer;
	let bReqRunning = false;
	let opts = jQuery.extend( {
		init_render: true,
		show_title: false
	}, options );
	initialise();

	return this;
};