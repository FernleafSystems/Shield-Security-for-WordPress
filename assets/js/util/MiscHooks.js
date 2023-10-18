import $ from 'jquery';
import BigPicture from "bigpicture";
import { BaseService } from "./BaseService";

export class MiscHooks extends BaseService {
	init() {
		/** TODO: test this fully */
		$( document ).on( 'submit', 'form.icwp-form-dynamic-action',
			( evt ) => evt.currentTarget.action = window.location.href
		);

		$( document ).on( 'click', '.option-video', ( evt ) => {
			evt.preventDefault();
			BigPicture( {
				el: evt.target,
				vimeoSrc: $( evt.currentTarget ).data( 'vimeoid' ),
			} );
			return false;
		} );
	}
}