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

	#pendingBusyClearContainers = new WeakSet();

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
		this.bindBusyStateLifecycle( this.$table );
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
		let timer = 0;
		return function () {
			let context = this, args = arguments;
			clearTimeout( timer );
			timer = setTimeout( function () {
				callback.apply( context, args );
			}, ms || 0 );
		};
	}

	buildDatatableConfig() {
		return $.extend(
			this._base_data.vars.datatables_init,
			this.getDefaultDatatableConfig()
		);
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

	addButtons() {
		this.getButtons().forEach( ( button, idx ) => {
			this.$table.button().add( idx, button );
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

	bindBusyStateLifecycle( datatable ) {
		const container = this.resolveTableContainer( datatable );
		if ( container === null || container.dataset.shieldBusyLifecycleBound === '1' ) {
			return;
		}

		container.dataset.shieldBusyLifecycleBound = '1';
		datatable.on( 'draw.shieldBusyState', () => {
			if ( this.#pendingBusyClearContainers.has( container ) ) {
				this.clearTableBusy( datatable );
			}
		} );
	}

	setTableBusy( datatableOrSettings, isBusy ) {
		const datatable = this.resolveDatatable( datatableOrSettings );
		const container = this.resolveTableContainer( datatable );
		if ( datatable === null || container === null ) {
			return;
		}

		container.classList.toggle( 'shield-table-is-busy', isBusy );
		container.setAttribute( 'aria-busy', isBusy ? 'true' : 'false' );
		if ( typeof datatable.processing === 'function' ) {
			datatable.processing( isBusy );
		}
		if ( !isBusy ) {
			this.#pendingBusyClearContainers.delete( container );
		}
	}

	clearTableBusy( datatableOrSettings = null ) {
		this.setTableBusy( datatableOrSettings, false );
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

	queueBusyClearOnNextDraw( datatableOrSettings ) {
		const container = this.resolveTableContainer( datatableOrSettings );
		if ( container !== null ) {
			this.#pendingBusyClearContainers.add( container );
		}
	}

	reloadBusyTable( datatableOrSettings ) {
		const datatable = this.resolveDatatable( datatableOrSettings );
		if ( datatable === null ) {
			return;
		}

		this.setTableBusy( datatable, true );
		this.queueBusyClearOnNextDraw( datatable );
		datatable.ajax.reload( null );
	}

	sendTableActionRequest( datatableOrSettings, reqData, fallbackErrorMessage = 'Communications error with site.' ) {
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
				if ( responseData.table_reload ) {
					this.reloadBusyTable( datatable );
				}
				else {
					this.clearTableBusy( datatable );
				}
				this.showResponseMessage( responseData.message || '', true );
			}
			else {
				this.clearTableBusy( datatable );
				alert( this.extractResponseMessage( resp, fallbackErrorMessage ) );
			}
			return resp;
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

			( new AjaxService() )
			.send( data )
			.then( ( resp ) => {
				if ( resp.success ) {
					this.tableReload();
					shieldServices.notification().showMessage( resp.data.message, resp.success );
				}
				else {
					alert( resp.data.message );
				}
			} )
			.catch( ( error ) => {
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

	tableReload() {
		this.$table.ajax.reload( null );
	}
}
