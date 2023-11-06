import Toastify from 'toastify-js';
import { ObjectOps } from "../../util/ObjectOps";

export class ToastifyService {

	showMessage( msg, success, options = {} ) {
		Toastify( ObjectOps.Merge( {
			className: 'shield-toastify ' + ( success ? 'toastify-success' : 'toastify-failure' ),
			text: msg,
			duration: 5000,
			destination: null,
			newWindow: true,
			close: true,
			gravity: "top", // `top` or `bottom`
			position: "right", // `left`, `center` or `right`
			stopOnFocus: true, // Prevents dismissing of toast on hover
			style: {
				background: "linear-gradient(to right, #00b09b, #96c93d)",
			},
			onClick: function () {
			} // Callback after click
		}, options ) ).showToast();
	};
}