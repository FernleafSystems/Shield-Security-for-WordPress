import $ from 'jquery';
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { ShieldTableBase } from "./ShieldTableBase";
import {
	bindScanResultsRowActions,
	bindScanResultsSelectionButtons,
	buildScanResultsButtons
} from "./ScanResultsTableBehavior";

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
		this.applyBehaviorDatatableConfig( cfg, context );

		const datatable = $tableElement.DataTable( cfg );
		this.bindBusyStateLifecycle( datatable );
		this.addBehaviorButtons( datatable, context );
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
		if ( tableContext.tableBehavior !== 'scan_results_flat' ) {
			$tableElement.off( '.shieldInvestigationFileScan' );
			return;
		}

		bindScanResultsRowActions( {
			$tableElement,
			datatable,
			scanResultsAction: tableContext.scanResultsAction,
			renderItemAnalysis: tableContext.renderItemAnalysis,
			onAction: ( action, rids = [] ) => this.performScanResultsAction( datatable, tableContext.scanResultsAction, action, rids ),
			namespace: 'shieldInvestigationFileScan',
		} );
		bindScanResultsSelectionButtons( datatable, 'shieldInvestigationScanResultsButtons' );
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
		const tableBehavior = String( tableEl.dataset.tableBehavior || 'default' ).trim();
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
			tableBehavior,
			datatablesInit,
			tableAction,
			scanResultsAction,
			renderItemAnalysis,
		};
	}

	applyBehaviorDatatableConfig( cfg, tableContext ) {
		if ( tableContext.tableBehavior !== 'scan_results_flat' ) {
			return;
		}

		cfg.dom = 'Brpftip';
		cfg.pageLength = 15;
		cfg.select = {
			style: 'multi',
			items: 'row'
		};
	}

	addBehaviorButtons( datatable, tableContext ) {
		if ( tableContext.tableBehavior !== 'scan_results_flat' ) {
			return;
		}

		buildScanResultsButtons( {
			includeReload: true,
			onReload: () => this.reloadBusyTable( datatable ),
			onBulkAction: ( action ) => this.performScanResultsAction(
				datatable,
				tableContext.scanResultsAction,
				action,
				this.getSelectedRids( datatable )
			),
		} ).forEach( ( button, index ) => {
			datatable.button().add( index, button );
		} );
	}

	getSelectedRids( datatable ) {
		const rids = [];
		datatable.rows( { selected: true } ).every( function () {
			rids.push( this.data().rid );
		} );
		return rids;
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
