import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { ShieldTableScanResults } from "./ShieldTableScanResults";

export class ShieldTablesScanResultsHandler extends BaseAutoExecComponent {

	run() {
		Object.keys( this._base_data ).forEach(
			( key, idx ) => {
				let thisTable = ObjectOps.ObjClone( this._base_data[ key ] );
				document.querySelectorAll( thisTable.vars.table_selector )
					.forEach( ( tableElem ) => {
						if ( !( tableElem instanceof HTMLTableElement ) || !tableElem.id ) {
							return;
						}

						let tableData = ObjectOps.ObjClone( thisTable );
						tableData.vars.table_selector = '#' + tableElem.id;
						if ( tableElem.dataset.type ) {
							tableData.ajax.table_action.type = tableElem.dataset.type;
						}
						if ( tableElem.dataset.file ) {
							tableData.ajax.table_action.file = tableElem.dataset.file;
						}
						tableElem.dataset.resultsDisplayOptions = JSON.stringify(
							tableData.ajax?.table_action?.results_display_options || {}
						);
						new ShieldTableScanResults( tableData );
					} );
			}
		);
	}
}
