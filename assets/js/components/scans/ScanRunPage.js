import { announceStatus } from "../ui/ShieldA11y";
import { Html } from "../../util/Html";

/**
 * Run Manual Scan shows scan progress and the result in the page, in place of the progress dialog. The scan
 * polling hands its markup here whenever the page's progress area exists.
 */
export class ScanRunPage {

	static Host() {
		const host = document.querySelector( '[data-shield-scan-inline="1"]' );
		return host instanceof HTMLElement ? host : null;
	}

	static IsActive() {
		return ScanRunPage.Host() !== null;
	}

	static AjaxParams() {
		return ScanRunPage.IsActive() ? { display: 'page' } : {};
	}

	static Show( html ) {
		const host = ScanRunPage.Host();
		if ( host === null ) {
			return false;
		}

		host.innerHTML = html;
		host.hidden = false;

		const root = host.closest( '[data-shield-scan-run="1"]' );
		root.querySelector( '[data-scan-step-panel="choose"]' ).hidden = true;

		const stateEl = host.querySelector( '[data-shield-scan-modal-state]' );
		const state = stateEl.dataset.shieldScanModalState;
		ScanRunPage.setStep( root, state === 'completed' ? 'results' : 'scanning' );
		announceStatus( host, stateEl.dataset.shieldScanModalAnnouncement, { allowRepeat: false } );
		if ( state === 'completed' ) {
			host.querySelector( '#ScanFinishedTitle' )?.focus( { preventScroll: true } );
		}
		return true;
	}

	static ShowLocal( { state, heading, message, announcement, busy } ) {
		const esc = ( text ) => Html.Escape( String( text ) );
		// A failure here leaves the page without its choices, so it offers the way back, as the server's failure does.
		const again = ScanRunPage.Host().closest( '[data-shield-scan-run="1"]' ).dataset;
		const body = state === 'failed'
			? `<section class="shield-tool-status is-critical">
				<span class="shield-tool-status__badge"><i class="bi bi-x-lg" aria-hidden="true"></i></span>
				<div class="shield-tool-status__copy">
					<h2 class="shield-tool-status__title">${esc( heading )}</h2>
					<p class="shield-tool-status__desc">${esc( message )}</p>
				</div>
				<a class="shield-tool-btn" href="${esc( again.scanAgainHref )}">
					<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>${esc( again.scanAgainLabel )}
				</a>
			</section>`
			: `<section class="shield-tool-box">
				<div class="shield-scan-progress__overall">
					<h2 class="shield-scan-progress__title">${esc( heading )}</h2>
					<div class="shield-scan-progress__bar" role="progressbar" aria-label="${esc( heading )}"
						 aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"><span class="shield-scan-progress__part is-running"></span></div>
					<p class="shield-scan-progress__current">${esc( message )}</p>
				</div>
			</section>`;

		return ScanRunPage.Show( `<div class="shield-scan-progress shield-scan-progress--page"
			data-shield-scan-modal-state="${esc( state )}" aria-busy="${busy ? 'true' : 'false'}"
			data-shield-scan-modal-announcement="${esc( announcement )}">${body}</div>` );
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
