import $ from 'jquery';
import { BaseService } from "./BaseService";
import { AjaxService } from "./AjaxService";
import { ObjectOps } from "./ObjectOps";

export class FileLocker extends BaseService {

	init() {
		$( document ).on( 'change', '#FileLockerFileSelect', ( evt ) => this.#select( evt ) );
		$( document ).on( 'submit', 'form.filelocker_fileaction', ( evt ) => this.#fileAction( evt ) );
	}

	#select( evt ) {
		let $selected = $( evt.currentTarget ).find( ":selected" );
		if ( $selected.val() !== '-' ) {

			const data = ObjectOps.ObjClone( this._base_data.ajax.render_diff );
			data[ 'rid' ] = $selected.val();

			( new AjaxService() )
			.send( data )
			.then( ( resp ) => {
				if ( resp.success ) {
					$( '#FileLockerDiffContents' ).html( resp.data.html );
				}
				else {
					alert( resp.data.error );
				}
			} )
			.catch( ( error ) => {
				alert( 'Sorry, something went wrong with the request.' );
				console.log( error );
			} );
			$( 'option[value="-"]', $selected ).prop( 'selected', true );
		}
	};

	#fileAction( evt ) {
		evt.preventDefault();

		let $form = $( evt.currentTarget );

		let ajax_vars = ObjectOps.ObjClone( this._base_data.ajax.file_action );
		let $button = $( 'input[type=submit]', $form );
		$button.attr( 'disabled', 'disabled' )
		ajax_vars.confirmed = $( 'input[type=checkbox]', $form ).is( ':checked' ) ? 1 : 0;
		ajax_vars.rid = $button.data( 'rid' );
		ajax_vars.file_action = $button.data( 'action' );

		( new AjaxService() )
		.send( ajax_vars )
		.finally( () => $button.removeAttr( 'disabled' ) );

		return false;
	}
}