import { BootstrapModals } from "../ui/BootstrapModals";

export class ScanProgressModal {

	static modalStates = [ 'initiating', 'running', 'completed', 'failed' ];

	static ShowHtml( html ) {
		const modal = ScanProgressModal.getModal();
		const modalContent = ScanProgressModal.getModalContent( modal );
		if ( !( modal instanceof HTMLElement ) || !( modalContent instanceof HTMLElement ) ) {
			return false;
		}

		modalContent.innerHTML = html;
		BootstrapModals.normalizeModalAccessibility( modal );
		BootstrapModals.Show( modal );
		return true;
	}

	static ShowInitiating( strings = {} ) {
		return ScanProgressModal.ShowHtml( ScanProgressModal.buildLocalModalContent( {
			state: 'initiating',
			title: strings.modal_title,
			heading: strings.modal_initiating,
			message: strings.modal_wait,
			busy: true
		} ) );
	}

	static ShowError( strings = {}, message = '' ) {
		const safeMessage = typeof message === 'string' && message.length > 0
			? message
			: strings.modal_error_message;

		return ScanProgressModal.ShowHtml( ScanProgressModal.buildLocalModalContent( {
			state: 'failed',
			title: strings.modal_title,
			heading: strings.modal_error_title,
			message: safeMessage,
			busy: false
		} ) );
	}

	static HasModalResponse( resp ) {
		return !!( resp?.data
			&& typeof resp.data.modal_html === 'string'
			&& resp.data.modal_html.length > 0
			&& ScanProgressModal.modalStates.includes( resp.data.modal_state ) );
	}

	static ModalState( resp ) {
		return ScanProgressModal.HasModalResponse( resp ) ? resp.data.modal_state : 'failed';
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

	static buildLocalModalContent( { state, title, heading, message, busy } ) {
		return `<div class="modal-header">
			<h5 class="modal-title">${ScanProgressModal.escapeHtml( title )}</h5>
			<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
		</div>
		<div class="modal-body">
			<div data-shield-scan-modal-state="${ScanProgressModal.escapeHtml( state )}" aria-busy="${busy ? 'true' : 'false'}">
				<h6>${ScanProgressModal.escapeHtml( heading )}</h6>
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

	static escapeHtml( text = '' ) {
		return String( text )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' );
	}
}
