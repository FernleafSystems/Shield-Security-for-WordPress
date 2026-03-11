import { Offcanvas } from "bootstrap";
import { BaseComponent } from "../BaseComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { AjaxService } from "../services/AjaxService";
import { UiContentActivator } from "./UiContentActivator";

export class OffCanvasService extends BaseComponent {

	static bsCanvas;
	static canvasTracker;
	static offCanvasEl;
	static HISTORY_MODE_PUSH = 'push';
	static HISTORY_MODE_REPLACE = 'replace';

	init() {
		OffCanvasService.canvasTracker = [];
		OffCanvasService.offCanvasEl = document.getElementById( 'AptoOffcanvas' ) || false;
		this.exec();
	}

	canRun() {
		return OffCanvasService.offCanvasEl;
	}

	run() {
		OffCanvasService.bsCanvas = new Offcanvas( OffCanvasService.offCanvasEl );

		OffCanvasService.offCanvasEl.addEventListener( 'shown.bs.offcanvas', () => {
			UiContentActivator.activateWithin( OffCanvasService.offCanvasEl );
		} );

		OffCanvasService.offCanvasEl.addEventListener( 'hidden.bs.offcanvas', () => {
			OffCanvasService.canvasTracker.pop(); // remove the one we just closed.
			if ( OffCanvasService.canvasTracker.length > 0 ) {
				OffCanvasService.renderRequest(
					OffCanvasService.canvasTracker[ OffCanvasService.canvasTracker.length - 1 ]
				).finally();
			}
		} );
	}

	renderConfig( config_item ) {
		this.renderCanvas( this._base_data.mod_config, {
			config_item: config_item
		} ).finally();
	};

	static RenderCanvas( canvasProperties = {}, options = {} ) {
		const request = ObjectOps.ObjClone( canvasProperties );
		OffCanvasService.updateHistory(
			request,
			OffCanvasService.normalizeHistoryMode( options.historyMode )
		);
		return OffCanvasService.renderRequest( request );
	};

	static normalizeHistoryMode( historyMode = '' ) {
		return historyMode === OffCanvasService.HISTORY_MODE_REPLACE
			? OffCanvasService.HISTORY_MODE_REPLACE
			: OffCanvasService.HISTORY_MODE_PUSH;
	}

	static updateHistory( request, historyMode ) {
		if (
			historyMode === OffCanvasService.HISTORY_MODE_REPLACE
			&& OffCanvasService.canvasTracker.length > 0
		) {
			OffCanvasService.canvasTracker[ OffCanvasService.canvasTracker.length - 1 ] = request;
		}
		else {
			OffCanvasService.canvasTracker.push( request );
		}
	}

	static renderRequest( request ) {
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
		.send( request, false )
		.then( ( resp ) => {
			if ( resp.success ) {
				OffCanvasService.offCanvasEl.classList.add( request.render_slug );
				OffCanvasService.offCanvasEl.innerHTML = resp.data.html;
				if ( OffCanvasService.offCanvasEl.classList.contains( 'show' ) ) {
					UiContentActivator.activateWithin( OffCanvasService.offCanvasEl );
				}
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
	}

	static CloseCanvas() {
		OffCanvasService.bsCanvas.hide();
	};
}
