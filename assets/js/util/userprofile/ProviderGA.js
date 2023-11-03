import QRCode from 'qrcode'
import { ProviderBase } from "./ProviderBase";
import { ObjectOps } from "../ObjectOps";

export class ProviderGA extends ProviderBase {

	run() {
		shieldEventsHandler_UserProfile.add_Click( '.shield_ga_remove', ( targetEl ) => {
			if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
				this.sendReq( this._base_data.ajax.profile_ga_toggle );
			}
		} );
		shieldEventsHandler_UserProfile.add_Keyup( 'input[type=text].shield_gacode', ( targetEl ) => {
			targetEl.value = targetEl.value
									 .replace( /[^0-9]/gi, '' )
									 .substring( 0, 6 );
			if ( targetEl.value.length === 6 ) {
				targetEl.setAttribute( 'disabled', 'disabled' );
				this.sendReq(
					ObjectOps.Merge( this._base_data.ajax.profile_ga_toggle, { ga_otp: targetEl.value } )
				);
			}
		} );
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
	}
}