import { ShieldTableBase } from "./ShieldTableBase";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";

export class ShieldTableIpRules extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-IpRules';
	}

	getButtons() {
		return [
			{
				text: 'Create New IP Rule',
				name: 'create-ip-rule',
				className: 'action create-ip-rule btn-outline-info mb-2',
				action: () => {
					const triggerEl = document.querySelector( 'a.offcanvas_form_create_ip_rule' );
					if ( triggerEl ) {
						triggerEl.click();
					}
				}
			},
			...super.getButtons()
		];
	}

	bindEvents() {
		super.bindEvents();

		shieldEventsHandler_Main.add_Click( 'td.ip_linked a.ip_delete', ( targetEl ) => {
			if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
				( new AjaxService() )
				.send( ObjectOps.Merge( this._base_data.ajax.rule_delete, { rid: targetEl.dataset[ 'rid' ] } ) )
				.then( () => this.tableReload() )
				.finally();
			}
		} );
		shieldEventsHandler_Main.addHandler(
			'hidden.bs.offcanvas',
			'.offcanvas.offcanvas_form_ip_rule_add',
			() => this.tableReload()
		);
		shieldEventsHandler_Main.addHandler(
			'hidden.bs.offcanvas',
			'.offcanvas.offcanvas_ipanalysis',
			() => this.tableReload()
		);
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.language.search = "Search IP";
		cfg.select = {
			style: 'api'
		};
		return cfg;
	}
}
