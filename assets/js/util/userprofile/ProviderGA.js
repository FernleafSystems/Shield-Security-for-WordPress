import $ from 'jquery';
import QRCode from 'qrcode'
import { ProviderBase } from "./ProviderBase";

export class ProviderGA extends ProviderBase {

	init() {
		const gaCode = this.container().querySelector( 'input[type=text].shield_gacode' );
		if ( gaCode ) {
			let $gaCode = $( gaCode );
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
		}

		$( this.container() ).on( 'click', '.shield_ga_remove', () => {
			this.sendReq( this._base_data.ajax.profile_ga_toggle );
		} );
	}

	generateSVG() {
		let svgCodeCanvas = this.container().querySelector( '.shield-SvgQrCode' );
		if ( svgCodeCanvas ) {
			QRCode
			.toCanvas( svgCodeCanvas, this._base_data.vars.qr_code_auth, {
				width: 300,
			} )
			.catch( err => console.error( err ) );
		}
	}

	postRender() {
		this.generateSVG();
	}
}