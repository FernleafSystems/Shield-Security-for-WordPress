import { ShieldTableBase } from "./ShieldTableBase";

export class ShieldTableImportExportSites extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-ImportExportSites';
	}

	getButtons() {
		let buttons = super.getButtons();
		buttons.push( {
			text: 'Queue Sync',
			name: 'queue-sync',
			className: 'action selected-action queue-sync btn-outline-primary mb-2',
			action: () => this.bulkTableAction( 'queue_sync' )
		} );
		return buttons;
	}

	addButtons() {
		super.addButtons();
		this.$table.buttons( 'queue-sync:name' ).disable();
	}

	rowSelectionChanged() {
		if ( this.$table.rows( { selected: true } ).count() > 0 ) {
			this.$table.buttons( 'queue-sync:name' ).enable();
		}
		else {
			this.$table.buttons( 'queue-sync:name' ).disable();
		}
	}
}
