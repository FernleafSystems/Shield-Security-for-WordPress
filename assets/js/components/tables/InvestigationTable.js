import $ from 'jquery';
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { ShieldTableBase } from "./ShieldTableBase";
import { bindActivityLogMetaPopover } from "./ActivityLogMetaPopover";

export class InvestigationTable extends ShieldTableBase {

	init() {
		this.contextEl = this._base_data?.contextEl instanceof Element ? this._base_data.contextEl : document;
		this.els = Array.isArray( this._base_data?.tableEls )
			? this._base_data.tableEls.filter( ( el ) => el instanceof Element )
			: Array.from( this.contextEl.querySelectorAll( '[data-investigation-table="1"]' ) );
		this.exec();
	}

	canRun() {
		return this.els.length > 0;
	}

	run() {
		this.els.forEach( ( el ) => this.setupInvestigationTable( el ) );
	}

	getDefaultDatatableConfig() {
		/** @type {Record<string, any>} */
		let cfg = super.getDefaultDatatableConfig();
		cfg.dom = 'frtip';
		cfg.pageLength = 15;
		cfg.select = false;
		return cfg;
	}

	setupInvestigationTable( tableEl ) {
		const context = this.extractTableContext( tableEl );
		if ( context === null ) {
			return;
		}

		const $tableElement = $( tableEl );
		if ( $.fn.dataTable && $.fn.dataTable.isDataTable( tableEl ) ) {
			const datatable = $tableElement.DataTable();
			this.bindBusyStateLifecycle( datatable );
			this.bindFloatingUiLifecycle( datatable );
			this.ensureSearchDelay( datatable );
			this.bindTableBehaviors( tableEl, context );
			return;
		}

		const cfg = $.extend(
			{},
			context.datatablesInit,
			this.getDefaultDatatableConfig(),
			{
				ajax: ( data, callback, settings ) => this.datatablesAjaxRequest( data, callback, settings, context )
			}
		);
		cfg.on = this.buildDatatableEventConfig(
			context.datatablesInit?.on,
			cfg.on
		);

		const datatable = $tableElement.DataTable( cfg );
		this.markBusyStateLifecycleBound( datatable );
		this.bindFloatingUiLifecycle( datatable );
		this.ensureSearchDelay( datatable );
		this.bindTableBehaviors( tableEl, context );
	}

	bindTableBehaviors( tableEl, tableContext ) {
		if ( tableContext.tableType === 'activity' ) {
			bindActivityLogMetaPopover( tableEl, tableContext.tableAction );
		}
	}

	ensureSearchDelay( datatable ) {
		const $input = $( '.dataTables_filter input', datatable.table().container() );
		$input
		.off( 'input.shieldInvestigationSearch' )
		.on(
			'input.shieldInvestigationSearch',
			( this.buildDelayedCallback( ( e ) => {
				datatable.search( e.currentTarget.value ).draw();
			}, 800 ) )
		);
	}

	datatablesAjaxRequest( data, callback, settings, tableContext ) {
		let reqData = ObjectOps.ObjClone( tableContext.tableAction );
		reqData.sub_action = 'retrieve_table_data';
		reqData.table_type = tableContext.tableType;
		reqData.subject_type = tableContext.subjectType;
		reqData.subject_id = tableContext.subjectId;
		reqData.table_data = data;

		return ( new AjaxService() )
		.send( reqData, false, true )
		.then( ( resp ) => {
			this.handleDatatableAjaxResponse( resp, callback, settings );
		} );
	}

	extractTableContext( tableEl ) {
		const { tableType, subjectType, subjectId, datatablesInit: datatablesInitRaw, tableAction: tableActionRaw } = tableEl.dataset;
		if (
			typeof tableType !== 'string' || tableType.length === 0
			|| typeof subjectType !== 'string' || subjectType.length === 0
			|| typeof subjectId !== 'string' || subjectId.length === 0
			|| typeof datatablesInitRaw !== 'string'
			|| typeof tableActionRaw !== 'string'
		) {
			return null;
		}

		const datatablesInit = this.parseJsonObject( datatablesInitRaw );
		const tableAction = this.parseJsonObject( tableActionRaw );

		if ( datatablesInit === null || tableAction === null ) {
			return null;
		}

		return {
			tableType,
			subjectType,
			subjectId,
			datatablesInit,
			tableAction,
		};
	}

	parseJsonObject( rawData ) {
		if ( typeof rawData !== 'string' || rawData.trim().length < 1 ) {
			return null;
		}

		try {
			const parsed = JSON.parse( rawData );
			return parsed && typeof parsed === 'object' ? parsed : null;
		}
		catch {
			return null;
		}
	}
}
