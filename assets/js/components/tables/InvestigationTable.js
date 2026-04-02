import $ from 'jquery';
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { ShieldTableBase } from "./ShieldTableBase";

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
			this.ensureSearchDelay( datatable );
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

		const datatable = $tableElement.DataTable( cfg );
		this.bindBusyStateLifecycle( datatable );
		this.ensureSearchDelay( datatable );
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
			if ( resp && resp.success ) {
				callback( this.extractResponseData( resp ).datatable_data );
			}
			else {
				this.clearTableBusy( settings );
				alert( this.extractResponseMessage( resp ) );
			}
		} );
	}

	extractTableContext( tableEl ) {
		const tableType = tableEl.dataset.tableType || '';
		const subjectType = tableEl.dataset.subjectType || '';
		const subjectId = tableEl.dataset.subjectId || '';
		const datatablesInit = this.parseJsonObject( tableEl.dataset.datatablesInit || '' );
		const tableAction = this.parseJsonObject( tableEl.dataset.tableAction || '' );

		if ( tableType.length === 0 || subjectType.length === 0 || datatablesInit === null || tableAction === null ) {
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
		catch ( e ) {
			return null;
		}
	}
}
