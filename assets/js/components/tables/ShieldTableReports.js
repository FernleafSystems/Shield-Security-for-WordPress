import { ShieldTableBase } from "./ShieldTableBase";
import { UiContentActivator } from "../ui/UiContentActivator";

export class ShieldTableReports extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-Reports';
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.dom = 'Brftip';
		return cfg;
	}

	getButtons() {
		let buttons = super.getButtons();
		buttons.push( {
			text: 'Delete Selected',
			name: 'selected-delete',
			className: 'action selected-action delete btn-outline-warning mb-2',
			action: () => {
				if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
					this.bulkTableAction( 'delete' );
				}
			}
		} );
		return buttons;
	}

	bindEvents() {
		super.bindEvents();

		this.$el.on(
			'click',
			'button.delete[data-rid]',
			( evt ) => {
				evt.preventDefault();
				if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
					this.bulkTableAction( 'delete', [ evt.currentTarget.dataset.rid ] );
				}
				return false;
			}
		);

		document.addEventListener( 'shield:mode-panel-opened', ( evt ) => {
			const panel = evt.target?.querySelector?.( '[data-mode-panel="1"].is-open' ) || null;
			if ( panel !== null && panel.querySelector( this.getTableSelector() ) !== null ) {
				UiContentActivator.activateWithin( panel );
			}
		} );
	}

	rowSelectionChanged() {
		if ( this.$table.rows( { selected: true } ).count() > 0 ) {
			this.$table.buttons( 'selected-delete:name' ).enable();
		}
		else {
			this.$table.buttons( 'selected-delete:name' ).disable();
		}
	}
}
