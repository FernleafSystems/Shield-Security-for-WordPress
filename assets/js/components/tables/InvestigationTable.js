import $ from 'jquery';
import { AjaxService } from "../services/AjaxService";
import { ScanItemAnalysisModal } from "../scans/ScanItemAnalysisModal";
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
			this.bindTableInteractions( $tableElement, datatable, context );
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
		this.bindTableInteractions( $tableElement, datatable, context );
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

	bindTableInteractions( $tableElement, datatable, tableContext ) {
		$tableElement.off( '.shieldInvestigationFileScan' );

		if ( tableContext.tableType !== 'file_scan_results' ) {
			return;
		}

		if ( tableContext.scanResultsAction !== null ) {
			$tableElement.on(
				'click.shieldInvestigationFileScan',
				'td.actions > button.action.delete',
				( evt ) => {
					evt.preventDefault();
					if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
						this.performScanResultsAction( datatable, tableContext.scanResultsAction, 'delete', [ evt.currentTarget.dataset.rid ] );
					}
					return false;
				}
			);

			$tableElement.on(
				'click.shieldInvestigationFileScan',
				'td.actions > button.action.ignore',
				( evt ) => {
					evt.preventDefault();
					this.performScanResultsAction( datatable, tableContext.scanResultsAction, 'ignore', [ evt.currentTarget.dataset.rid ] );
					return false;
				}
			);

			$tableElement.on(
				'click.shieldInvestigationFileScan',
				'td.actions > button.action.repair',
				( evt ) => {
					evt.preventDefault();
					datatable.rows().deselect();
					this.performScanResultsAction( datatable, tableContext.scanResultsAction, 'repair', [ evt.currentTarget.dataset.rid ] );
					return false;
				}
			);
		}

		if ( tableContext.renderItemAnalysis !== null ) {
			$tableElement.on(
				'click.shieldInvestigationFileScan',
				'.action.view-file',
				( evt ) => {
					evt.preventDefault();
					ScanItemAnalysisModal.show( tableContext.renderItemAnalysis, evt.currentTarget.dataset.rid );
					return false;
				}
			);
		}
	}

	performScanResultsAction( datatable, actionData, action, rids = [] ) {
		const filteredRids = rids.filter( ( rid ) => typeof rid === 'string' && rid.length > 0 );
		if ( filteredRids.length < 1 ) {
			return Promise.resolve();
		}

		const reqData = ObjectOps.ObjClone( actionData );
		reqData.sub_action = action;
		reqData.rids = filteredRids;

		return this.sendTableActionRequest( datatable, reqData );
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
		const datatablesInit = this.parseJsonData( tableEl.dataset.datatablesInit || '' );
		const tableAction = this.parseJsonData( tableEl.dataset.tableAction || '' );
		const scanResultsAction = this.parseJsonData( tableEl.dataset.scanResultsAction || '' );
		const renderItemAnalysis = this.parseJsonData( tableEl.dataset.renderItemAnalysis || '' );

		if ( tableType.length === 0 || subjectType.length === 0 || datatablesInit === null || tableAction === null ) {
			return null;
		}

		return {
			tableType,
			subjectType,
			subjectId,
			datatablesInit,
			tableAction,
			scanResultsAction,
			renderItemAnalysis,
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
