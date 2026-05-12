import { ShieldTableBase } from "./ShieldTableBase";
import { PageQueryParam } from "../../util/PageQueryParam";

export class ShieldTableTrafficLog extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-TrafficViewer';
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.select = {
			style: 'api'
		};

		const search = PageQueryParam.Retrieve( 'search' );
		if ( typeof search === 'string' && search.length > 0 ) {
			cfg.search = { search };
		}
		return cfg;
	}
}
