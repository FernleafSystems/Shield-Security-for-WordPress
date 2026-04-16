import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";

export class LicenseHandler extends BaseComponent {
	init() {
		this.busyButtons = [];
		this.busyRoot = null;
		this.isBusy = false;

		shieldEventsHandler_Main.add_Click( '.license-action', ( targetEl ) => {
			const pageRoot = targetEl.closest( '.license-page' );
			const action = String( targetEl.dataset[ 'action' ] || '' ).trim();
			const actionData = this._base_data.ajax?.[ action ] || null;

			if ( this.isBusy || actionData === null || pageRoot === null ) {
				return;
			}

			if ( action !== 'clear' || confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
				this.enterBusyState( pageRoot );

				( new AjaxService() )
					.send( actionData, true )
					.finally( () => this.exitBusyState() );
			}
		} );
	}

	enterBusyState( pageRoot ) {
		this.isBusy = true;
		this.busyRoot = pageRoot;
		this.busyButtons = [ ...pageRoot.querySelectorAll( '.license-action' ) ].map( ( button ) => ( {
			button,
			wasDisabled: button.disabled
		} ) );

		pageRoot.setAttribute( 'aria-busy', 'true' );
		this.busyButtons.forEach( ( item ) => item.button.disabled = true );
	}

	exitBusyState() {
		this.busyButtons.forEach( ( item ) => {
			item.button.disabled = item.wasDisabled;
		} );
		this.busyRoot?.removeAttribute( 'aria-busy' );
		this.busyButtons = [];
		this.busyRoot = null;
		this.isBusy = false;
	}
}
