import $ from 'jquery';
import 'chartist/dist/index.scss';
import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { LineChart } from 'chartist';
import { ObjectOps } from "./ObjectOps";

export class Chart extends BaseService {
	chartTitleContainer;
	$chartContainer;

	constructor( initData, chart, containerSelector ) {
		super( initData );
		this.chart = chart;
		this.container = document.querySelector( containerSelector );
		this.reqRunning = false;

		if ( this.container ) {
			this.#createChartContainer();
			if ( this.chart.init_render ) {
				this.reqRenderChart();
			}
		}
	}

	reqRenderChart() {
		if ( this.reqRunning ) {
			return false;
		}

		this.reqRunning = true;
		this.$chartContainer.html( 'Loading...' );
		// this.chartTitleContainer.html( '' );

		( new AjaxService() )
		.bg( ObjectOps.Merge( this._base_data.ajax.render_summary_chart, this.chart.req_params ) )
		.then( ( resp ) => {

			if ( !resp.success ) {
				alert( resp.data.message );
			}
			else {
				if ( this.chart.show_title && typeof resp.data.chart.title !== typeof undefined ) {
					// this.chartTitleContainer.html( resp.data.chart.title );
				}

				this.$chartContainer.html( '' );

				new LineChart(
					this.container.querySelector( '.icwpAjaxContainerChart' ),
					resp.data.chart.data,
					$.extend( {
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
						}
						/* plugins: [
						// 	Chartist.plugins.legend( {
						// 		legendNames: resp.data.chart.legend
						// 	} )
						 ]*/
					}, this.chart.chart_options )
				);
			}
		} )
		.finally( () => this.reqRunning = false );
	};

	#createChartContainer() {
		// this.chartTitleContainer = $( '#CustomChartTitle' );
		this.$chartContainer = $( '<div />' ).appendTo( $( this.container ) );
		this.$chartContainer
			.addClass( 'icwpAjaxContainerChart' )
			.addClass( 'ct-chart' );
	}
}