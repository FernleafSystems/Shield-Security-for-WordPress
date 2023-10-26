import $ from 'jquery';
import '../../deprecated/u2f-bundle.js';
import { ProviderBase } from "./ProviderBase";

/**
 * @deprecated 18.5
 */
export class ProviderU2f extends ProviderBase {

	init() {
		console.log( typeof u2fApi );
		let self = this;
		typeof u2fApi !== 'undefined' &&
		u2fApi.isSupported()
			  .then( ( supported ) => {

				  let labelRegEx = new RegExp( "^[a-zA-Z0-9_-]{1,16}$" );
				  let $registerButton = $( 'button#icwp_u2f_key_reg' );
				  let $oU2fStatus = $( '#icwp_u2f_section p.description' );

				  if ( supported ) {
					  $registerButton.prop( 'disabled', false );
					  $registerButton.on( 'click', () => {
						  let label = prompt( this._base_data.strings.prompt_dialog, "<Insert Label>" );
						  if ( typeof label === 'undefined' || label === null ) {
							  alert( this._base_data.strings.err_no_label )
						  }
						  else if ( !labelRegEx.test( label ) ) {
							  alert( this._base_data.strings.err_invalid_label )
						  }
						  else {
							  u2fApi.register( this._base_data.reg_request, this._base_data.signs )
									.then( ( u2fResponse ) => {
										u2fResponse.label = label;
										this._base_data.ajax.profile_u2f_add.icwp_wpsf_new_u2f_response = u2fResponse;
										this.sendReq( this._base_data.ajax.profile_u2f_add );
									} )
									.catch( ( resp ) => {
										$oU2fStatus.text( this._base_data.strings.failed );
										$oU2fStatus.css( 'font-weight', 'bolder' )
												   .css( 'color', 'red' );
									} );
						  }
					  } );
				  }
				  else {
					  $registerButton.prop( 'disabled', true );
					  $oU2fStatus.text( self._base_data.strings.not_supported );
				  }
			  } )
			  .catch();

		$( 'a.icwpWpsf-U2FRemove' ).on( 'click', ( evt ) => {
			evt.preventDefault();
			this._base_data.ajax.profile_u2f_remove.u2fid = $( evt.currentTarget ).data( 'u2fid' );
			this.sendReq( this._base_data.ajax.profile_u2f_remove );
			return false;
		} );
	}
}