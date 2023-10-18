import { BaseService } from "./BaseService";

export class ShieldUI extends BaseService {

	static SetBusy() {
		document.querySelector( 'body' ).classList.add( 'shield-busy' );
	};

	static ClearBusy() {
		document.querySelector( 'body' ).classList.remove( 'shield-busy' );
	};
}