import { ShieldTableBase } from "./ShieldTableBase";
import { confirmDialog, resolveDialogConfirmLabel, resolveDialogLauncher } from "../ui/ShieldDialog";

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
			action: async ( e, dt, node ) => {
				if ( await confirmReportDelete( resolveDialogLauncher( e, node ) ) ) {
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

	rowSelectionChanged() {
		if ( this.$table.rows( { selected: true } ).count() > 0 ) {
			this.$table.buttons( 'selected-delete:name' ).enable();
		}
		else {
			this.$table.buttons( 'selected-delete:name' ).disable();
		}
	}
}

function confirmReportDelete( launcher ) {
	return confirmDialog( {
		message: shieldStrings.string( 'are_you_sure' ),
		confirmLabel: resolveDialogConfirmLabel( launcher ),
		danger: true,
		launcher,
	} );
}
