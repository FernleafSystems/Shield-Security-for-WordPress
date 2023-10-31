import $ from 'jquery';
import { BaseService } from "./BaseService";
import { AjaxService } from "./AjaxService";
import { ObjectOps } from "./ObjectOps";
import { OffCanvasService } from "./OffCanvasService";

export class IpRules extends BaseService {

	init() {
		this.exec();
	}

	run() {
		this.handleIpRuleOffcanvasForm();
		this.handleIpDelete();
		this.handleIpRuleForm();
	}

	handleIpRuleOffcanvasForm() {
		$( document ).on( 'click', 'a.offcanvas_form_create_ip_rule', ( evt ) => {
			evt.preventDefault();
			OffCanvasService.RenderCanvas( this._base_data.ajax.render_offcanvas ).finally();
			return false;
		} );
	}

	handleIpRuleForm() {
		document.addEventListener( 'submit', ( evt ) => {
			if ( evt.target.id === 'IpRuleAddForm' ) {
				evt.preventDefault();

				( new AjaxService() )
				.send( ObjectOps.Merge(
					this._base_data.ajax.add_form_submit,
					{ 'form_data': Object.fromEntries( new FormData( evt.target ) ) }
				) )
				.finally();

				return false;
			}
		} );
	}

	handleIpDelete() {
		$( document ).on( 'click', 'td.ip_linked a.ip_delete', ( evt ) => {
			if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
				( new AjaxService() )
				.send( ObjectOps.Merge(
					this._base_data.ajax.delete,
					{ 'rid': $( evt.currentTarget ).data( 'rid' ) }
				) )
				.finally();
			}
		} );
	}
}