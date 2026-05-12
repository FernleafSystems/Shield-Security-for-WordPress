import introJs from "intro.js";
import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { BootstrapModals } from "../ui/BootstrapModals";

/**
 * @typedef {Object} ShieldTourStepData
 * @property {string} selector
 * @property {string} title
 * @property {string} intro
 * @property {string} position
 * @property {boolean} required
 */

/**
 * @typedef {Object} ShieldTourVideoModalData
 * @property {boolean} is_enabled
 * @property {string} embed_url
 * @property {string} modal_title
 * @property {string} video_title
 * @property {string} body_copy
 * @property {string} continue_label
 * @property {string} skip_label
 */

/**
 * @typedef {Object} ShieldTourData
 * @property {string} key
 * @property {boolean} is_available
 * @property {ShieldTourStepData[]} steps
 * @property {Record<string, any>} options
 * @property {ShieldTourVideoModalData} video_modal
 */

/**
 * @typedef {Object} ShieldPluginOnboardingBaseData
 * @property {{ finished: Record<string, any> }} ajax
 * @property {{ tour: ShieldTourData }} vars
 */

/**
 * @extends {BaseComponent<ShieldPluginOnboardingBaseData>}
 */
export class PluginOnboarding extends BaseComponent {

	init() {
		const tour = this._base_data.vars.tour;
		if ( tour.is_available !== true || this.hasSecurityAdminOverlay() ) {
			return;
		}

		const steps = this.buildStepsForTour( tour.steps );
		if ( steps === null || steps.length < 1 ) {
			return;
		}

		const startTour = () => this.setupTour( tour.key, steps, tour.options );
		if ( this.shouldShowVideoModal( tour.video_modal ) ) {
			if ( !this.showVideoModal( tour.video_modal, startTour ) ) {
				startTour();
			}
			return;
		}

		startTour();
	}

	/**
	 * @param {string} tourKey
	 * @param {Array<Record<string, any>>} steps
	 * @param {Record<string, any>} options
	 */
	setupTour( tourKey, steps, options ) {
		let markedComplete = false;
		const markComplete = () => {
			if ( markedComplete ) {
				return;
			}
			markedComplete = true;
			this.markTourFinished( tourKey );
		};

		introJs.tour()
		.setOptions( {
			...options,
			steps: steps
		} )
		.onExit( markComplete )
		.onSkip( markComplete )
		.onComplete( markComplete )
		.start()
		.catch( ( error ) => console.log( error ) );
	}

	/**
	 * @param {ShieldTourVideoModalData} videoModal
	 * @returns {boolean}
	 */
	shouldShowVideoModal( videoModal ) {
		return videoModal?.is_enabled === true && typeof videoModal?.embed_url === 'string' && videoModal.embed_url.length > 0;
	}

	/**
	 * @param {ShieldTourVideoModalData} videoModal
	 * @param {() => void} onClosed
	 * @returns {boolean}
	 */
	showVideoModal( videoModal, onClosed ) {
		const modal = document.getElementById( 'ShieldModalContainer' );
		const modalContent = modal?.querySelector( '.modal-content' );
		if ( !( modal instanceof HTMLElement ) || !( modalContent instanceof HTMLElement ) ) {
			return false;
		}

		const modalEls = this.buildVideoModalContent( videoModal );
		modalContent.replaceChildren( modalEls.header, modalEls.body, modalEls.footer );

		let player = this.createVimeoPlayer( modalEls.iframe );
		let startedTour = false;
		const startTour = () => {
			if ( startedTour ) {
				return;
			}
			startedTour = true;
			this.cleanupVimeoPlayer( player, modalEls.iframe );
			player = null;
			onClosed();
		};

		modal.addEventListener( 'hidden.bs.modal', startTour, { once: true } );
		modalEls.continueButton.addEventListener( 'click', () => BootstrapModals.Hide( modal ) );
		modalEls.skipButton.addEventListener( 'click', () => BootstrapModals.Hide( modal ) );
		BootstrapModals.Show( modal );

		return true;
	}

	/**
	 * @param {ShieldTourVideoModalData} videoModal
	 * @returns {{
	 *   header: HTMLDivElement,
	 *   body: HTMLDivElement,
	 *   footer: HTMLDivElement,
	 *   iframe: HTMLIFrameElement,
	 *   continueButton: HTMLButtonElement,
	 *   skipButton: HTMLButtonElement
	 * }}
	 */
	buildVideoModalContent( videoModal ) {
		const header = document.createElement( 'div' );
		header.className = 'modal-header';

		const title = document.createElement( 'h5' );
		title.className = 'modal-title';
		title.id = 'ShieldModalContainerLabel';
		title.textContent = videoModal.modal_title;
		header.appendChild( title );

		const closeButton = document.createElement( 'button' );
		closeButton.type = 'button';
		closeButton.className = 'btn-close';
		closeButton.setAttribute( 'data-bs-dismiss', 'modal' );
		closeButton.setAttribute( 'aria-label', shieldStrings.string( 'close' ) || 'Close' );
		header.appendChild( closeButton );

		const body = document.createElement( 'div' );
		body.className = 'modal-body shield-video-modal';

		const bodyCopy = document.createElement( 'p' );
		bodyCopy.className = 'shield-video-modal__copy';
		bodyCopy.textContent = videoModal.body_copy;
		body.appendChild( bodyCopy );

		const videoWrapper = document.createElement( 'div' );
		videoWrapper.className = 'shield-video-modal__frame';

		const iframe = document.createElement( 'iframe' );
		iframe.src = videoModal.embed_url;
		iframe.title = videoModal.video_title;
		iframe.allow = 'autoplay; fullscreen; picture-in-picture; clipboard-write';
		iframe.allowFullscreen = true;
		videoWrapper.appendChild( iframe );
		body.appendChild( videoWrapper );

		const footer = document.createElement( 'div' );
		footer.className = 'modal-footer';

		const skipButton = document.createElement( 'button' );
		skipButton.type = 'button';
		skipButton.className = 'btn btn-outline-secondary';
		skipButton.textContent = videoModal.skip_label;
		footer.appendChild( skipButton );

		const continueButton = document.createElement( 'button' );
		continueButton.type = 'button';
		continueButton.className = 'btn btn-primary';
		continueButton.textContent = videoModal.continue_label;
		footer.appendChild( continueButton );

		return {
			header: header,
			body: body,
			footer: footer,
			iframe: iframe,
			continueButton: continueButton,
			skipButton: skipButton
		};
	}

	/**
	 * @param {HTMLIFrameElement} iframe
	 * @returns {any}
	 */
	createVimeoPlayer( iframe ) {
		if ( typeof window.Vimeo?.Player !== 'function' ) {
			return null;
		}

		try {
			return new window.Vimeo.Player( iframe );
		}
		catch ( error ) {
			console.log( error );
			return null;
		}
	}

	/**
	 * @param {any} player
	 * @param {HTMLIFrameElement} iframe
	 */
	cleanupVimeoPlayer( player, iframe ) {
		if ( player && typeof player.destroy === 'function' ) {
			const cleanup = player.destroy();
			if ( cleanup && typeof cleanup.catch === 'function' ) {
				cleanup.catch( ( error ) => console.log( error ) );
			}
			return;
		}

		if ( player && typeof player.unload === 'function' ) {
			const cleanup = player.unload();
			if ( cleanup && typeof cleanup.catch === 'function' ) {
				cleanup.catch( ( error ) => console.log( error ) );
			}
		}

		iframe.remove();
	}

	/**
	 * @param {ShieldTourStepData[]} tourItems
	 * @returns {Array<Record<string, any>>|null}
	 */
	buildStepsForTour( tourItems ) {
		const steps = [];
		for ( let i = 0; i < tourItems.length; i++ ) {
			const tourItem = tourItems[ i ];
			const targetElement = document.querySelector( tourItem.selector );
			if ( targetElement === null ) {
				if ( tourItem.required === true ) {
					return null;
				}
				continue;
			}

			const step = {
				element: targetElement,
				intro: tourItem.intro,
				title: tourItem.title,
				position: tourItem.position
			};
			steps.push( step );
		}
		return steps;
	}

	hasSecurityAdminOverlay() {
		return document.getElementById( 'SecurityAdminOverlay' ) !== null;
	}

	/**
	 * @param {string} tourKey
	 */
	markTourFinished( tourKey ) {
		const req = ObjectOps.ObjClone( this._base_data.ajax.finished );
		req[ 'tour_key' ] = tourKey;
		( new AjaxService() ).bg( req ).finally();
	}
}
