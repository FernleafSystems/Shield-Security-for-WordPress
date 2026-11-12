import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { Forms } from "../../util/Forms";
import { OptionsFormSubmit } from "./OptionsFormSubmit";
import { sendEncodedOptionsSave } from "./OptionsSaveRequest";

/**
 * One save bar for a Configure zone page. Every open component form on the page saves in a
 * single request, so related changes in different components never need separate saves.
 *
 * The same option can appear in more than one component (e.g. a shared on/off switch), so an
 * edit is mirrored into every copy on the page before the forms are merged for saving.
 */
export class ConfigureSaveBar extends BaseAutoExecComponent {

	static ROOT_SELECTOR = '[data-configure-diagnosis="1"]';

	run() {
		document.addEventListener( 'shield:options-dirty-change', ( evt ) => this.refreshFor( evt.target ) );
		document.addEventListener( 'change', ( evt ) => this.mirrorDuplicates( evt.target ) );
		document.addEventListener( 'input', ( evt ) => this.mirrorDuplicates( evt.target ) );

		// Capture phase, so a zone form's own submit (e.g. Enter in a text field) never saves just one component.
		document.addEventListener( 'submit', ( evt ) => {
			const root = this.rootFor( evt.target );
			if ( root !== null ) {
				evt.preventDefault();
				evt.stopImmediatePropagation();
				this.save( root );
			}
		}, true );

		shieldEventsHandler_Main.add_Click( '[data-configure-savebar-save="1"]', ( button ) => {
			const root = this.rootFor( button );
			root !== null && this.save( root );
		} );
		shieldEventsHandler_Main.add_Click( '[data-configure-savebar-discard="1"]', ( button ) => {
			const root = this.rootFor( button );
			root !== null && this.discard( root );
		} );

		window.addEventListener( 'resize', () => {
			document.querySelectorAll( '[data-configure-savebar="1"]:not([hidden])' ).forEach( ( bar ) => {
				const root = bar.closest( ConfigureSaveBar.ROOT_SELECTOR );
				root instanceof HTMLElement && this.alignToRoot( root, bar );
			} );
		} );

		window.addEventListener( 'beforeunload', ( evt ) => {
			if ( document.querySelector( `${ConfigureSaveBar.ROOT_SELECTOR} .shield-option-item.is-dirty` ) !== null ) {
				evt.preventDefault();
				evt.returnValue = '';
			}
		} );
	}

	/**
	 * @param {EventTarget|null} target
	 * @return {HTMLElement|null}
	 */
	rootFor( target ) {
		const root = target instanceof Element ? target.closest( ConfigureSaveBar.ROOT_SELECTOR ) : null;
		return root instanceof HTMLElement && root.querySelector( '[data-configure-savebar="1"]' ) !== null ? root : null;
	}

	/**
	 * @param {HTMLElement} root
	 * @return {HTMLFormElement[]}
	 */
	dirtyForms( root ) {
		return [ ...root.querySelectorAll( 'form.options_form_for' ) ].filter(
			( form ) => form instanceof HTMLFormElement && form.querySelector( '.shield-option-item.is-dirty' ) !== null
		);
	}

	refreshFor( target ) {
		const root = this.rootFor( target );
		if ( root === null ) {
			return;
		}
		const bar = root.querySelector( '[data-configure-savebar="1"]' );
		const dirtyKeys = new Set(
			[ ...root.querySelectorAll( '.shield-option-item.is-dirty' ) ].map( ( item ) => item.dataset.optionKey || '' )
		);
		const count = dirtyKeys.size;
		bar.hidden = count === 0;
		root.classList.toggle( 'has-configure-savebar', count > 0 );
		if ( count === 0 ) {
			return;
		}
		this.alignToRoot( root, bar );

		const template = count === 1 ? bar.dataset.countOne : bar.dataset.countMany;
		bar.querySelector( '[data-configure-savebar-count="1"]' ).textContent = String( template || '%s' ).replace( '%s', String( count ) );
		bar.querySelector( '[data-configure-savebar-where="1"]' ).textContent = [ ...new Set( this.dirtyForms( root ).map(
			( form ) => form.closest( '.shield-detail-item--configure' )?.querySelector( '.shield-detail-row__title' )?.textContent?.trim() || ''
		) ) ].filter( ( title ) => title.length > 0 ).join( ', ' );
	}

	/**
	 * The bar is fixed to the viewport bottom (an ancestor scroll container defeats position: sticky),
	 * so it is lined up horizontally with the zone it belongs to.
	 */
	alignToRoot( root, bar ) {
		const rect = root.getBoundingClientRect();
		bar.style.left = `${Math.max( 0, rect.left )}px`;
		bar.style.width = `${rect.width}px`;
	}

	/**
	 * Copies an edit into the other copies of the same option on this page.
	 * @param {EventTarget|null} control
	 */
	mirrorDuplicates( control ) {
		const root = this.rootFor( control );
		if ( root === null || control.dataset?.configureMirroring === '1'
			 || !( control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement )
			 || !control.name || control.closest( '.shield-option-control-col' ) === null ) {
			return;
		}

		// Radios (panel sliders and choices) are mirrored like checkboxes: the same choice in another form.
		const isCheck = control instanceof HTMLInputElement && ( control.type === 'checkbox' || control.type === 'radio' );
		root.querySelectorAll( `form.options_form_for [name="${CSS.escape( control.name )}"]` ).forEach( ( copy ) => {
			if ( copy === control || ( isCheck && copy.value !== control.value ) || copy.form === control.form
				 || ( copy instanceof HTMLInputElement && copy.type === 'hidden' ) ) {
				return;
			}
			if ( isCheck ) {
				if ( copy.checked === control.checked ) {
					return;
				}
				copy.checked = control.checked;
			}
			else {
				if ( copy.value === control.value ) {
					return;
				}
				copy.value = control.value;
			}
			copy.dataset.configureMirroring = '1';
			copy.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			delete copy.dataset.configureMirroring;
		} );
	}

	/**
	 * @param {HTMLElement} root
	 */
	save( root ) {
		const bar = root.querySelector( '[data-configure-savebar="1"]' );
		const forms = this.dirtyForms( root );
		if ( forms.length === 0 || bar.dataset.busy === '1' ) {
			return;
		}

		const mismatch = forms.map( ( form ) => OptionsFormSubmit.findPasswordMismatch( form ) ).find( ( field ) => field !== null );
		if ( mismatch ) {
			mismatch.classList.add( 'is-invalid' );
			shieldServices.dialog().message( { message: bar.dataset.pinMismatch, launcher: mismatch } );
			return;
		}

		const actionKey = forms[ 0 ].dataset.optionsSaveAction;
		if ( !actionKey || !( actionKey in this._base_data.ajax ) ) {
			return;
		}

		// Forms list their keys in a hidden field; the merged request must carry the union.
		const keys = new Set();
		const formData = {};
		forms.forEach( ( form ) => {
			const data = Forms.Serialize( form );
			String( data.all_opts_keys || '' ).split( ',' ).filter( ( key ) => key.length > 0 ).forEach( ( key ) => keys.add( key ) );
			Object.assign( formData, data );
		} );
		formData.all_opts_keys = [ ...keys ].join( ',' );

		const saveButton = bar.querySelector( '[data-configure-savebar-save="1"]' );
		const saveLabel = saveButton.textContent;
		bar.dataset.busy = '1';
		saveButton.disabled = true;
		saveButton.textContent = bar.dataset.savingLabel || saveLabel;

		sendEncodedOptionsSave( this._base_data.ajax[ actionKey ], formData )
		.then( ( resp ) => {
			if ( resp?.data?.page_reload ) {
				window.location.reload();
				return;
			}
			if ( root.closest( '[data-configure-landing="1"]' ) === null ) {
				// Elsewhere (such as Reports) the forms stay: what was saved is now what each control is compared
				// against, so nothing reads as unsaved.
				forms.forEach( ( form ) => this.markSaved( form ) );
				this.refreshFor( forms[ 0 ] );
			}
			else {
				forms.forEach( ( form ) => form.querySelectorAll( '.shield-option-item.is-dirty' ).forEach( ( item ) => item.classList.remove( 'is-dirty' ) ) );
			}
			// The Configure landing reloads the whole zone after a save, refreshing issues and every form.
			forms[ 0 ].dispatchEvent( new CustomEvent( 'shield:expansion-form-saved', { bubbles: true } ) );
		} )
		.catch( () => null )
		.finally( () => {
			delete bar.dataset.busy;
			saveButton.disabled = false;
			saveButton.textContent = saveLabel;
		} );
	}

	/**
	 * @param {HTMLFormElement} form
	 */
	markSaved( form ) {
		form.querySelectorAll( 'input, select, textarea' ).forEach( ( control ) => {
			if ( control instanceof HTMLInputElement && [ 'checkbox', 'radio' ].includes( control.type ) ) {
				control.defaultChecked = control.checked;
			}
			else if ( control instanceof HTMLSelectElement ) {
				[ ...control.options ].forEach( ( option ) => option.defaultSelected = option.selected );
			}
			else if ( control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement ) {
				control.defaultValue = control.value;
			}
		} );
		form.querySelectorAll( '.shield-option-item.is-dirty' ).forEach( ( item ) => {
			item.classList.remove( 'is-dirty' );
			item.querySelector( '[data-option-unsaved="1"]' )?.setAttribute( 'hidden', '' );
		} );
	}

	/**
	 * @param {HTMLElement} root
	 */
	discard( root ) {
		this.dirtyForms( root ).forEach( ( form ) => {
			form.reset();
			form.querySelectorAll( '.shield-option-control-col [name]' ).forEach( ( control ) => {
				control.dataset.configureMirroring = '1';
				control.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				delete control.dataset.configureMirroring;
			} );
		} );
	}
}
