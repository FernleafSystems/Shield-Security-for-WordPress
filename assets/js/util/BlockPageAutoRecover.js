import { BaseService } from "./BaseService";

export class BlockPageAutoRecover extends BaseService {

	init() {
		this.the_p = document.getElementById( 'unblock_confirm' ) || false;
		this.exec();
	}

	canRun() {
		return this.the_p;
	}

	run() {
		let the_cb = document.createElement( "input" );
		let the_lab = document.createElement( "label" );
		let the_txt = document.createTextNode( "I confirm that I'm accessing this site legitimately" );
		the_cb.type = "checkbox";
		the_cb.id = "_confirm";
		the_cb.name = "_confirm";
		the_cb.value = "Y";
		this.the_p.appendChild( the_lab );
		the_lab.appendChild( the_cb );
		the_lab.appendChild( the_txt );

		the_cb.onchange = function ( evt ) {
			const button = document.getElementById( "submitUnblock" ) || false;
			if ( button ) {
				if ( evt.currentTarget.checked ) {
					button.removeAttribute( 'disabled' );
				}
				else {
					button.setAttribute( 'disabled', 'disabled' );
				}
			}
		};
	}
}