import { ShieldTableBase } from "./ShieldTableBase";
import { ObjectOps } from "../../util/ObjectOps";
import { AjaxService } from "../services/AjaxService";

export class ShieldTableSessions extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-SessionsViewer';
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.dom = 'Brptip';
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

						let data = ObjectOps.ObjClone( this._base_data.ajax.table_action )
						data.sub_action = 'delete';
						data.rids = this.getSelectedRIDs();

						( new AjaxService() )
						.send( data )
						.then( ( resp ) => {

							if ( resp.success ) {
								this.tableReload();
								shieldServices.notification().showMessage( resp.data.message, resp.success );
							}
							else {
								alert( resp.data.message );
								// console.log( resp );
							}
						} )
						.catch( ( error ) => {
							console.log( error );
						} );
					}
				}
			}
		);
		return buttons;
	}
}