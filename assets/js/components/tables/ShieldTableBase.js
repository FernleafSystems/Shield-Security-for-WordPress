import $ from 'jquery';
import 'datatables.net-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-searchpanes-bs5';
import 'datatables.net-select-bs5';
import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { OffCanvasService } from "../ui/OffCanvasService";

export class ShieldTableBase extends BaseComponent {

	init() {
		let selector = this.getTableSelector();
		if ( selector.length > 0 ) {
			this.el = document.querySelector( selector );
			this.exec();
		}
		else {
			/* console.log( 'aborting ShieldTable initialisation with empty getTableElementID()' ); */
		}
	}

	canRun() {
		if ( this.el === null ) {
			/* console.log( 'skipping ShieldTable initialisation as element is not available for selection: ' + selector ); */
		}
		return this.el !== null;
	}

	run() {
		this.$el = $( this.el );
		this.setupDatatable();
	}

	getTableSelector() {
		return '';
	}

	setupDatatable() {
		this.$table = this.$el.DataTable( this.buildDatatableConfig() );
		this.markBusyStateLifecycleBound( this.$table );
		this.addButtons();
		this.bindEvents();
		this.ensureSearchDelay();
	}

	bindEvents() {
		[ 'xhr', 'draw', 'select', 'deselect' ].forEach( ( event ) => {
			this.$table.on( event, () => this.rowSelectionChanged() );
		} );
	}

	ensureSearchDelay() {
		$( '.dataTables_filter input', this.$table )
		.unbind() // Unbind previous default bindings
		.bind(
			'input',
			( this.buildDelayedCallback( ( e ) => { // Bind our desired behavior
				this.$table.search( e.currentTarget.value ).draw();
			}, 800 ) )
		); // Set delay in milliseconds
	}

	buildDelayedCallback( callback, ms ) {
		/** @type {ReturnType<typeof globalThis.setTimeout>|undefined} */
		let timer;
		return function () {
			let context = this, args = arguments;
			clearTimeout( timer );
			timer = setTimeout( function () {
				callback.apply( context, args );
			}, ms || 0 );
		};
	}

	buildDatatableConfig() {
		const sourceConfig = this._base_data?.vars?.datatables_init || {};
		const defaultConfig = this.getDefaultDatatableConfig();
		const config = $.extend( {}, sourceConfig, defaultConfig );
		config.on = this.buildDatatableEventConfig(
			sourceConfig?.on,
			defaultConfig?.on
		);
		return config;
	}

	datatablesAjaxRequest( data, callback, settings ) {
		let reqData = ObjectOps.ObjClone( this._base_data.ajax.table_action );
		reqData.sub_action = 'retrieve_table_data';
		reqData.table_data = data;

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

	/** @returns {Record<string, any>} */
	getDefaultDatatableConfig() {
		return {
			dom: 'PrBpftip',
			serverSide: true,
			processing: true,
			searchDelay: 600,
			ajax: ( data, callback, settings ) => this.datatablesAjaxRequest( data, callback, settings ),
			deferRender: true,
			select: {
				style: 'multi'
			},
			rowReorder: false,
			search: {},
			buttons: {
				buttons: [],
				dom: {
					button: {
						className: 'btn btn-sm'
					}
				}
			},
			language: {
				emptyTable: "There are no items to display.",
				zeroRecords: "No entries found - please try adjusting your search filters."
			},
			pageLength: 25
		};
	}

	buildDatatableEventConfig( sourceEvents, defaultEvents ) {
		const events = {
			...this.normalizeDatatableEvents( sourceEvents ),
			...this.normalizeDatatableEvents( defaultEvents ),
		};
		const existingProcessingHandler = typeof events.processing === 'function'
			? events.processing
			: null;

		events.processing = ( e, settings, isBusy ) => {
			this.handleDatatableProcessing( settings, isBusy );
			existingProcessingHandler?.( e, settings, isBusy );
		};

		return events;
	}

	normalizeDatatableEvents( events ) {
		return events && typeof events === 'object'
			? events
			: {};
	}

	addButtons() {
		this.getButtons().forEach( ( button, idx ) => {
			this.$table.button().add( idx, /** @type {any} */ ( button ) );
		} );
	}

	getButtons() {
		let buttons = [ {
			text: 'Reload Table',
			name: 'table-reload',
			className: 'action table-refresh btn-outline-secondary mb-2',
			action: ( e, dt, node, config ) => {
				this.tableReload();
			}
		} ];

		if ( this._base_data.ajax && this._base_data.ajax.render_offcanvas ) {
			buttons.push( {
				text: 'Search Help',
				name: 'search-help',
				className: 'action search-help btn-outline-info mb-2',
				action: ( e, dt, node, config ) => {
					OffCanvasService.RenderCanvas( this._base_data.ajax.render_offcanvas );
				}
			} );
		}

		return buttons;
	}

	rowSelectionChanged() {
	};

	resolveDatatable( datatableOrSettings = null ) {
		if ( datatableOrSettings && typeof datatableOrSettings.table === 'function' ) {
			return datatableOrSettings;
		}

		const tableNode = datatableOrSettings?.nTable || null;
		if ( tableNode && $.fn.dataTable && $.fn.dataTable.isDataTable( tableNode ) ) {
			return $( tableNode ).DataTable();
		}

		return this.$table || null;
	}

	resolveTableContainer( datatableOrSettings = null ) {
		const datatable = this.resolveDatatable( datatableOrSettings );
		const container = datatable?.table?.().container?.() || null;
		return container instanceof HTMLElement ? container : null;
	}

	markBusyStateLifecycleBound( datatableOrSettings = null ) {
		const container = this.resolveTableContainer( datatableOrSettings );
		if ( container !== null ) {
			container.dataset.shieldBusyLifecycleBound = '1';
		}
	}

	bindBusyStateLifecycle( datatable ) {
		const container = this.resolveTableContainer( datatable );
		if ( container === null || container.dataset.shieldBusyLifecycleBound === '1' ) {
			return;
		}

		container.dataset.shieldBusyLifecycleBound = '1';
		datatable.on( 'processing.shieldBusyState', ( e, settings, isBusy ) => {
			this.handleDatatableProcessing( settings, isBusy );
		} );
	}

	handleDatatableProcessing( datatableOrSettings, isBusy ) {
		ShieldTableBase.applyBusyStateToContainer(
			this.resolveTableContainer( datatableOrSettings ),
			isBusy
		);
	}

	setTableBusy( datatableOrSettings, isBusy ) {
		const datatable = this.resolveDatatable( datatableOrSettings );
		const processingDatatable = /** @type {{processing?: ( isBusy: boolean ) => void}|null} */ ( datatable );
		if ( processingDatatable === null || typeof processingDatatable.processing !== 'function' ) {
			return;
		}

		processingDatatable.processing( isBusy );
	}

	clearTableBusy( datatableOrSettings = null ) {
		this.setTableBusy( datatableOrSettings, false );
	}

	static applyBusyStateToContainer( container, isBusy ) {
		if ( !( container instanceof HTMLElement ) ) {
			return false;
		}

		container.classList.toggle( 'shield-table-is-busy', isBusy );
		container.setAttribute( 'aria-busy', isBusy ? 'true' : 'false' );
		return true;
	}

	static resolveDatatableForTableElement( tableEl ) {
		return tableEl instanceof HTMLTableElement
			&& $.fn.dataTable
			&& $.fn.dataTable.isDataTable( tableEl )
			? $( tableEl ).DataTable()
			: null;
	}

	static setBusyForTableElement( tableEl, isBusy ) {
		const datatable = ShieldTableBase.resolveDatatableForTableElement( tableEl );
		const processingDatatable = /** @type {{processing?: ( isBusy: boolean ) => void}|null} */ ( datatable );
		if ( processingDatatable === null || typeof processingDatatable.processing !== 'function' ) {
			return false;
		}

		processingDatatable.processing( isBusy );
		return true;
	}

	extractResponseData( resp ) {
		return ( resp && typeof resp === 'object' && resp.data && typeof resp.data === 'object' )
			? resp.data
			: {};
	}

	extractResponseMessage( resp, fallback = 'Communications error with site.' ) {
		const responseData = this.extractResponseData( resp );
		return ( typeof responseData.message === 'string' && responseData.message.length > 0 )
			? responseData.message
			: fallback;
	}

	showResponseMessage( message, success = true ) {
		if ( typeof message !== 'string' || message.length < 1 ) {
			return;
		}

		const notificationService = shieldServices?.notification?.();
		if ( notificationService ) {
			notificationService.showMessage( message, success );
		}
		else {
			alert( message );
		}
	}

	dispatchTableActionSuccess( datatableOrSettings, reqData, responseData ) {
		const container = this.resolveTableContainer( datatableOrSettings );
		if ( container === null ) {
			return;
		}

		container.dispatchEvent( new CustomEvent( 'shield:table-action-success', {
			bubbles: true,
			detail: {
				request_data: reqData,
				response_data: responseData,
			}
		} ) );
	}

	sendTableActionRequest( datatableOrSettings, reqData, fallbackErrorMessage = 'Communications error with site.', options = {} ) {
		const datatable = this.resolveDatatable( datatableOrSettings );
		if ( datatable === null || reqData === null || typeof reqData !== 'object' ) {
			return Promise.resolve( null );
		}

		this.setTableBusy( datatable, true );

		return ( new AjaxService() )
		.send( reqData, false, true )
		.then( ( resp ) => {
			if ( resp?.success ) {
				const responseData = this.extractResponseData( resp );
				if ( responseData.table_reload || options.reloadTableOnSuccess ) {
					this.tableReload( datatable );
				}
				else {
					this.clearTableBusy( datatable );
				}
				this.showResponseMessage( responseData.message || '', true );
				this.dispatchTableActionSuccess( datatable, reqData, responseData );
			}
			else {
				this.clearTableBusy( datatable );
				alert( this.extractResponseMessage( resp, fallbackErrorMessage ) );
			}
			return resp;
		} )
		.catch( ( error ) => {
			this.clearTableBusy( datatable );
			alert( fallbackErrorMessage );
			throw error;
		} );
	}

	bulkTableAction( action, RIDs = [] ) {
		if ( RIDs.length === 0 ) {
			RIDs = this.getSelectedRIDs();
		}

		if ( RIDs.length > 0 ) {
			let data = ObjectOps.ObjClone( this._base_data.ajax.table_action )
			delete data.file;
			delete data.type;
			data.sub_action = action;
			data.rids = RIDs;

			this.sendTableActionRequest(
				this.$table,
				data,
				'Communications error with site.',
				{ reloadTableOnSuccess: true }
			).catch( ( error ) => {
				console.log( error );
			} );
		}
	};

	getSelectedRIDs() {
		const RIDs = [];
		this.$table
			.rows( { selected: true } )
			.every(
				function ( rowIdx, tableLoop, rowLoop ) {
					RIDs.push( this.data().rid );
				}
			);
		return RIDs;
	}

	tableReload( datatableOrSettings = null ) {
		const datatable = this.resolveDatatable( datatableOrSettings );
		if ( datatable !== null ) {
			datatable.ajax.reload( null );
		}
	}
}
