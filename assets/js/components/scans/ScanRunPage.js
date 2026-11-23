import { announceStatus } from "../ui/ShieldA11y";

/**
 * Run Manual Scan shows the scans' progress in the page. The server renders every state: the scan actions answer
 * with the progress markup, and the states the page shows by itself (preparing, failed without a reason from the
 * server, and stale) come from templates rendered with the page.
 */
export class ScanRunPage {

	static serverStates = [ 'running', 'completed', 'failed' ];

	static Host() {
		const host = document.querySelector( '[data-scan-progress-host="1"]' );
		return host instanceof HTMLElement ? host : null;
	}

	static IsActive() {
		return ScanRunPage.Host() !== null;
	}

	/**
	 * The scan actions always answer with progress; anything else is a request that broke or was refused.
	 */
	static HasProgress( resp ) {
		return typeof resp?.data?.progress_html === 'string'
			&& ScanRunPage.serverStates.includes( resp.data.progress_state );
	}

	static ErrorMessage( resp ) {
		const message = resp?.data?.message;
		return typeof message === 'string' && message.length > 0 ? message : '';
	}

	static Show( html ) {
		const host = ScanRunPage.Host();
		host.innerHTML = html;
		ScanRunPage.shown( host );
	}

	/**
	 * @param {'initiating'|'failed'|'stale'} state
	 * @param {string} message the reason the server gave for refusing a request, if it gave one
	 */
	static ShowLocal( state, message = '' ) {
		const host = ScanRunPage.Host();
		const template = host.closest( '[data-shield-scan-run="1"]' )
							 .querySelector( `template[data-scan-progress-template="${state}"]` );
		host.replaceChildren( template.content.cloneNode( true ) );
		if ( message.length > 0 ) {
			host.querySelector( '[data-scan-progress-message="1"]' ).textContent = message;
			host.querySelector( '[data-scan-progress-state]' ).dataset.scanProgressAnnouncement = message;
		}
		ScanRunPage.shown( host );
	}

	static shown( host ) {
		host.hidden = false;
		const root = host.closest( '[data-shield-scan-run="1"]' );
		root.querySelector( '[data-scan-step-panel="choose"]' ).hidden = true;

		const stateEl = host.querySelector( '[data-scan-progress-state]' );
		const state = stateEl.dataset.scanProgressState;
		ScanRunPage.setStep( root, state === 'completed' ? 'results' : 'scanning' );
		announceStatus( host, stateEl.dataset.scanProgressAnnouncement, { allowRepeat: false } );
		if ( state === 'completed' ) {
			host.querySelector( '#ScanFinishedTitle' ).focus( { preventScroll: true } );
		}
	}

	/**
	 * Run Scans Now only works with at least one scan ticked; until then it says so beside it.
	 */
	static SyncRunButton() {
		const form = document.getElementById( 'StartScans' );
		const button = document.getElementById( 'StartScansButton' );
		if ( !( form instanceof HTMLFormElement ) || !( button instanceof HTMLButtonElement ) ) {
			return;
		}
		const none = !ScanRunPage.HasChoice( form );
		button.disabled = none;
		const note = form.querySelector( '[data-scan-none-note="1"]' );
		if ( note instanceof HTMLElement ) {
			note.hidden = !none;
		}
	}

	static HasChoice( form ) {
		return form.querySelector( 'input[data-scan-choice]:checked' ) !== null;
	}

	/**
	 * The steps are in order in the page, so the ones before the current step are done.
	 */
	static setStep( root, step ) {
		root.dataset.scanStep = step;
		const tabs = [ ...root.querySelectorAll( '[data-scan-step-tab]' ) ];
		const current = tabs.findIndex( ( tab ) => tab.dataset.scanStepTab === step );
		tabs.forEach( ( tab, index ) => {
			tab.classList.toggle( 'is-done', index < current );
			if ( index === current ) {
				tab.setAttribute( 'aria-current', 'step' );
			}
			else {
				tab.removeAttribute( 'aria-current' );
			}
		} );
	}
}
