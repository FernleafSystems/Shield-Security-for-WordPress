import $ from 'jquery';
import { BaseService } from "./BaseService";
import { ObjectOps } from "./ObjectOps";
import { AjaxService } from "./AjaxService";

export class SuperSearchResults extends BaseService {

	init() {
		this.searchTimeout = false;

		$( document ).on( 'keyup', '#ModalSuperSearchBox input.search-text', ( evt ) => {
			this.displayResults( $( evt.currentTarget ).val() )
		} );

		$( document ).on( 'select2:open', () => {
			document.querySelector( '.select2-search__field' ).focus();
		} );

		$( document ).on( '#SuperSearchBox select2:select', ( evt ) => {
			/**
			 * Note: IP Address results are handled separately within IPAnalyse
			 */
			let optResultData = evt.params.data;
			if ( typeof optResultData.ip === 'undefined' ) {
				optResultData.new_window ? window.open( evt.params.data.href ) : window.location.href = evt.params.data.href;
			}
		} );
	}

	displayResults( search_text ) {

		if ( this.searchTimeout ) {
			clearTimeout( this.searchTimeout );
		}

		if ( search_text !== '' ) {
			this.searchTimeout = setTimeout( () => {

				$( '#ModalSuperSearchBox .modal-body' ).html(
					'<div class="d-flex justify-content-center align-items-center"><div class="spinner-border text-success m-5" role="status"><span class="visually-hidden">Loading...</span></div></div>'
				);

				const data = ObjectOps.ObjClone( this._base_data.ajax.render_search_results );
				data[ 'search' ] = search_text;

				( new AjaxService() )
				.send( data, false )
				.then( ( resp ) => {
					if ( resp.success ) {
						$( '#ModalSuperSearchBox .modal-body' ).html( resp.data.render_output );
					}
					else {
						alert( resp.data.error );
					}
				} )
				.finally();
			}, 700 );
		}
	}
}