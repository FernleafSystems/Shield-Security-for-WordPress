import Sortable from 'sortablejs';
import { ShieldTableBase } from "./ShieldTableBase";
import { ObjectOps } from "../../util/ObjectOps";
import { AjaxService } from "../services/AjaxService";

/**
 * Rows-Reorder extension for Datatables is terrible. When using server-side there's no way to gather
 * the current row order, so we have to use Sortable to bridge the gap and scrap DT reorder extension.
 */
export class ShieldTableSecurityRules extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-SecurityRulesManager';
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.dom = 'Brptip';
		// cfg.rowReorder = {
		// 	dataSrc: 'rid', /** The column that selects to drag **/
		// 	/** https://datatables.net/reference/option/rowReorder.update **/
		// 	update: false, /** whether to automatically reload the table after ordering **/
		// };
		return cfg;
	}

	bindEvents() {
		super.bindEvents();

		this.$el.on(
			'click',
			'input[type=checkbox].active-switch',
			( evt ) => {
				evt.preventDefault();
				if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
					this.bulkTableAction.call( this, evt.currentTarget.dataset.sub_action, [ evt.currentTarget.dataset.rid ] );
				}
				return false;
			}
		);

		this.$el.on(
			'click',
			'button',
			( evt ) => {
				evt.preventDefault();
				if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
					this.bulkTableAction.call( this, evt.currentTarget.dataset.sub_action, [ evt.currentTarget.dataset.rid ] );
				}
				return false;
			}
		);

		[ 'xhr', 'draw', 'select', 'deselect' ].forEach( ( event ) => {
			this.$table.on( event, () => this.rowSelectionChanged() );
		} );
	}

	getButtons() {
		let buttons = super.getButtons();
		buttons.push(
			{
				text: 'Deactivate All',
				name: 'deactivate_all',
				className: 'deactivate-all action btn-outline-warning mb-2',
				action: ( e, dt, node, config ) => {
					if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
						dt.rows().select();
						this.bulkTableAction.call( this, 'deactivate_all' );
					}
				}
			},
		);
		return buttons;
	}

	datatablesAjaxRequest( data, callback, settings ) {
		return super.datatablesAjaxRequest( data, callback, settings )
					.finally( () => this.attachSortable() );
	}

	attachSortable() {
		const group = document.querySelector( '#ShieldTable-SecurityRulesManager tbody' );
		if ( group ) {
			/** https://github.com/SortableJS/Sortable **/
			Sortable.create( group, {
				animation: 600,
				easing: 'cubic-bezier(1, 0, 0, 1)',
				handle: '.drag',

				ghostClass: 'list-group-item-info',
				chosenClass: 'list-group-item-info',
				dragClass: 'list-group-item-info',

				onEnd: ( evt ) => {
					if ( evt.newIndex !== evt.oldIndex ) {
						const items = [];
						group.querySelectorAll( 'td.drag > div' )
							 .forEach( ( item ) => {
								 items.push( item.dataset.rid );
							 } );
						this.action( {
							sub_action: 'reorder',
							rids: items,
						} );
					}
				},
			} );
		}
	}

	action( params = {} ) {
		return ( new AjaxService() )
		.send( ObjectOps.Merge( this._base_data.ajax.table_action, params ) )
		.then( ( resp ) => {
			if ( resp.success ) {
				this.tableReload();
				if ( resp.data.message.length > 0 ) {
					shieldServices.notification().showMessage( resp.data.message, resp.success );
				}
			}
			else {
				alert( resp.data.message );
				console.log( resp );
			}
		} );
	}
}