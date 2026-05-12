import BigPicture from "bigpicture";
import { BaseComponent } from "../BaseComponent";

export class MiscHooks extends BaseComponent {
	init() {
		shieldEventsHandler_Main.add_Click( '.option-video', ( targetEl ) => {
			if ( !( targetEl instanceof HTMLElement ) ) {
				return;
			}
			BigPicture( {
				el: targetEl,
				vimeoSrc: targetEl.dataset[ 'vimeoid' ],
			} );
		} );
	}
}
