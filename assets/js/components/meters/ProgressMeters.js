import CircleProgress from "js-circle-progress";
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { OffCanvasService } from "../ui/OffCanvasService";

export class ProgressMeters extends BaseAutoExecComponent {

	run() {
		this.drawCharts();
		this.events();
	}

	drawCharts() {
		document.querySelectorAll( '.circle-progress' )
				.forEach( ( elem, idx, ) => {
					new CircleProgress( elem, ObjectOps.Merge( {
						max: 100,
						textFormat: ( value, max ) => elem.dataset.grade,
					}, { value: elem.dataset.value } ) );
				} );
	}

	events() {
		shieldEventsHandler_Main.add_Click( 'a.offcanvas_meter_analysis', ( targetEl ) => {
			OffCanvasService.RenderCanvas( ObjectOps.Merge( this._base_data.ajax.render_offcanvas, targetEl.dataset ) )
							.finally();
		} );
		shieldEventsHandler_Main.add_Click( 'div.progress-meter .description', ( targetEl ) => {
			targetEl.querySelectorAll( '.toggleable' ).forEach(
				( toggleableElem ) => toggleableElem.classList.toggle( 'hidden' )
			);
		} );
	}
}