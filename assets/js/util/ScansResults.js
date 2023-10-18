import $ from 'jquery';
import { BaseService } from "./BaseService";
import { ScansActionAbandonedPlugins } from "./ScansActionAbandonedPlugins";
import { ShieldTablesScanResultsHandler } from "./ShieldTablesScanResultsHandler";

export class ScansResults extends BaseService {

	init() {
		$( '.nav-vertical a[data-bs-toggle="tab"]' ).on( 'shown.bs.tab', ( evt ) => {
			window.scrollTo( { top: 0, behavior: 'smooth' } )
		} );
		this.exec();
	}

	run() {
		new ScansActionAbandonedPlugins( this._base_data );
		new ShieldTablesScanResultsHandler( this._base_data.vars.scan_results_tables );
	}
}