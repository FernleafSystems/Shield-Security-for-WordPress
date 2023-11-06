import $ from 'jquery';
import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { ObjectOps } from "../../util/ObjectOps";

export class MainwpServer extends BaseComponent {

	init() {
		this.container = document.querySelector( '.mainwp_page_Extensions-Wp-Simple-Firewall' );
		this.exec();
	}

	canRun() {
		return this.container !== null;
	}

	run() {
		this.$c = $( this.container );
		this.bindEvents();
	}

	bindEvents() {
		this.$c.on( 'click', '.site-dropdown a.site_action', ( evt ) => {
			evt.preventDefault();

			const $target = $( evt.currentTarget );

			this.sendReq(
				ObjectOps.Merge( this._base_data.ajax.site_action, {
					client_site_id: $target.parent().data( 'sid' ),
					client_site_action_data: $target.data( 'site_action' )
				} )
			);

			return false;
		} );
	}

	sendReq( reqData ) {
		( new AjaxService() )
		.send( reqData )
		.finally();
	}
}