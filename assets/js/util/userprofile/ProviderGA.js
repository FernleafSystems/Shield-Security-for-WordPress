import $ from 'jquery';
import QRCode from 'qrcode'
import { ProviderBase } from "./ProviderBase";

export class ProviderGA extends ProviderBase {

	init() {
		let svgCodeCanvas = document.getElementById( 'SvgQrCode' );
		if ( svgCodeCanvas ) {
			QRCode.toCanvas( svgCodeCanvas, this._base_data.vars.qr_code_auth, {
				width: 300,
			} )
				  .then( url => {
					  // console.log( url )
				  } )
				  .catch( err => {
					  console.error( err )
				  } );
		}

		let $gaCode = $( 'input[type=text]#shield_gacode' );
		if ( $gaCode.length > 0 ) {
			$( document ).on( 'change, keyup', $gaCode, () => {
				$gaCode.val( $gaCode.val()
									.replace( /[^A-F0-9]/gi, '' )
									.toUpperCase()
									.substring( 0, 6 ) );

				if ( $gaCode.val().length === 6 ) {
					$gaCode.prop( 'disabled', 'disabled' );
					this._base_data.ajax.profile_ga_toggle.ga_otp = $gaCode.val();
					this.sendReq( this._base_data.ajax.profile_ga_toggle );
				}
			} );
		}

		$( document ).on( 'click', '#shield_ga_remove', () => {
			this.sendReq( this._base_data.ajax.profile_ga_toggle );
		} );
	}
}