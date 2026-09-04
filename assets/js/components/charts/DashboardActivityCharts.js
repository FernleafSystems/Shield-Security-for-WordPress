import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { LineChartRenderer } from "./LineChartRenderer";

const CHART_RETURN_DELAY_MS = 1000;

export class DashboardActivityCharts extends BaseAutoExecComponent {

	canRun() {
		return document.querySelector( '[data-dashboard-activity-charts="1"]' ) !== null;
	}

	run() {
		const chartContainer = document.querySelector( '[data-dashboard-activity-charts="1"]' );
		const chartData = JSON.parse( chartContainer.dataset.dashboardActivityChartData );
		const seriesByKey = new Map( chartData.series.map( ( series ) => [ series.key, series ] ) );

		chartContainer.querySelectorAll( '[data-dashboard-activity-chart]' ).forEach( ( tile ) => {
			const series = seriesByKey.get( tile.dataset.dashboardActivityChart );
			const output = tile.querySelector( '.summary-chart' );
			if ( !series || !output ) {
				return;
			}

			const renderer = new LineChartRenderer( output );
			renderer.render( {
				labels: chartData.labels,
				series: [ series ],
			}, {
				compact: true,
				lineColor: getComputedStyle( tile ).getPropertyValue( '--dashboard-activity-chart-color' ).trim(),
				tooltipLabel: series.label,
				tooltipOutputEl: tile.querySelector( '[data-dashboard-activity-chart-tooltip="1"]' ),
			} );
			this.resizeAfterFlip( tile, renderer );
			this.setupFlipBehavior( tile );
		} );
	}

	setupFlipBehavior( tile ) {
		const flipper = tile.querySelector( '.stat-flipper' );
		if ( !flipper ) {
			return;
		}

		const reducedMotionQuery = window.matchMedia( '(prefers-reduced-motion: reduce)' );
		let awaitingFlipCompletion = false;
		let isFlippingToChart = false;
		let returnTimer = null;
		const isActive = () => tile.matches( ':hover, :focus-visible' );
		const clearReturnTimer = () => {
			if ( returnTimer !== null ) {
				window.clearTimeout( returnTimer );
				returnTimer = null;
			}
		};
		const scheduleReturn = () => {
			if ( isActive() ) {
				return;
			}
			if ( reducedMotionQuery.matches ) {
				tile.classList.remove( 'is-chart-visible' );
				return;
			}
			clearReturnTimer();
			returnTimer = window.setTimeout( () => {
				if ( !isActive() ) {
					tile.classList.remove( 'is-chart-visible' );
				}
				returnTimer = null;
			}, CHART_RETURN_DELAY_MS );
		};
		const deferReturn = () => {
			if ( isActive() ) {
				return;
			}
			if ( isFlippingToChart ) {
				awaitingFlipCompletion = true;
				return;
			}
			scheduleReturn();
		};
		const showChart = () => {
			awaitingFlipCompletion = false;
			clearReturnTimer();
			if ( !tile.classList.contains( 'is-chart-visible' ) ) {
				tile.classList.add( 'is-chart-visible' );
				isFlippingToChart = !reducedMotionQuery.matches;
			}
		};

		tile.addEventListener( 'mouseenter', showChart );
		tile.addEventListener( 'mouseleave', deferReturn );
		tile.addEventListener( 'focusin', showChart );
		tile.addEventListener( 'focusout', () => window.requestAnimationFrame( deferReturn ) );
		flipper.addEventListener( 'transitionend', ( event ) => {
			if ( event.target !== flipper || event.propertyName !== 'transform' || !isFlippingToChart ) {
				return;
			}
			isFlippingToChart = false;
			if ( awaitingFlipCompletion ) {
				awaitingFlipCompletion = false;
				deferReturn();
			}
		} );
	}

	resizeAfterFlip( tile, renderer ) {
		const flipper = tile.querySelector( '.stat-flipper' );
		flipper?.addEventListener( 'transitionend', ( event ) => {
			if ( event.target === flipper && event.propertyName === 'transform' ) {
				renderer.resize();
			}
		} );
	}
}
