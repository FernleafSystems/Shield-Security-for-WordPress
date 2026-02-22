import $ from 'jquery';
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { ShieldTableBase } from "./ShieldTableBase";

export class InvestigationTable extends ShieldTableBase {

	init() {
		this.els = Array.from( document.querySelectorAll( '[data-investigation-table="1"]' ) );
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
		const cfg = $.extend(
			{},
			context.datatablesInit,
			this.getDefaultDatatableConfig(),
			{
				ajax: ( data, callback, settings ) => this.datatablesAjaxRequest( data, callback, settings, context )
			}
		);

		const datatable = $tableElement.DataTable( cfg );
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
		.send( reqData )
		.then( ( resp ) => {
			const responseData = ( resp && typeof resp === 'object' && resp.data && typeof resp.data === 'object' )
				? resp.data
				: {};

			if ( resp && resp.success ) {
				callback( responseData.datatable_data );
			}
			else {
				const msg = ( typeof responseData.message === 'string' && responseData.message.length > 0 )
					? responseData.message
					: 'Communications error with site.';
				alert( msg );
			}
		} );
	}

	extractTableContext( tableEl ) {
		const tableType = tableEl.dataset.tableType || '';
		const subjectType = tableEl.dataset.subjectType || '';
		const subjectId = tableEl.dataset.subjectId || '';
		const datatablesInit = this.parseJsonData( tableEl.dataset.datatablesInit || '' );
		const tableAction = this.parseJsonData( tableEl.dataset.tableAction || '' );

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

	parseJsonData( rawData ) {
		try {
			return JSON.parse( rawData );
		}
		catch ( e ) {
			return null;
		}
	}
}
