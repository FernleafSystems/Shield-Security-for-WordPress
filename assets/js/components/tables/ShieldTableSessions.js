import { ShieldTableBase } from "./ShieldTableBase";

export class ShieldTableSessions extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-SessionsViewer';
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.dom = 'PBrptip';
		return cfg;
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
				text: 'Delete Selected',
				name: 'selected-delete',
				className: 'select-all action btn-outline-warning mb-2',
				action: ( e, dt, node, config ) => {
					if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
						this.bulkTableAction( 'delete' );
					}
				}
			}
		);
		return buttons;
	}
}
