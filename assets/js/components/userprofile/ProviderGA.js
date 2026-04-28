import QRCode from 'qrcode'
import { ProviderBase } from "./ProviderBase";
import { ObjectOps } from "../../util/ObjectOps";
import { mfaConfirm } from "./MfaProfileDialog";

export class ProviderGA extends ProviderBase {

	run() {
		shieldEventsHandler_UserProfile.add_Click( '.shield_ga_remove', async ( targetEl ) => {
			if ( await mfaConfirm( {
				title: shieldStrings.string( 'dialog_confirm_title' ),
				message: shieldStrings.string( 'are_you_sure' ),
				confirmLabel: shieldStrings.string( 'confirm' ),
				cancelLabel: shieldStrings.string( 'cancel' ),
				danger: true,
				launcher: targetEl,
			} ) ) {
				this.sendReq( this._base_data.ajax.profile_ga_toggle, targetEl );
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
