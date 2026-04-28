import { ObjectOps } from "../../util/ObjectOps";
import { AjaxService } from "../services/AjaxService";
import { ShieldTableBase } from "./ShieldTableBase";
import {
	bindScanResultsRowActions,
	buildScanResultsButtons,
	normalizeResultsDisplayOptions,
	syncScanResultsDisplayButtons,
	syncScanResultsSelectionButtons
} from "./ScanResultsTableBehavior";

export class ShieldTableScanResults extends ShieldTableBase {

	run() {
		this.resultsDisplayOptions = normalizeResultsDisplayOptions(
			this.parseResultsDisplayOptionsDataset()
		);
		this.applyResultsDisplayOptions( this.resultsDisplayOptions );
		super.run();
	}

	getTableSelector() {
		return this._base_data.vars.table_selector;
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.dom = 'Brpftip';
		cfg.language = {
			...( cfg.language || {} ),
			...this.getFilterAwareLanguage(),
		};
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
		this.syncDynamicUi();
	}

	getButtons() {
		const baseButtons = /** @type {any[]} */ ( super.getButtons() );
		return baseButtons.concat(
			buildScanResultsButtons( {
				displayFilters: {
					onToggle: ( optionKey ) => this.toggleResultsDisplayOption( optionKey ),
				},
				onBulkAction: ( action ) => this.bulkTableAction.call( this, action ),
			} )
		);
	}

	rowSelectionChanged() {
		syncScanResultsSelectionButtons( this.$table );
		this.syncDynamicUi();
	}

	datatablesAjaxRequest( data, callback, settings ) {
		const reqData = ObjectOps.ObjClone( this._base_data.ajax.table_action );
		reqData.sub_action = 'retrieve_table_data';
		reqData.table_data = data;
		reqData.results_display_options = ObjectOps.ObjClone( this.resultsDisplayOptions );

		return ( new AjaxService() )
			.send( reqData, false, true )
			.then( ( resp ) => {
				if ( resp.success ) {
					callback( resp.data.datatable_data );
				}
				else {
					this.clearTableBusy( settings );
					alert( this.extractResponseMessage( resp ) );
				}
			} );
	}

	bulkTableAction( action, RIDs = [] ) {
		if ( RIDs.length === 0 ) {
			RIDs = [ 'ignore', 'unignore' ].includes( action )
				? this.getSelectedRIDsForScanResultAction( action )
				: this.getSelectedRIDs();
		}

		if ( RIDs.length > 0 ) {
			const data = ObjectOps.ObjClone( this._base_data.ajax.table_action );
			delete data.file;
			delete data.type;
			data.sub_action = action;
			data.rids = RIDs;

			this.sendTableActionRequest(
				this.$table,
				data,
				'Communications error with site.',
				{ resetPaging: false }
			);
		}
	}

	getSelectedRIDsForScanResultAction( action ) {
		const RIDs = [];
		const targetIgnoredState = action === 'unignore';

		this.$table
			.rows( { selected: true } )
			.every( function () {
				const rowData = this.data() || {};
				if ( Boolean( rowData.is_ignored ) === targetIgnoredState ) {
					RIDs.push( rowData.rid );
				}
			} );

		return RIDs;
	}

	toggleResultsDisplayOption( optionKey ) {
		const nextOptions = {
			...this.resultsDisplayOptions,
			[ optionKey ]: !this.resultsDisplayOptions?.[ optionKey ],
		};

		if ( this.resultsDisplayOptions.ignored_only ) {
			nextOptions.ignored_only = false;
		}
		if ( optionKey === 'include_ignored' && nextOptions.include_ignored === false ) {
			nextOptions.ignored_only = false;
		}

		this.applyResultsDisplayOptions( nextOptions );
		this.tableReload();
	}

	applyResultsDisplayOptions( options = {} ) {
		this.resultsDisplayOptions = normalizeResultsDisplayOptions( options );
		this._base_data.ajax.table_action.results_display_options = ObjectOps.ObjClone( this.resultsDisplayOptions );

		if ( this.el instanceof HTMLTableElement ) {
			this.el.dataset.resultsDisplayOptions = JSON.stringify( this.resultsDisplayOptions );
		}

		if ( this.$table ) {
			const settings = this.$table.settings?.()[ 0 ] || null;
			if ( settings && settings.oLanguage ) {
				Object.assign( settings.oLanguage, this.getFilterAwareLanguage() );
			}
		}
	}

	syncResultsDisplayState() {
		if ( this.$table ) {
			syncScanResultsDisplayButtons( this.$table, this.resultsDisplayOptions );
		}
	}

	syncDynamicUi() {
		this.syncResultsDisplayState();
	}

	parseResultsDisplayOptionsDataset() {
		if ( !( this.el instanceof HTMLTableElement ) ) {
			return {};
		}

		try {
			return JSON.parse( this.el.dataset.resultsDisplayOptions || '{}' );
		}
		catch ( e ) {
			return {};
		}
	}

	getFilterAwareLanguage() {
		const message = 'No results match the current display filters. Use Display Results to show ignored, repaired, or deleted results.';
		return {
			emptyTable: message,
			zeroRecords: message,
		};
	}
}
