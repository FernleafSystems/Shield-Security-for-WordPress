import BigPicture from "bigpicture";
import { BaseComponent } from "../BaseComponent";

export class MiscHooks extends BaseComponent {
	init() {
		// shieldEventsHandler_Main.add_Submit( 'form.icwp-form-dynamic-action', ( form ) => {
		// 	form.action = window.location.href
		// } );

		shieldEventsHandler_Main.add_Click( '.option-video', ( targetEl ) => {
			BigPicture( {
				el: targetEl,
				vimeoSrc: targetEl.dataset[ 'vimeoid' ],
			} );
		} );
	}
}