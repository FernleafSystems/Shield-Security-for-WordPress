import { BaseService } from "./BaseService";
import { Chart } from "./Chart";

export class ChartsSummaryCharts extends BaseService {

	init() {
		this._base_data.summary_charts.charts.forEach( ( chart, ) => {
			new Chart( chart, '.summary-chart.summary-chart-' + chart.event_id );
		} )
	}
}