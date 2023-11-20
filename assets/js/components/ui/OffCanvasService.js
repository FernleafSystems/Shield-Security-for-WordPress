import { Offcanvas } from "bootstrap";
import { BaseComponent } from "../BaseComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { AjaxService } from "../services/AjaxService";

export class OffCanvasService extends BaseComponent {

	static bsCanvas;
	static canvasTracker;
	static offCanvasEl;

	init() {
		OffCanvasService.canvasTracker = [];
		OffCanvasService.offCanvasEl = document.getElementById( 'ShieldOffcanvas' ) || false;
		this.exec();
	}

	canRun() {
		return OffCanvasService.offCanvasEl;
	}

	run() {
		OffCanvasService.bsCanvas = new Offcanvas( OffCanvasService.offCanvasEl );

		OffCanvasService.offCanvasEl.addEventListener( 'hidden.bs.offcanvas', () => {
			OffCanvasService.canvasTracker.pop(); // remove the one we just closed.
			if ( OffCanvasService.canvasTracker.length > 0 ) {
				let latest = OffCanvasService.canvasTracker.pop();
				OffCanvasService.RenderCanvas( latest ).finally(); // re-open the latest.
			}
		} );
	}

	renderConfig( config_item ) {
		this.renderCanvas( this._base_data.mod_config, {
			config_item: config_item
		} ).finally();
	};

	static RenderCanvas( canvasProperties = {} ) {

		canvasProperties = ObjectOps.ObjClone( canvasProperties );
		OffCanvasService.canvasTracker.push( canvasProperties );

		const spinner = document.getElementById( 'ShieldWaitSpinner' ).cloneNode( true );
		spinner.id = '';
		spinner.classList.remove( 'd-none' );

		OffCanvasService.offCanvasEl.textContent = '';
		OffCanvasService.offCanvasEl.appendChild( spinner );
		OffCanvasService.offCanvasEl.classList.forEach( ( cls ) => {
			if ( cls.startsWith( 'offcanvas_' ) ) {
				OffCanvasService.offCanvasEl.classList.remove( cls );
			}
		} );
		OffCanvasService.bsCanvas.show();

		return ( new AjaxService() )
		.send( canvasProperties, false )
		.then( ( resp ) => {
			if ( resp.success ) {
				OffCanvasService.offCanvasEl.classList.add( canvasProperties.render_slug );
				OffCanvasService.offCanvasEl.innerHTML = resp.data.html;
			}
			else if ( typeof resp.data.error !== 'undefined' ) {
				alert( resp.data.error );
			}
			else {
				alert( 'There was a problem displaying the page.' );
				console.log( resp );
			}
		} )
		.catch( ( error ) => {
			console.log( error );
		} );
	};

	static CloseCanvas() {
		OffCanvasService.bsCanvas.hide();
	};
}