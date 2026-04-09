import { ShieldTableBase } from "./ShieldTableBase";
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
					if ( triggerEl instanceof HTMLElement ) {
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
			const rid = targetEl instanceof HTMLElement ? targetEl.dataset[ 'rid' ] || '' : '';
			if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
				if ( rid.length < 1 ) {
					return;
				}

				this.sendTableActionRequest(
					this.$table,
					ObjectOps.Merge( this._base_data.ajax.rule_delete, { rid } ),
					'Communications error with site.',
					{ reloadTableOnSuccess: true }
				).catch( () => null );
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
