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

const CHART_LINE_COLOR_FALLBACK = '#008000';
const CHART_POINT_COLOR_FALLBACK = '#008000';

export class Chart extends BaseComponent {
	$chartContainer;

	constructor( initData, chart, containerSelector ) {
		super( initData );
		this.chart = chart;
		this.container = document.querySelector( containerSelector );
		this.reqRunning = false;

		if ( this.container ) {
			this.#createChartContainer();
		}
	}

	shouldInitRender() {
		return !!( this.container && this.chart.init_render );
	}

	buildBatchItem() {
		return {
			id: `summary-chart-${this.chart.event_id}`,
			request: this.buildRequestData(),
		};
	}

	buildRequestData() {
		return ObjectOps.Merge( this._base_data.ajax.render_summary_chart, this.chart.req_params );
	}

	renderLoading() {
		if ( this.$chartContainer ) {
			this.$chartContainer.html( 'Loading...' );
		}
	}

	renderError( message = 'There was a problem loading this chart.' ) {
		if ( this.$chartContainer ) {
			this.$chartContainer.html( message );
		}
	}

	handleBatchSuccess( result ) {
		this.renderFromResponse( result );
	}

	handleBatchError( result ) {
		this.renderError( result?.data?.message || 'There was a problem loading this chart.' );
	}

	reqRenderChart() {
		if ( this.reqRunning || !this.container ) {
			return false;
		}

		this.reqRunning = true;
		this.renderLoading();

		( new AjaxService() )
		.bg( this.buildRequestData() )
		.then( ( resp ) => {
			this.renderFromResponse( resp );
		} )
		.finally( () => this.reqRunning = false );
	};

	renderFromResponse( resp = {} ) {
		const payload = ( resp?.data && typeof resp.data === 'object' ) ? resp.data : resp;
		const success = typeof resp?.success === 'boolean' ? resp.success : !!payload?.success;

		if ( !success ) {
			this.renderError( payload?.message || 'There was a problem loading this chart.' );
			return;
		}

		this.$chartContainer.html( '' );

		const chartData = payload?.chart?.data || {};
		const chartColors = this.getChartColors();

		const canvas = document.createElement( 'canvas' );
		this.container.querySelector( '.icwpAjaxContainerChart' ).appendChild( canvas );

		new ChartJS( canvas, {
			type: 'line',
			data: {
				labels: chartData.labels || [],
				datasets: ( chartData.series || [] ).map( ( series ) => ( {
					data: series,
					borderColor: chartColors.lineColor,
					borderWidth: 1.5,
					pointBackgroundColor: chartColors.pointColor,
					pointBorderColor: chartColors.pointColor,
					pointRadius: 2,
					pointHoverRadius: 3,
					pointHitRadius: 6,
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

	getChartColors() {
		const cssSource = this.container?.querySelector( '.shield-chart' ) || this.container || document.documentElement;
		const css = window.getComputedStyle( cssSource );
		return {
			lineColor: css.getPropertyValue( '--shield-chart-line-color' ).trim() || CHART_LINE_COLOR_FALLBACK,
			pointColor: css.getPropertyValue( '--shield-chart-point-color' ).trim() || CHART_POINT_COLOR_FALLBACK
		};
	}

	#createChartContainer() {
		this.$chartContainer = $( '<div />' ).appendTo( $( this.container ) );
		this.$chartContainer
			.addClass( 'icwpAjaxContainerChart' )
			.addClass( 'shield-chart' );
	}
}
