import $ from 'jquery';
import 'select2';
import { BaseService } from "./BaseService";
import { Modal } from "bootstrap";
import { ObjectOps } from "./ObjectOps";
import { SuperSearchResults } from "./SuperSearchResults";

export class SuperSearchService extends BaseService {

	init() {

		this.theModal = null;

		$( document ).on( 'click', '#SuperSearchLaunch input', ( evt ) => {
			evt.preventDefault();

			if ( this.theModal === null ) {
				this.theModal = document.getElementById( 'ModalSuperSearchBox' );
				this.theModal.addEventListener( 'shown.bs.modal', event => {
					this.theModal.getElementsByTagName( 'input' )[ 0 ].focus();
				} );
				new SuperSearchResults( this._base_data )
			}

			( new Modal( this.theModal ) ).show();

			return false;
		} );

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
}