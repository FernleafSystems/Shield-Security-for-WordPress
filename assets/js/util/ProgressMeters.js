import $ from "jquery";
import CircleProgress from "js-circle-progress";
import { BaseService } from "./BaseService";
import { ObjectOps } from "./ObjectOps";
import { OffCanvasService } from "./OffCanvasService";

export class ProgressMeters extends BaseService {

	init() {
		this.exec();
	}

	run() {
		$( document ).on( 'click', 'a.offcanvas_meter_analysis', ( evt ) => {
			evt.preventDefault();
			OffCanvasService.RenderCanvas( ObjectOps.Merge( this._base_data.ajax.render_offcanvas, evt.currentTarget.dataset ) )
							.finally();
			return false;
		} );

		document.querySelectorAll( '.progress-meter .description' )
				.forEach(
					( elem ) => elem.addEventListener( 'click',
						() => elem.querySelectorAll( '.toggleable' )
								  .forEach(
									  ( toggleAbleElem ) => toggleAbleElem.classList.toggle( 'hidden' )
								  )
					)
				);

		document.querySelectorAll( '.circle-progress' )
				.forEach( ( elem, idx, ) => {
					new CircleProgress( elem, ObjectOps.Merge( {
						max: 100,
						textFormat: ( value, max ) => elem.dataset.grade,
					}, { value: elem.dataset.value } ) );
				} );
	}
}