import { BaseComponent } from "../BaseComponent";
import { Chart } from "./Chart";
import { AjaxBatchService } from "../services/AjaxBatchService";

export class ChartsSummaryCharts extends BaseComponent {

	init() {
		const charts = this._base_data.summary_charts.charts.map( ( chart ) =>
			new Chart( this._base_data.summary_charts, chart, '.summary-chart.summary-chart-' + chart.event_id )
		);

		const batchData = this._base_data.summary_charts.ajax.batch_requests || null;
		if ( batchData ) {
			const batchService = new AjaxBatchService( batchData );

			charts.forEach( ( chart ) => {
				if ( chart.shouldInitRender() ) {
					const batchItem = chart.buildBatchItem();
					chart.renderLoading();
					batchService.add( {
						id: batchItem.id,
						request: batchItem.request,
						onSuccess: ( result ) => chart.handleBatchSuccess( result ),
						onError: ( result ) => chart.handleBatchError( result ),
					} );
				}
			} );

			batchService.flush().finally();
		}
		else {
			charts.forEach( ( chart ) => {
				if ( chart.shouldInitRender() ) {
					chart.reqRenderChart();
				}
			} );
		}
	}
}
