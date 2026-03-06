import { ShieldTableBase } from "./ShieldTableBase";
import { DataTableVisibilityAdjuster } from "./DataTableVisibilityAdjuster";

export class ShieldTableReports extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-Reports';
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.dom = 'Brftip';
		cfg.select = false;
		return cfg;
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

		document.querySelectorAll( '[data-mode-shell="1"]' ).forEach( ( modeShell ) => {
			modeShell.addEventListener( 'shield:mode-panel-opened', () => {
				const panel = modeShell.querySelector( '[data-mode-panel="1"].is-open' );
				if ( panel !== null && panel.querySelector( this.getTableSelector() ) !== null ) {
					DataTableVisibilityAdjuster.adjustWithinNextFrame( panel );
				}
			} );
		} );
	}
}
