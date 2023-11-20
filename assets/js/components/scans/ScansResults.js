import $ from 'jquery';
import { BaseComponent } from "../BaseComponent";
import { ScansActionAbandonedPlugins } from "./ScansActionAbandonedPlugins";
import { ShieldTablesScanResultsHandler } from "../tables/ShieldTablesScanResultsHandler";

export class ScansResults extends BaseComponent {

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