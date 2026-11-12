import { ShieldTableBase } from "./ShieldTableBase";

/**
 * The security reports: just the table and its pages. Selecting reports brings up the action bar along the
 * bottom of the screen, as Configure's unsaved changes do, with Delete selected.
 */
export class ShieldTableReports extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-Reports';
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.dom = 'rtip';
		return cfg;
	}

	getButtons() {
		return [];
	}

	setupDatatable() {
		super.setupDatatable();
		this.selectionBar = this.buildSelectionBar();
		window.addEventListener( 'resize', () => this.alignSelectionBar() );
	}

	buildSelectionBar() {
		const bar = document.createElement( 'div' );
		bar.className = 'shield-actionbar';
		bar.setAttribute( 'role', 'region' );
		bar.setAttribute( 'aria-label', 'Selected reports' );
		bar.hidden = true;
		bar.innerHTML = `<span class="shield-actionbar__dot" aria-hidden="true"></span>
			<span class="shield-actionbar__copy" aria-live="polite"><strong data-reports-selection-count="1"></strong></span>
			<button type="button" class="btn btn-sm shield-actionbar__secondary" data-reports-selection-clear="1">Clear selection</button>
			<button type="button" class="btn btn-sm btn-danger" data-reports-selection-delete="1">Delete selected</button>`;

		bar.querySelector( '[data-reports-selection-clear="1"]' ).addEventListener( 'click', () => {
			this.$table.rows().deselect();
		} );
		bar.querySelector( '[data-reports-selection-delete="1"]' ).addEventListener( 'click', async ( evt ) => {
			if ( await confirmReportDelete( evt.currentTarget ) ) {
				this.bulkTableAction( 'delete' );
			}
		} );

		this.resolveTableContainer().after( bar );
		return bar;
	}

	/**
	 * The bar is fixed to the bottom of the screen, lined up with the table.
	 */
	alignSelectionBar() {
		if ( !this.selectionBar || this.selectionBar.hidden ) {
			return;
		}
		const rect = this.resolveTableContainer().getBoundingClientRect();
		this.selectionBar.style.left = `${Math.max( 0, rect.left )}px`;
		this.selectionBar.style.width = `${rect.width}px`;
	}

	bindEvents() {
		super.bindEvents();

		this.$el.on(
			'click',
			'button.delete[data-rid]',
			( evt ) => {
				evt.preventDefault();
				const target = evt.currentTarget;
				confirmReportDelete( target ).then( ( confirmed ) => {
					if ( confirmed ) {
						this.bulkTableAction( 'delete', [ target.dataset.rid ] );
					}
				} );
				return false;
			}
		);
	}

	/**
	 * Called as rows are drawn and selected, before the bar exists on the first draw.
	 */
	rowSelectionChanged() {
		if ( !this.selectionBar ) {
			return;
		}
		const count = this.$table.rows( { selected: true } ).count();
		this.selectionBar.hidden = count === 0;
		this.selectionBar.querySelector( '[data-reports-selection-count="1"]' ).textContent =
			count === 1 ? '1 report selected' : `${count} reports selected`;
		this.alignSelectionBar();
	}
}

function confirmReportDelete( launcher ) {
	const dialog = shieldServices.dialog();
	return dialog.confirm( {
		message: shieldStrings.string( 'are_you_sure' ),
		confirmLabel: dialog.resolveConfirmLabel( launcher ),
		danger: true,
		launcher,
	} );
}
