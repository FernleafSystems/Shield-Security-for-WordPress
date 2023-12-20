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
		JsLoadingOverlay.show( {
			"overlayBackgroundColor": "#ffffff",
			"overlayOpacity": "0.7",
			"spinnerIcon": "ball-spin-clockwise-fade",
			"spinnerColor": "#008000",
			"spinnerSize": "2x",
			"overlayIDName": "ShieldOverlay",
			"spinnerIDName": "ShieldOverlaySpinner",
			"offsetX": 0,
			"offsetY": "-25%",
			"containerID": ( containerID && containerID.length > 0 && document.getElementById( containerID ) ) ? containerID : null,
			"lockScroll": false,
			"overlayZIndex": 100001,
			"spinnerZIndex": 100002
		} );
		// document.querySelector( 'body' ).classList.add( 'shield-busy' );
	}
}