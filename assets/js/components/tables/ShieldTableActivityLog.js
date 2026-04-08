import { ShieldTableBase } from "./ShieldTableBase";
import { PageQueryParam } from "../../util/PageQueryParam";
import { bindActivityLogMetaPopover } from "./ActivityLogMetaPopover";

export class ShieldTableActivityLog extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-ActivityLog';
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

	run() {
		super.run();
		bindActivityLogMetaPopover( this.el, this._base_data.ajax.table_action );
	}
}
