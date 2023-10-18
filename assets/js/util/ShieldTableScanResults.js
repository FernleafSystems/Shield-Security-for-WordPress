import $ from 'jquery';
import { ShieldTableBase } from "./ShieldTableBase";
import { ShieldOverlay } from "./ShieldOverlay";
import { ObjectOps } from "./ObjectOps";
import { AjaxService } from "./AjaxService";

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
			'button.action.delete',
			( evt ) => {
				evt.preventDefault();
				if ( confirm( shield_vars.strings.are_you_sure ) ) {
					this.bulkTableAction.call( this, 'delete', [ evt.currentTarget.dataset.rid ] );
				}
				return false;
			}
		);

		this.$el.on(
			'click',
			'button.action.ignore',
			( evt ) => {
				evt.preventDefault();
				this.bulkTableAction.call( this, 'ignore', [ evt.currentTarget.dataset.rid ] );
				return false;
			}
		);

		this.$el.on(
			'click',
			'button.action.repair',
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
						let $fileViewModal = $( '#ShieldModalContainer' );
						$( '.modal-content', $fileViewModal ).html( resp.data.html );
						$fileViewModal.modal( 'show' );
						$fileViewModal[ 0 ].querySelectorAll( 'pre.icwp-code-render code' ).forEach( ( el ) => {
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
					if ( confirm( this._base_data.strings.are_you_sure ) ) {
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
					else if ( confirm( this._base_data.strings.absolutely_sure ) ) {
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