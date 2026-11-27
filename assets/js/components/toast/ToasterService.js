import $ from 'jquery';
import { Toast } from 'bootstrap';

export class ToasterService {

	constructor() {
		let toastDIV = document.getElementById( 'AptoOptionsToast' );

		this.$toast = $( toastDIV );
		this.$toast.on( 'hidden.bs.toast', function () {
			toastDIV.style[ 'z-index' ] = -10;
		} );

		this.toastie = Toast.getInstance( toastDIV );
		if ( !this.toastie ) {
			this.toastie = new Toast( toastDIV, {
				autohide: true,
				delay: 3000
			} );
		}
	}

	showMessage( msg, success ) {

		this.$toast.removeClass( 'text-bg-success text-bg-warning' );
		this.$toast.addClass( success ? 'text-bg-success' : 'text-bg-warning' );

		let $toastBody = $( '.toast-body', this.$toast );

		$toastBody.html( '' );

		// Messages are plain text, often carrying server error text, so they're never parsed as HTML.
		$( '<span></span>' )
		.text( msg )
		.appendTo( $toastBody );

		this.$toast.css( 'z-index', 100000000 );

		this.toastie.show();
	};
}