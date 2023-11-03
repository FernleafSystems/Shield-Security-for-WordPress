import CircleProgress from "js-circle-progress";
import { BaseService } from "./BaseService";
import { ObjectOps } from "./ObjectOps";
import { OffCanvasService } from "./OffCanvasService";

export class ProgressMeters extends BaseService {

	init() {
		this.exec();
	}

	run() {
		shieldEventsHandler_Main.add_Click( 'a.offcanvas_meter_analysis', ( targetEl ) => {
			OffCanvasService.RenderCanvas( ObjectOps.Merge( this._base_data.ajax.render_offcanvas, targetEl.dataset ) )
							.finally();
		} );
		shieldEventsHandler_Main.add_Click( 'div.progress-meter .description', ( targetEl ) => {
			targetEl.querySelectorAll( '.toggleable' ).forEach(
				( toggleableElem ) => toggleableElem.classList.toggle( 'hidden' )
			);
		} );
		document.querySelectorAll( '.circle-progress' )
				.forEach( ( elem, idx, ) => {
					new CircleProgress( elem, ObjectOps.Merge( {
						max: 100,
						textFormat: ( value, max ) => elem.dataset.grade,
					}, { value: elem.dataset.value } ) );
				} );
	}
}