import CircleProgress from "js-circle-progress";
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { OffCanvasService } from "../ui/OffCanvasService";
import { AjaxService } from "../services/AjaxService";

export class ProgressMeters extends BaseAutoExecComponent {

	run() {
		this.activeAnalysis = '';
		this.renderMetersAll();
		this.events();
	}

	renderMeters( meterElements ) {
		meterElements.forEach( ( elem ) => {
			if ( elem.dataset.meter_slug ) {
				this.renderMeter( elem, elem.dataset.meter_slug );
			}
		} );
	}

	renderMetersForSlugs( slugs ) {
		/** TODO
		slugs.forEach( ( slug, idx, ) => {
			const elem = document.querySelector( '.progress-metercard.progress-metercard-' + slug );
		} );*/
	}

	renderMetersAll() {
		this.renderMeters( document.querySelectorAll( '.progress-metercard' ) );
	}

	renderMeter( container, slug ) {
		return ( new AjaxService() )
		.bg( ObjectOps.Merge( this._base_data.ajax.render_metercard, {
			meter_slug: slug
		} ) )
		.then( ( resp ) => {
			container.innerHTML = resp.data.html;
		} )
		.then( () => {
			const elem = container.querySelector( '.circle-progress' );
			if ( elem ) {
				const cp2 = new CircleProgress( {
					min: 1,
					max: 100,
					textFormat: () => {
						return elem.dataset.grade;
					},
					value: elem.dataset.value,
					startAngle: 0,
				} );
				elem.appendChild( cp2 );
			}
		} )
		.catch( ( error ) => {
			console.log( error );
		} )
		.finally();
	}

	events() {
		shieldEventsHandler_Main.add_Click( 'a.offcanvas_meter_analysis', ( targetEl ) => {
			this.activeAnalysis = targetEl.dataset.meter;
			OffCanvasService.RenderCanvas( ObjectOps.Merge( this._base_data.ajax.render_offcanvas, targetEl.dataset ) )
							.finally();
		} );
		shieldEventsHandler_Main.add_Click( 'div.progress-metercard .description > :not(.alert)', ( targetEl ) => {
			targetEl.querySelectorAll( '.toggleable-text' ).forEach(
				( toggleableElem ) => toggleableElem.classList.toggle( 'hidden' )
			);
		} );
		shieldEventsHandler_Main.addHandler(
			'hidden.bs.offcanvas',
			'.offcanvas.offcanvas_meter_analysis',
			() => this.renderMetersAll()
		);
	}
}