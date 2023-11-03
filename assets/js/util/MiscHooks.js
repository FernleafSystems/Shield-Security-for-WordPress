import BigPicture from "bigpicture";
import { BaseService } from "./BaseService";

export class MiscHooks extends BaseService {
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