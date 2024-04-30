import 'js-loading-overlay';

export class ShieldOverlay {

	hide() {
		ShieldOverlay.Hide();
	}

	show() {
		ShieldOverlay.Show();
	}

	static Hide() {
		JsLoadingOverlay.hide();
		// document.querySelector( 'body' ).classList.remove( 'shield-busy' );
	}

	/**
	 * https://js-loading-overlay.muhdfaiz.com/
	 */
	static Show( containerID = null ) {
		let theID = null;
		if ( containerID && containerID.length > 0 && document.getElementById( containerID ) ) {
			theID = containerID;
		}
		else {
			const ShieldContainer = document.getElementById( 'PageContainer-Apto' ) || false;
			if ( ShieldContainer ) {
				theID = ShieldContainer.id;
			}
		}

		JsLoadingOverlay.show( {
			"overlayBackgroundColor": "#ffffff",
			"overlayOpacity": "0.7",
			"spinnerIcon": "ball-spin-clockwise-fade",
			"spinnerColor": "#008000",
			"spinnerSize": "2x",
			"overlayIDName": "ShieldOverlay",
			"spinnerIDName": "ShieldOverlaySpinner",
			"offsetX": 0,
			"offsetY": "-35%",
			"containerID": theID,
			"lockScroll": false,
			"overlayZIndex": 100001,
			"spinnerZIndex": 100002
		} );
		// document.querySelector( 'body' ).classList.add( 'shield-busy' );
	}
}