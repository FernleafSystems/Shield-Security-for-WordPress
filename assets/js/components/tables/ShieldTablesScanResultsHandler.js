import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { ShieldTableScanResults } from "./ShieldTableScanResults";

export class ShieldTablesScanResultsHandler extends BaseAutoExecComponent {

	run() {
		Object.keys( this._base_data ).forEach(
			( key, idx ) => {
				let thisTable = ObjectOps.ObjClone( this._base_data[ key ] );
				if ( thisTable.vars.table_selector.startsWith( '#' ) ) {
					new ShieldTableScanResults( thisTable );
				}
				else {
					document.querySelectorAll( thisTable.vars.table_selector )
							.forEach( ( tableElem, idx ) => {
								if ( tableElem.id ) {
									let tableData = ObjectOps.ObjClone( thisTable );
									tableData.vars.table_selector = '#' + tableElem.id;
									tableData.ajax.table_action.type = tableElem.dataset.type;
									tableData.ajax.table_action.file = tableElem.dataset.file;
									new ShieldTableScanResults( tableData );
								}
							} );
				}
			}
		);
	}
}