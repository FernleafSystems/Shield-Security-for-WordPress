import { ObjectOps } from "../../util/ObjectOps";
import { ShieldTableBase } from "./ShieldTableBase";
import {
	bindScanResultsRowActions,
	buildScanResultsButtons,
	syncScanResultsSelectionButtons
} from "./ScanResultsTableBehavior";

export class ShieldTableScanResults extends ShieldTableBase {

	getTableSelector() {
		return this._base_data.vars.table_selector;
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.dom = 'Brpftip';
		return cfg;
	}

	bindEvents() {
		super.bindEvents();
		bindScanResultsRowActions( {
			$tableElement: this.$el,
			datatable: this.$table,
			scanResultsAction: this._base_data.ajax.table_action,
			renderItemAnalysis: this._base_data.ajax.render_item_analysis,
			onAction: ( action, rids = [] ) => this.bulkTableAction.call( this, action, rids ),
			namespace: 'shieldScanResults',
		} );
	}

	getButtons() {
		return super.getButtons().concat(
			buildScanResultsButtons( {
				onBulkAction: ( action ) => this.bulkTableAction.call( this, action ),
			} )
		);
	}

	rowSelectionChanged() {
		syncScanResultsSelectionButtons( this.$table );
	}

	bulkTableAction( action, RIDs = [] ) {
		if ( RIDs.length === 0 ) {
			RIDs = this.getSelectedRIDs();
		}

		if ( RIDs.length > 0 ) {
			const data = ObjectOps.ObjClone( this._base_data.ajax.table_action );
			delete data.file;
			delete data.type;
			data.sub_action = action;
			data.rids = RIDs;

			this.sendTableActionRequest( this.$table, data );
		}
	}
}
