import { BaseComponent } from "../BaseComponent";
import { SuperSearchResults } from "./SuperSearchResults";
import { BootstrapModals } from "../ui/BootstrapModals";

/**
 * Opens the search dialog from the sidebar's search, or with Ctrl+K.
 */
export class SuperSearchService extends BaseComponent {

	init() {
		this.theModalDisplayed = false;
		this.theModal = null;

		shieldEventsHandler_Main.add_Click( '#SuperSearchLaunch input', () => this.launchModal() );

		/**
		 * https://www.freecodecamp.org/news/javascript-keycode-list-keypress-event-key-codes/
		 * So we only intercept Ctrl+k if the modal isn't currently displayed. Otherwise, we let the browser
		 * have its say on what happens. This allows for accessing the browser address field with a double-press.
		 */
		// Caught first, and kept from WordPress's own command palette, which listens for the same keys.
		window.addEventListener( 'keydown', ( evt ) => {
			if ( evt.ctrlKey && evt.key === 'k' && !this.theModalDisplayed ) {
				evt.preventDefault();
				evt.stopImmediatePropagation();
				this.launchModal();
				return false;
			}
		}, true );
	}

	launchModal() {
		if ( this.theModalDisplayed !== true ) {
			this.theModalDisplayed = true;
			if ( this.theModal === null ) {
				this.theModal = document.getElementById( 'ModalSuperSearchBox' );
				this.theModal.addEventListener( 'shown.bs.modal', () => {
					this.theModal.querySelector( '#ModalSuperSearchInput' ).focus();
				} );
				this.theModal.addEventListener( 'hidden.bs.modal', () => this.theModalDisplayed = false );
				new SuperSearchResults( this._base_data );
			}
			BootstrapModals.Show( this.theModal );
		}
	}
}
