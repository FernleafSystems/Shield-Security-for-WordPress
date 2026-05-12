import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";

export class BlockPageMagicLink extends BaseComponent {

	init() {
		this.button = document.querySelector( '[data-shield-magic-link-send="1"]' ) || false;
		this.status = document.querySelector( '[data-shield-magic-link-status="1"]' ) || false;
		this.exec();
	}

	canRun() {
		return this.button && this.status;
	}

	run() {
		this.button.addEventListener( 'click', ( evt ) => {
			evt.preventDefault();
			this.setBusy( true );
			this.setStatus( '' );

			( new AjaxService() )
			.send( this._base_data.ajax.unblock_request, false, true )
			.then( ( resp ) => this.handleResponse( resp ) )
			.catch( () => this.setRequestFailedStatus() )
			.finally( () => this.setBusy( false ) );
		}, false );
	}

	handleResponse( resp ) {
		if ( typeof resp?.data?.message === 'string' && resp.data.message.length > 0 ) {
			this.setStatus( resp.data.message );
			return;
		}

		this.setRequestFailedStatus();
	}

	setRequestFailedStatus() {
		this.setStatus( this._base_data.strings.request_failed );
	}

	setBusy( isBusy ) {
		this.button.toggleAttribute( 'disabled', isBusy );
		this.button.setAttribute( 'aria-busy', isBusy ? 'true' : 'false' );
	}

	setStatus( message ) {
		this.status.textContent = message;
	}
}
