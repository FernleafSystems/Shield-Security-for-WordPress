import { BaseService } from "./BaseService";
import { AjaxService } from "./AjaxService";

export class BlockPageMagicLink extends BaseService {

	init() {
		this.href = document.getElementById( 'MagicLinkSendEmail' ) || false;
		this.exec();
	}

	canRun() {
		return this.href && typeof this._base_data !== 'undefined';
	}

	run() {
		this.href && this.href.addEventListener( 'click', ( evt ) => {
			( new AjaxService() )
			.send( this._base_data )
			.finally();
		}, false );
	}
}