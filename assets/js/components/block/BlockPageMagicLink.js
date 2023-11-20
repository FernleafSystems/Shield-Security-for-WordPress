import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";

export class BlockPageMagicLink extends BaseComponent {

	init() {
		this.href = document.getElementById( 'MagicLinkSendEmail' ) || false;
		this.exec();
	}

	canRun() {
		return this.href;
	}

	run() {
		const self = this;
		this.href.addEventListener( 'click', ( evt ) => {
			( new AjaxService() )
			.send( self._base_data.ajax.unblock_request )
			.finally();
		}, false );
	}
}