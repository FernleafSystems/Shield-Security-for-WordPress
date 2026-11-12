import $ from 'jquery';
import { ObjectOps } from "../../util/ObjectOps";
import { AjaxService } from "../services/AjaxService";
import { ShieldTableBase } from "./ShieldTableBase";
import {
	bindScanResultsRowActions,
	buildScanResultsActionButtons,
	buildScanResultsToolbarButtons,
	normalizeResultsDisplayOptions,
	syncScanResultsDisplayButtons,
	syncScanResultsSelectionButtons
} from "./ScanResultsTableBehavior";

/**
 * A scan's results. Above the table are its own controls (select all, what to display, reload); beside it, in a
 * column that stays in view, is everything that changes results: for the selected results, which wakes up once
 * something is selected, then for all results, then the scan's own tools.
 */
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
		// The table's controls above it with the search, then the table, then the count and pages once, below.
		cfg.dom = '<"scan-results-toolbar"Bf>rtip';
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
			onAction: ( action, rids = [], launcher = null ) => this.bulkTableAction.call( this, action, rids, launcher ),
			namespace: 'shieldScanResults',
		} );
		this.syncDynamicUi();
	}

	getButtons() {
		const otherButtons = /** @type {any[]} */ ( super.getButtons() ).filter( ( button ) => button.name !== 'table-reload' );
		return buildScanResultsToolbarButtons( {
			onReload: () => this.tableReload(),
			displayFilters: {
				onToggle: ( optionKey ) => this.toggleResultsDisplayOption( optionKey ),
			},
		} ).concat( otherButtons );
	}

	addButtons() {
		super.addButtons();
		this.addActionsColumn();
	}

	/**
	 * The buttons that change results go in their own DataTables button group, in a column beside the table.
	 */
	addActionsColumn() {
		const buttons = buildScanResultsActionButtons( {
			onBulkAction: ( action ) => this.bulkTableAction.call( this, action ),
			onIgnoreAll: ( launcher ) => this.ignoreAllResults( launcher ),
			malwareRefresh: this._base_data.vars.malware_refresh === null ? null : {
				...this._base_data.vars.malware_refresh,
				onRefresh: ( launcher ) => this.refreshMalwareAssessments( launcher ),
			},
			assetActions: this._base_data.vars.asset_actions === null ? null : {
				...this._base_data.vars.asset_actions,
				onAction: ( assetAction, launcher ) => this.runAssetAction( assetAction, launcher ),
			},
		} );
		const wrapper = this.$table.table().container();
		if ( buttons.length === 0 || !( wrapper instanceof HTMLElement ) || wrapper.parentElement === null ) {
			return;
		}

		new $.fn.dataTable.Buttons( this.$table, {
			name: 'results-actions',
			buttons,
			dom: {
				container: {
					className: 'scan-results-actions__buttons',
				},
				button: {
					className: 'btn btn-sm',
				},
			},
		} );

		// The layout measures its own width, so the column sits beside the table only when there's room for both.
		const layout = document.createElement( 'div' );
		layout.className = 'scan-results-layout';
		const grid = document.createElement( 'div' );
		grid.className = 'scan-results-layout__grid';
		layout.append( grid );
		wrapper.parentElement.insertBefore( layout, wrapper );
		grid.append( wrapper );

		const column = document.createElement( 'div' );
		column.className = 'scan-results-actions';
		column.setAttribute( 'role', 'group' );
		column.setAttribute( 'aria-label', 'Actions for these results' );

		// How many are selected, said politely as it changes, since the actions depend on it.
		this.selectionStatusEl = document.createElement( 'p' );
		this.selectionStatusEl.className = 'scan-results-actions__status';
		this.selectionStatusEl.setAttribute( 'role', 'status' );
		this.selectionStatusEl.setAttribute( 'aria-live', 'polite' );

		const container = this.$table.buttons( 'results-actions', null ).container().get( 0 );
		column.append( this.selectionStatusEl, container );
		grid.append( column );
		this.addActionHeadings( container );
	}

	/**
	 * A small heading over each group in the column: what acts on the selection, on all results, and the
	 * scan's own tools.
	 * @param {HTMLElement} container
	 */
	addActionHeadings( container ) {
		const headings = {
			selected: 'Selected',
			all: 'All results',
			// The scan's own tools are only there for the malware scan's refresh.
			tools: 'Malware scan',
			asset: this._base_data.vars.asset_actions === null ? '' : this._base_data.vars.asset_actions.heading,
		};
		const starts = [
			[ 'selected', container.querySelector( '.btn' ) ],
			...[ ...container.querySelectorAll( '[data-scan-results-group-start]' ) ].map(
				( button ) => [ button.getAttribute( 'data-scan-results-group-start' ), button ]
			),
		];
		starts.forEach( ( [ key, button ] ) => {
			if ( button instanceof HTMLElement && headings[ key ] ) {
				const heading = document.createElement( 'p' );
				heading.className = 'scan-results-actions__heading';
				heading.textContent = headings[ key ];
				button.before( heading );
			}
		} );
	}

	/**
	 * A link goes straight there; anything else is confirmed first, then sent, and the results reload after.
	 */
	async runAssetAction( assetAction, launcher ) {
		if ( assetAction.kind === 'href' ) {
			window.location.href = assetAction.href;
			return null;
		}

		if ( assetAction.confirm_text !== '' && !( await shieldServices.dialog().confirm( {
			title: shieldStrings.string( 'dialog_confirm_title' ),
			message: assetAction.confirm_text,
			cancelLabel: shieldStrings.string( 'cancel' ),
			danger: true,
			launcher,
		} ) ) ) {
			return null;
		}

		// Reinstalling takes a while and mustn't be interrupted, so it holds the page with what it's doing.
		const processing = assetAction.processing_text !== ''
			? shieldServices.dialog().processing( { message: assetAction.processing_text, launcher } )
			: null;
		return this.sendTableActionRequest( this.$table, JSON.parse( assetAction.ajax_action_json ), 'Communications error with site.', {
			reloadTableOnSuccess: true,
			resetPaging: false,
			launcher,
		} ).finally( () => processing?.close() );
	}

	ignoreAllResults( launcher ) {
		const data = ObjectOps.ObjClone( this._base_data.ajax.table_action );
		data.sub_action = 'ignore_all';
		return this.sendTableActionRequest( this.$table, data, 'Communications error with site.', {
			resetPaging: false,
			launcher,
		} );
	}

	rowSelectionChanged() {
		syncScanResultsSelectionButtons( this.$table );
		this.syncSelectionStatus();
		this.syncDynamicUi();
	}

	syncSelectionStatus() {
		if ( !( this.selectionStatusEl instanceof HTMLElement ) ) {
			return;
		}
		const count = this.$table.rows( { selected: true } ).count();
		const text = count === 0 ? 'Select results to act on them' : ( count === 1 ? '1 selected' : `${count} selected` );
		if ( this.selectionStatusEl.textContent !== text ) {
			this.selectionStatusEl.textContent = text;
		}
		const allIgnore = this.$table.button( 'all-ignore:name' );
		if ( allIgnore.node()?.length > 0 ) {
			allIgnore.enable( ( this.$table.page.info()?.recordsDisplay || 0 ) > 0 );
		}
	}

	refreshMalwareAssessments( launcher ) {
		if ( this.malwareRefreshPending ) {
			return;
		}

		this.malwareRefreshPending = true;
		const button = this.$table.button( 'refresh-malware-assessments:name' );
		button.disable();
		const data = ObjectOps.ObjClone( this._base_data.ajax.table_action );
		data.sub_action = 'refresh_malware_assessments';

		return this.sendTableActionRequest( this.$table, data, 'Communications error with site.', {
			resetPaging: false,
			launcher,
		} ).then( ( resp ) => {
			if ( resp?.success === false && this.extractResponseData( resp ).table_reload === true ) {
				this.tableReload( this.$table, { resetPaging: false } );
				this.el.dispatchEvent( new CustomEvent( 'shield:scan-results-changed', { bubbles: true } ) );
			}
		} ).catch( () => null ).finally( () => {
			this.malwareRefreshPending = false;
			button.enable();
		} );
	}

	datatablesAjaxRequest( data, callback, settings ) {
		const reqData = ObjectOps.ObjClone( this._base_data.ajax.table_action );
		reqData.sub_action = 'retrieve_table_data';
		reqData.table_data = data;
		reqData.results_display_options = ObjectOps.ObjClone( this.resultsDisplayOptions );

		return ( new AjaxService() )
			.send( reqData, false, true )
			.then( ( resp ) => {
				if ( this.handleDatatableAjaxResponse( resp, callback, settings ) ) {
					this.updateDisplayNoticeFromResponse( resp );
					this.dispatchScanResultsChangedIfNeeded( resp );
				}
			} );
	}

	bulkTableAction( action, RIDs = [], launcher = null ) {
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
				{
					resetPaging: false,
					launcher,
				}
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

	dispatchScanResultsChangedIfNeeded( resp ) {
		const datatableData = this.extractResponseData( resp ).datatable_data || {};
		if ( datatableData.scan_results_changed !== true || !( this.el instanceof HTMLTableElement ) ) {
			return;
		}

		this.el.dispatchEvent( new CustomEvent( 'shield:scan-results-changed', {
			bubbles: true,
			detail: {
				datatable_data: datatableData,
			}
		} ) );
	}

	updateDisplayNoticeFromResponse( resp ) {
		if ( !( this.el instanceof HTMLTableElement ) ) {
			return;
		}

		const notice = this.extractResponseData( resp ).datatable_data?.display_notice || null;
		const container = this.el
			.closest( '.investigate-table-panel, .shield-card' )
			?.querySelector( '[data-scan-results-display-notice="1"]' );
		if ( !( container instanceof HTMLElement ) || notice === null || typeof notice !== 'object' ) {
			return;
		}

		const status = typeof notice.status === 'string' && notice.status !== '' ? notice.status : 'info';
		container.classList.forEach( ( className ) => {
			if ( className.indexOf( 'alert-' ) === 0 ) {
				container.classList.remove( className );
			}
		} );
		container.classList.add( `alert-${ status }` );
		container.classList.toggle( 'd-none', notice.is_visible !== true );
		container.dataset.scanResultsDisplayNoticeMode = typeof notice.mode === 'string' ? notice.mode : 'none';
		container.dataset.scanResultsDisplayNoticeIgnoredCount = String( notice.ignored_count ?? 0 );
		container.textContent = typeof notice.text === 'string' ? notice.text : '';
	}

	getFilterAwareLanguage() {
		const message = 'No results match the current display filters. Use Display results to show ignored, repaired, or deleted results.';
		return {
			emptyTable: message,
			zeroRecords: message,
		};
	}
}
