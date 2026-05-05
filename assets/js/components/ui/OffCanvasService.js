import { Offcanvas } from "bootstrap";
import { BaseComponent } from "../BaseComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { AjaxService } from "../services/AjaxService";
import { UiContentActivator } from "./UiContentActivator";
import { BootstrapTooltips } from "./BootstrapTooltips";
import { focusElement } from "./ShieldA11y";
import { messageDialog } from "./ShieldDialog";

export class OffCanvasService extends BaseComponent {

	static bsCanvas;
	static canvasTracker;
	static offCanvasEl;
	static rootOpenerEl;
	static HISTORY_MODE_PUSH = 'push';
	static HISTORY_MODE_REPLACE = 'replace';

	init() {
		OffCanvasService.canvasTracker = [];
		OffCanvasService.offCanvasEl = document.getElementById( 'AptoOffcanvas' ) || false;
		OffCanvasService.rootOpenerEl = null;
		this.exec();
	}

	canRun() {
		return OffCanvasService.offCanvasEl;
	}

	run() {
		OffCanvasService.bsCanvas = new Offcanvas( OffCanvasService.offCanvasEl );

		OffCanvasService.offCanvasEl.addEventListener( 'shown.bs.offcanvas', () => {
			UiContentActivator.activateCurrentSubtree( OffCanvasService.offCanvasEl );
		} );

		OffCanvasService.offCanvasEl.addEventListener( 'hidden.bs.offcanvas', () => {
			OffCanvasService.canvasTracker.pop(); // remove the one we just closed.
			if ( OffCanvasService.canvasTracker.length > 0 ) {
				OffCanvasService.renderRequest(
					OffCanvasService.canvasTracker[ OffCanvasService.canvasTracker.length - 1 ]
				).finally();
				return;
			}

			const openerEl = OffCanvasService.rootOpenerEl;
			OffCanvasService.rootOpenerEl = null;
			focusElement( openerEl );
		} );
	}

	renderConfig( config_item ) {
		this.renderCanvas( this._base_data.mod_config, {
			config_item: config_item
		} ).finally();
	};

	static RenderCanvas( canvasProperties = {}, options = {} ) {
		const request = ObjectOps.ObjClone( canvasProperties );
		if ( OffCanvasService.canvasTracker.length === 0 ) {
			OffCanvasService.captureRootOpener( options.launcher );
		}
		OffCanvasService.updateHistory(
			request,
			OffCanvasService.normalizeHistoryMode( options.historyMode )
		);
		return OffCanvasService.renderRequest( request );
	};

	static captureRootOpener( launcher = null ) {
		if ( OffCanvasService.isValidRootOpener( launcher ) ) {
			OffCanvasService.rootOpenerEl = launcher;
			return;
		}

		const activeEl = OffCanvasService.offCanvasEl instanceof HTMLElement
			? OffCanvasService.offCanvasEl.ownerDocument.activeElement
			: document.activeElement;
		OffCanvasService.rootOpenerEl = OffCanvasService.isValidRootOpener( activeEl ) ? activeEl : null;
	}

	static isValidRootOpener( element ) {
		return element instanceof HTMLElement
			&& OffCanvasService.offCanvasEl instanceof HTMLElement
			&& element.isConnected
			&& element !== element.ownerDocument.body
			&& element !== element.ownerDocument.documentElement
			&& !OffCanvasService.offCanvasEl.contains( element );
	}

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
		BootstrapTooltips.DisposeTooltipsWithin( OffCanvasService.offCanvasEl );
		OffCanvasService.offCanvasEl.replaceChildren( OffCanvasService.buildLoadingContent() );
		OffCanvasService.offCanvasEl.classList.forEach( ( cls ) => {
			if ( cls.startsWith( 'offcanvas_' ) ) {
				OffCanvasService.offCanvasEl.classList.remove( cls );
			}
		} );
		OffCanvasService.bsCanvas.show();

		return ( new AjaxService() )
		.send( request, false )
		.then( ( resp ) => {
			if ( resp?.success && typeof resp?.data?.html === 'string' ) {
				OffCanvasService.offCanvasEl.classList.add( request.render_slug );
				BootstrapTooltips.DisposeTooltipsWithin( OffCanvasService.offCanvasEl );
				OffCanvasService.offCanvasEl.innerHTML = resp.data.html;
				if ( !OffCanvasService.validateOffcanvasAccessibility() ) {
					return OffCanvasService.handleRenderFailure(
						'The requested panel could not be displayed.'
					);
				}
				if ( OffCanvasService.offCanvasEl.classList.contains( 'show' ) ) {
					UiContentActivator.activateCurrentSubtree( OffCanvasService.offCanvasEl );
				}
				return true;
			}
			console.log( resp );
			return OffCanvasService.handleRenderFailure( OffCanvasService.extractErrorMessage( resp ) );
		} )
		.catch( ( error ) => {
			console.log( error );
			return OffCanvasService.handleRenderFailure(
				'There was a problem displaying the page.'
			);
		} );
	}

	static validateOffcanvasAccessibility() {
		const offcanvas = OffCanvasService.offCanvasEl;
		if ( !( offcanvas instanceof HTMLElement ) || !offcanvas.hasAttribute( 'tabindex' ) ) {
			return false;
		}

		const labelId = String( offcanvas.getAttribute( 'aria-labelledby' ) || '' ).trim();
		const labelEl = labelId.length > 0 ? offcanvas.ownerDocument.getElementById( labelId ) : null;
		if ( !( labelEl instanceof HTMLElement ) || String( labelEl.textContent || '' ).trim().length < 1 ) {
			return false;
		}

		return Array.from( offcanvas.querySelectorAll( '[data-bs-dismiss="offcanvas"]' ) )
		.every( ( control ) => OffCanvasService.hasAccessibleControlLabel( control ) );
	}

	static hasAccessibleControlLabel( control ) {
		if ( !( control instanceof HTMLElement ) ) {
			return false;
		}

		return [
			control.getAttribute( 'aria-label' ),
			control.getAttribute( 'title' ),
			control.textContent,
		].some( ( label ) => String( label || '' ).trim().length > 0 );
	}

	static handleRenderFailure( message ) {
		const openMessage = () => messageDialog( {
			title: OffCanvasService.localizedString( 'request_failed', 'Request Failed' ),
			message: message,
			launcher: OffCanvasService.resolveDialogLauncher(),
		} ).then( () => false );

		OffCanvasService.canvasTracker = [];
		if ( !( OffCanvasService.offCanvasEl instanceof HTMLElement )
			|| (
				!OffCanvasService.offCanvasEl.classList.contains( 'show' )
				&& !OffCanvasService.offCanvasEl.classList.contains( 'showing' )
			) ) {
			return openMessage();
		}

		return new Promise( ( resolve ) => {
			OffCanvasService.offCanvasEl.addEventListener( 'hidden.bs.offcanvas', () => {
				resolve( openMessage() );
			}, { once: true } );
			OffCanvasService.CloseCanvas();
		} );
	}

	static resolveDialogLauncher() {
		return document.activeElement instanceof HTMLElement ? document.activeElement : null;
	}

	static extractErrorMessage( resp ) {
		const message = resp?.data?.error || resp?.data?.message;
		return typeof message === 'string' && message.length > 0
			? message
			: 'There was a problem displaying the page.';
	}

	static localizedString( key, fallback ) {
		return typeof shieldStrings !== 'undefined' && typeof shieldStrings.string === 'function'
			? shieldStrings.string( key ) || fallback
			: fallback;
	}

	static buildLoadingContent() {
		const fragment = document.createDocumentFragment();
		const header = document.createElement( 'div' );
		header.className = 'offcanvas-header';
		const title = document.createElement( 'h5' );
		title.className = 'offcanvas-title';
		title.id = 'AptoOffcanvasLabel';
		title.textContent = typeof shieldStrings !== 'undefined' && typeof shieldStrings.string === 'function'
			? shieldStrings.string( 'loading' ) || 'Loading'
			: 'Loading';
		header.appendChild( title );
		const closeButton = document.createElement( 'button' );
		closeButton.type = 'button';
		closeButton.className = 'btn-close';
		closeButton.setAttribute( 'data-bs-dismiss', 'offcanvas' );
		closeButton.setAttribute( 'aria-label', typeof shieldStrings !== 'undefined' && typeof shieldStrings.string === 'function'
			? shieldStrings.string( 'close' ) || 'Close'
			: 'Close' );
		header.appendChild( closeButton );

		const body = document.createElement( 'div' );
		body.className = 'offcanvas-body';
		const spinner = document.getElementById( 'ShieldWaitSpinner' )?.cloneNode( true ) || document.createElement( 'div' );
		if ( spinner instanceof HTMLElement ) {
			spinner.id = '';
			spinner.classList.remove( 'd-none' );
			body.appendChild( spinner );
		}

		fragment.appendChild( header );
		fragment.appendChild( body );
		return fragment;
	}

	static CloseCanvas() {
		OffCanvasService.bsCanvas.hide();
	};
}
