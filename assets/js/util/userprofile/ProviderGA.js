import QRCode from 'qrcode'
import { ProviderBase } from "./ProviderBase";
import { ObjectOps } from "../ObjectOps";

export class ProviderGA extends ProviderBase {

	attachRemove() {
		const remove = this.container().querySelector( '.shield_ga_remove' );
		if ( remove ) {
			remove.addEventListener( 'click', () => {
				if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
					this.sendReq( this._base_data.ajax.profile_ga_toggle );
				}
			}, false );
		}
	}

	generateSVG() {
		let svgCodeCanvas = this.container().querySelector( '.shield-SvgQrCode' );
		if ( svgCodeCanvas ) {
			QRCode
			.toCanvas( svgCodeCanvas, svgCodeCanvas.dataset[ 'qr_url' ], {
				width: 300,
			} )
			.catch( err => console.error( err ) );
		}
	}

	postRender() {
		this.generateSVG();
		this.attachRemove();

		const gaCode = this.container().querySelector( 'input[type=text].shield_gacode' );
		if ( gaCode ) {
			gaCode.addEventListener( 'keyup', () => {
				gaCode.value = gaCode.value
									 .replace( /[^0-9]/gi, '' )
									 .substring( 0, 6 );

				if ( gaCode.value.length === 6 ) {
					gaCode.setAttribute( 'disabled', 'disabled' );
					this.sendReq(
						ObjectOps.Merge( this._base_data.ajax.profile_ga_toggle, { ga_otp: gaCode.value } )
					);
				}
			}, false );
		}
	}
}