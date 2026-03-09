import $ from 'jquery';
import hljs from 'highlight.js/lib/core';
import bash from 'highlight.js/lib/languages/bash';
import css from 'highlight.js/lib/languages/css';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import php from 'highlight.js/lib/languages/php';
import sql from 'highlight.js/lib/languages/sql';
import xml from 'highlight.js/lib/languages/xml';
import { AjaxService } from "../services/AjaxService";
import { BootstrapModals } from "../ui/BootstrapModals";
import { ObjectOps } from "../../util/ObjectOps";
import { ShieldTableBase } from "./ShieldTableBase";

hljs.registerLanguage( 'bash', bash );
hljs.registerLanguage( 'css', css );
hljs.registerLanguage( 'javascript', javascript );
hljs.registerLanguage( 'json', json );
hljs.registerLanguage( 'php', php );
hljs.registerLanguage( 'sql', sql );
hljs.registerLanguage( 'xml', xml );

export class InvestigationTable extends ShieldTableBase {

	static hasBoundShownTabAdjustHandler = false;

	init() {
		this.contextEl = this._base_data?.contextEl instanceof Element ? this._base_data.contextEl : document;
		this.els = Array.from( this.contextEl.querySelectorAll( '[data-investigation-table="1"]' ) );
		this.exec();
	}

	canRun() {
		return this.els.length > 0;
	}

	run() {
		this.els.forEach( ( el ) => this.setupInvestigationTable( el ) );
		this.bindShownTabAdjustHandler();
	}

	getDefaultDatatableConfig() {
		let cfg = super.getDefaultDatatableConfig();
		cfg.dom = 'frtip';
		cfg.pageLength = 15;
		cfg.select = false;
		return cfg;
	}

	setupInvestigationTable( tableEl ) {
		if ( !this.isElementVisible( tableEl ) ) {
			return;
		}

		const context = this.extractTableContext( tableEl );
		if ( context === null ) {
			return;
		}

		const $tableElement = $( tableEl );
		if ( $.fn.dataTable && $.fn.dataTable.isDataTable( tableEl ) ) {
			const datatable = $tableElement.DataTable();
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
		this.ensureSearchDelay( datatable );
		this.bindTableInteractions( $tableElement, datatable, context );
	}

	bindShownTabAdjustHandler() {
		if ( InvestigationTable.hasBoundShownTabAdjustHandler ) {
			return;
		}
		InvestigationTable.hasBoundShownTabAdjustHandler = true;

		shieldEventsHandler_Main.addHandler(
			'shown.bs.tab',
			'.shield-options-rail [data-bs-toggle="tab"]',
			( targetEl ) => {
				if ( targetEl === null ) {
					return;
				}

				const paneSelector = targetEl.dataset.bsTarget || targetEl.getAttribute( 'href' ) || '';
				if ( typeof paneSelector !== 'string' || paneSelector.length === 0 || paneSelector.charAt( 0 ) !== '#' ) {
					return;
				}

				const pane = document.querySelector( paneSelector );
				if ( pane === null || pane.querySelector( '[data-investigation-table="1"]' ) === null ) {
					return;
				}

				new InvestigationTable( { contextEl: pane } );
				if ( !$.fn.dataTable || !$.fn.dataTable.isDataTable ) {
					return;
				}

				$( pane ).find( '[data-investigation-table="1"]' ).each( ( _, tableEl ) => {
					if ( $.fn.dataTable.isDataTable( tableEl ) ) {
						const datatable = $( tableEl ).DataTable();
						datatable.columns.adjust();
					}
				} );
			},
			false
		);
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
					this.renderItemAnalysisModal( tableContext.renderItemAnalysis, evt.currentTarget.dataset.rid );
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

		return ( new AjaxService() )
		.send( reqData )
		.then( ( resp ) => {
			const responseData = ( resp && typeof resp === 'object' && resp.data && typeof resp.data === 'object' )
				? resp.data
				: {};

			if ( resp.success ) {
				datatable.ajax.reload( null );
				const notificationService = shieldServices?.notification?.();
				if ( notificationService ) {
					notificationService.showMessage( responseData.message || '', resp.success );
				}
			}
			else {
				alert( responseData.message || 'Communications error with site.' );
			}
		} )
		.catch( ( error ) => {
			console.log( error );
		} );
	}

	renderItemAnalysisModal( renderAction, rid ) {
		if ( typeof rid !== 'string' || rid.length < 1 ) {
			return Promise.resolve();
		}

		const reqData = ObjectOps.ObjClone( renderAction );
		reqData.rid = rid;

		return ( new AjaxService() )
		.send( reqData )
		.then( ( resp ) => {
			const responseData = ( resp && typeof resp === 'object' && resp.data && typeof resp.data === 'object' )
				? resp.data
				: {};

			if ( resp.success ) {
				const modal = document.getElementById( 'ShieldModalContainer' );
				if ( modal === null ) {
					return;
				}

				const modalContent = modal.querySelector( '.modal-content' );
				if ( modalContent === null ) {
					return;
				}

				modalContent.innerHTML = responseData.html || '';
				BootstrapModals.Show( modal );
				this.highlightModalCodeBlocks( modal );
			}
			else {
				alert( responseData.message || 'Communications error with site.' );
			}
		} )
		.catch( ( error ) => {
			console.log( error );
		} );
	}

	highlightModalCodeBlocks( modal ) {
		const unknownLanguageBlocks = [];
		modal.querySelectorAll( 'pre.icwp-code-render code' ).forEach( ( el ) => {
			const languageClass = Array.from( el.classList ).find( ( cls ) => cls.startsWith( 'language-' ) ) || '';
			const language = languageClass ? languageClass.slice( 9 ) : '';
			if ( language.length > 0 && hljs.getLanguage( language ) ) {
				hljs.highlightElement( el );
			}
			else {
				unknownLanguageBlocks.push( el );
			}
		} );

		if ( unknownLanguageBlocks.length < 1 ) {
			return;
		}

		const detectedLanguage = hljs.highlightAuto(
			unknownLanguageBlocks.map( ( el ) => el.textContent || '' ).join( "\n" )
		).language || '';

		unknownLanguageBlocks.forEach( ( el ) => {
			if ( detectedLanguage.length > 0 && hljs.getLanguage( detectedLanguage ) ) {
				const highlighted = hljs.highlight( el.textContent || '', {
					language: detectedLanguage,
					ignoreIllegals: true,
				} );
				el.innerHTML = highlighted.value;
				el.classList.add( 'hljs', 'language-' + detectedLanguage );
			}
			else {
				const highlighted = hljs.highlightAuto( el.textContent || '' );
				el.innerHTML = highlighted.value;
				el.classList.add( 'hljs' );
				if ( highlighted.language ) {
					el.classList.add( 'language-' + highlighted.language );
				}
			}
		} );
	}

	datatablesAjaxRequest( data, callback, settings, tableContext ) {
		let reqData = ObjectOps.ObjClone( tableContext.tableAction );
		reqData.sub_action = 'retrieve_table_data';
		reqData.table_type = tableContext.tableType;
		reqData.subject_type = tableContext.subjectType;
		reqData.subject_id = tableContext.subjectId;
		reqData.table_data = data;

		return ( new AjaxService() )
		.send( reqData, false )
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

	isElementVisible( el ) {
		return el instanceof Element && el.getClientRects().length > 0;
	}
}
