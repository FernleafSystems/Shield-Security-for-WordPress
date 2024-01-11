import $ from 'jquery';
import 'select2';
import { BaseComponent } from "../BaseComponent";
import { Modal } from "bootstrap";
import { ObjectOps } from "../../util/ObjectOps";
import { SuperSearchResults } from "./SuperSearchResults";

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
		document.addEventListener( 'keydown', ( evt ) => {
			if ( evt.ctrlKey && evt.key === 'k' && !this.theModalDisplayed ) {
				evt.preventDefault();
				this.launchModal();
				return false;
			}
		}, false );

		$( '#SuperSearchBox select' ).select2( {
			minimumInputLength: 3,
			language: {
				inputTooShort: () => this._base_data.strings.enter_at_least_3_chars
			},
			placeholder: this._base_data.strings.placeholder,
			templateResult: ( val ) => ( typeof val.icon === 'undefined' ? '' : ' <span class="svg-container me-2">' + val.icon + '</span>' )
				+ val.text,
			escapeMarkup: ( content ) => content,
			ajax: {
				delay: 750,
				url: this._base_data.ajax.select_search.ajaxurl,
				contentType: "application/json; charset=utf-8",
				dataType: 'json',
				data: ( params ) => {
					let query = ObjectOps.ObjClone( this._base_data.ajax.select_search );
					query.search = params.term;
					return query;
				},
				processResults: ( response ) => {
					return {
						results: response.data.results
					};
				},
			}
		} );
	}

	launchModal() {
		if ( this.theModalDisplayed !== true ) {
			this.theModalDisplayed = true;
			if ( this.theModal === null ) {
				this.theModal = document.getElementById( 'ModalSuperSearchBox' );
				this.theModal.addEventListener( 'shown.bs.modal', evt => {
					this.theModal.getElementsByTagName( 'input' )[ 0 ].focus();
				} );
				this.theModal.addEventListener( 'hidden.bs.modal', evt => this.theModalDisplayed = false );
				new SuperSearchResults( this._base_data )
			}
			( new Modal( this.theModal ) ).show();
		}
	}
}