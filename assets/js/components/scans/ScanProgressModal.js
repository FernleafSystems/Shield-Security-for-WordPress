import { BootstrapModals } from "../ui/BootstrapModals";
import { announceWithin } from "../ui/ShieldA11y";
import { ScanRunPage } from "./ScanRunPage";

export class ScanProgressModal {

	static modalStates = [ 'initiating', 'running', 'completed', 'failed' ];

	static ShowHtml( html ) {
		if ( ScanRunPage.IsActive() ) {
			return ScanRunPage.Show( html );
		}

		const modal = ScanProgressModal.getModal();
		const modalContent = ScanProgressModal.getModalContent( modal );
		if ( !( modal instanceof HTMLElement ) || !( modalContent instanceof HTMLElement ) ) {
			return false;
		}

		modalContent.innerHTML = html;
		if ( modal.classList.contains( 'show' ) ) {
			ScanProgressModal.announceCurrentState( modal );
		}
		else {
			modal.addEventListener( 'shown.bs.modal', () => ScanProgressModal.announceCurrentState( modal ), { once: true } );
		}
		BootstrapModals.Show( modal );
		return true;
	}

	static ShowInitiating( strings = {} ) {
		return ScanProgressModal.showLocal( {
			state: 'initiating',
			title: strings.modal_title,
			heading: strings.modal_initiating,
			message: strings.modal_wait,
			announcement: strings.modal_initiating,
			busy: true
		} );
	}

	static ShowError( strings = {}, message = '' ) {
		const safeMessage = typeof message === 'string' && message.length > 0
			? message
			: strings.modal_error_message;

		return ScanProgressModal.showLocal( {
			state: 'failed',
			title: strings.modal_title,
			heading: strings.modal_error_title,
			message: safeMessage,
			announcement: safeMessage,
			busy: false
		} );
	}

	/**
	 * The scans may be fine: it's only their progress that can't be reached at the moment.
	 */
	static ShowStale( strings = {} ) {
		return ScanProgressModal.showLocal( {
			state: 'stale',
			title: strings.modal_title,
			heading: strings.modal_stale_title,
			message: strings.modal_stale_message,
			announcement: `${strings.modal_stale_title} ${strings.modal_stale_message}`,
			busy: false,
			refreshLabel: strings.refresh
		} );
	}

	static showLocal( content ) {
		return ScanRunPage.IsActive()
			? ScanRunPage.ShowLocal( content )
			: ScanProgressModal.ShowHtml( ScanProgressModal.buildLocalModalContent( content ) );
	}

	static HasModalResponse( resp ) {
		return !!( resp?.data
			&& typeof resp.data.modal_html === 'string'
			&& resp.data.modal_html.length > 0
			&& ScanProgressModal.modalStates.includes( resp.data.modal_state ) );
	}

	static ExtractErrorMessage( resp ) {
		const message = resp?.data?.message;
		return typeof message === 'string' && message.length > 0 ? message : '';
	}

	static getModal() {
		return document.getElementById( 'ShieldModalContainer' );
	}

	static getModalContent( modal ) {
		return modal?.querySelector( '.modal-content' ) || null;
	}

	static buildLocalModalContent( { state, title, heading, message, announcement, busy } ) {
		return `<div class="modal-header">
			<h2 class="modal-title h5" id="ShieldModalContainerLabel">${ScanProgressModal.escapeHtml( title )}</h2>
			<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${ScanProgressModal.escapeHtml( ScanProgressModal.getCloseLabel() )}"></button>
		</div>
		<div class="modal-body">
			<div data-shield-scan-modal-state="${ScanProgressModal.escapeHtml( state )}" aria-busy="${busy ? 'true' : 'false'}" data-shield-scan-modal-announcement="${ScanProgressModal.escapeHtml( announcement )}">
				<h3 class="h6">${ScanProgressModal.escapeHtml( heading )}</h3>
				<p>${ScanProgressModal.escapeHtml( message )}</p>
				${busy ? ScanProgressModal.buildSpinnerMarkup() : ''}
			</div>
		</div>
		<div class="modal-footer"></div>`;
	}

	static buildSpinnerMarkup() {
		const spinner = document.getElementById( 'ShieldWaitSpinner' );
		if ( spinner instanceof HTMLElement ) {
			const clone = spinner.cloneNode( true );
			if ( clone instanceof HTMLElement ) {
				clone.id = '';
				clone.classList.remove( 'd-none' );
				return clone.outerHTML;
			}
		}

		return '<div class="d-flex justify-content-center align-items-center"><div class="spinner-border text-success m-5" role="status"><span class="visually-hidden">Loading...</span></div></div>';
	}

	static getCloseLabel() {
		return typeof shieldStrings !== 'undefined' && typeof shieldStrings.string === 'function'
			? shieldStrings.string( 'close' ) || 'Close'
			: 'Close';
	}

	static announceCurrentState( modal ) {
		const stateEl = modal.querySelector( '[data-shield-scan-modal-announcement]' );
		if ( stateEl instanceof HTMLElement ) {
			announceWithin( modal, stateEl.dataset.shieldScanModalAnnouncement );
		}
	}

	static escapeHtml( text = '' ) {
		return String( text )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' );
	}
}
