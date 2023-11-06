import $ from 'jquery';
import 'datatables.net-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-searchpanes-bs5';
import 'datatables.net-select-bs5';
import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { ObjectOps } from "./ObjectOps";

export class ShieldTableBase extends BaseService {

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
			( delay( ( e ) => { // Bind our desired behavior
				this.$table.search( e.currentTarget.value ).draw();
			}, 800 ) )
		); // Set delay in milliseconds

		function delay( callback, ms ) {
			let timer = 0;
			return function () {
				let context = this, args = arguments;
				clearTimeout( timer );
				timer = setTimeout( function () {
					callback.apply( context, args );
				}, ms || 0 );
			};
		}
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

		( new AjaxService() )
		.send( reqData )
		.then( ( resp ) => {
			if ( resp.success ) {
				callback( resp.data.datatable_data );
			}
			else {
				let msg = 'Communications error with site.';
				if ( resp.data.message !== undefined ) {
					msg = resp.data.message;
				}
				alert( msg );
			}
		} )
		.finally();
	}

	getDefaultDatatableConfig() {
		return {
			dom: 'PrBpftip',
			serverSide: true,
			searchDelay: 600,
			ajax: ( data, callback, settings ) => this.datatablesAjaxRequest( data, callback, settings ),
			deferRender: true,
			select: {
				style: 'multi'
			},
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
		return [ {
			text: 'Reload Table',
			name: 'table-reload',
			className: 'action table-refresh btn-outline-secondary mb-2',
			action: ( e, dt, node, config ) => {
				dt.ajax.reload( null );
			}
		} ];
	}

	rowSelectionChanged() {
	};

	bulkTableAction( action, RIDs = [] ) {
		if ( RIDs.length === 0 ) {
			this.$table
				.rows( { selected: true } )
				.every(
					function ( rowIdx, tableLoop, rowLoop ) {
						RIDs.push( this.data().rid );
					}
				);
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
					this.$table.ajax.reload( null );
					shieldServices.notification().showMessage( resp.data.message, resp.success );
				}
				else {
					alert( resp.data.message );
					// console.log( resp );
				}
			} )
			.catch( ( error ) => {
				console.log( error );
			} )
			.finally( () => {
			} );
		}
	};
}