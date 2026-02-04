import $ from 'jquery';
import {
	Chart as ChartJS,
	LineController,
	LineElement,
	PointElement,
	LinearScale,
	CategoryScale,
	Tooltip
} from 'chart.js';
import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { ObjectOps } from "../../util/ObjectOps";

ChartJS.register( LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip );

const CHART_COLORS = [ '#d70206', '#f4c63d', '#f05b4f', '#d17905', '#453d3f' ];

export class Chart extends BaseComponent {
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

		( new AjaxService() )
		.bg( ObjectOps.Merge( this._base_data.ajax.render_summary_chart, this.chart.req_params ) )
		.then( ( resp ) => {

			if ( !resp.success ) {
				alert( resp.data.message );
			}
			else {
				this.$chartContainer.html( '' );

				const canvas = document.createElement( 'canvas' );
				this.container.querySelector( '.icwpAjaxContainerChart' ).appendChild( canvas );

				new ChartJS( canvas, {
					type: 'line',
					data: {
						labels: resp.data.chart.data.labels || [],
						datasets: ( resp.data.chart.data.series || [] ).map( ( series, i ) => ( {
							data: series,
							borderColor: CHART_COLORS[ i % CHART_COLORS.length ],
							borderWidth: 2,
							pointRadius: 4,
							pointHoverRadius: 6,
							tension: 0.1,
							fill: false
						} ) )
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: { display: false },
							tooltip: { enabled: true }
						},
						scales: {
							x: {
								display: true,
								ticks: {
									display: true,
									maxRotation: 0,
									callback: function( val, index, ticks ) {
										return ( index === 0 || index === ticks.length - 1 ) ? this.getLabelForValue( val ) : '';
									}
								},
								grid: { display: false }
							},
							y: {
								display: true,
								beginAtZero: true,
								ticks: {
									stepSize: 1,
									callback: ( v ) => Math.floor( v ) === v ? v : null
								}
							}
						},
						layout: {
							padding: { top: 5, right: 15, bottom: 5, left: 5 }
						}
					}
				} );
			}
		} )
		.finally( () => this.reqRunning = false );
	};

	#createChartContainer() {
		this.$chartContainer = $( '<div />' ).appendTo( $( this.container ) );
		this.$chartContainer
			.addClass( 'icwpAjaxContainerChart' )
			.addClass( 'shield-chart' );
	}
}
