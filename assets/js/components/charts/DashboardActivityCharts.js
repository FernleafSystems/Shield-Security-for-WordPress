import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { LineChartRenderer } from "./LineChartRenderer";

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
