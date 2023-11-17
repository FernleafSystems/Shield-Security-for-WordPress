import $ from 'jquery';
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { ShieldOverlay } from "../ui/ShieldOverlay";
import { ShieldTableBase } from "./ShieldTableBase";

export class ShieldTableScanResults extends ShieldTableBase {

	getTableSelector() {
		return this._base_data.vars.table_selector;
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.dom = 'Brpftip';
		return cfg;
	}

	bindEvents() {
		super.bindEvents();

		this.$el.on(
			'click',
			'td.actions > button.action.delete',
			( evt ) => {
				evt.preventDefault();
				if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
					this.bulkTableAction.call( this, 'delete', [ evt.currentTarget.dataset.rid ] );
				}
				return false;
			}
		);

		this.$el.on(
			'click',
			'td.actions > button.action.ignore',
			( evt ) => {
				evt.preventDefault();
				this.bulkTableAction.call( this, 'ignore', ( 'rid' in evt.currentTarget.dataset ) ? [ evt.currentTarget.dataset.rid ] : [] );
				return false;
			}
		);

		this.$el.on(
			'click',
			'td.actions > button.action.repair',
			( evt ) => {
				evt.preventDefault();
				this.$table.rows().deselect();
				this.bulkTableAction.call( this, 'repair', [ evt.currentTarget.dataset.rid ] );
				return false;
			}
		);

		this.$el.on(
			'click',
			'.action.view-file',
			( evt ) => {
				evt.preventDefault();

				const data = ObjectOps.ObjClone( this._base_data.ajax.render_item_analysis );
				data[ 'rid' ] = evt.currentTarget.dataset.rid;

				( new AjaxService() )
				.send( data )
				.then( ( resp ) => {
					if ( resp.success ) {
						const modal = document.getElementById( 'ShieldModalContainer' );
						modal.querySelector( '.modal-content' ).innerHTML = resp.data.html;
						$( modal ).modal( 'show' );
						modal.querySelectorAll( 'pre.icwp-code-render code' ).forEach( ( el ) => {
							// hljs.highlightElement( el );
						} );
					}
					else {
						alert( resp.data.message );
						// console.log( resp );
					}
				} )
				.catch( ( error ) => {
					console.log( error );
				} )
				.finally( () => ShieldOverlay.Hide() );

				return false;
			}
		);
	}

	getButtons() {
		let buttons = super.getButtons();
		buttons.push(
			{
				text: 'De/Select All',
				name: 'all-select',
				className: 'select-all action btn-outline-secondary mb-2',
				action: ( e, dt, node, config ) => {
					let total = dt.rows().count()
					if ( dt.rows( { selected: true } ).count() < total ) {
						dt.rows().select();
					}
					else {
						dt.rows().deselect();
					}
				}
			},
			{
				text: 'Ignore Selected',
				name: 'selected-ignore',
				className: 'action selected-action ignore btn-outline-secondary mb-2',
				action: ( e, dt, node, config ) => {
					if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
						this.bulkTableAction.call( this, 'ignore' );
					}
				}
			},
			{
				text: 'Delete/Repair Selected',
				name: 'selected-repair',
				className: 'action selected-action repair btn-outline-secondary mb-2',
				action: ( e, dt, node, config ) => {
					if ( dt.rows( { selected: true } ).count() > 20 ) {
						alert( "Sorry, this tool isn't designed for such large repairs. We recommend completely removing and reinstalling the item." )
					}
					else if ( confirm( shieldStrings.string( 'absolutely_sure' ) ) ) {
						this.bulkTableAction.call( this, 'repair-delete' );
					}
				}
			}
		);
		return buttons;
	}

	rowSelectionChanged() {
		if ( this.$table.rows( { selected: true } ).count() > 0 ) {
			this.$table.buttons( 'selected-ignore:name, selected-repair:name' ).enable();
		}
		else {
			this.$table.buttons( 'selected-ignore:name, selected-repair:name' ).disable();
		}
	};
}