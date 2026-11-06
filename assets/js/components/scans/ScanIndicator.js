/**
 * While scans run, the Actions Queue says so in its top bar rather than covering the queue with a dialog: a link
 * to Run Manual Scan, which shows their progress. The scan polling keeps it up to date; once the scans finish the
 * queue reloads with their results. Only a change of state is announced, not each step of progress.
 */
export class ScanIndicator {

	/**
	 * Only the Actions Queue has the top bar it sits in; Run Manual Scan shows progress in its own page.
	 */
	static IsAvailable() {
		return ScanIndicator.bar() !== null;
	}

	static bar() {
		return document.querySelector( '[data-actions-landing="1"]' ) === null
			? null
			: document.querySelector( '[data-operator-step-tabs="1"]' );
	}

	/**
	 * @param {'running'|'initiating'|'completed'|'failed'} state
	 * @param {string} detail what's running now, such as the current scan and how far it has got
	 * @param {Object} base the scans component's data
	 */
	static Show( state, detail, base ) {
		const indicator = ScanIndicator.ensure( base );
		const label = {
			running: base.strings.indicator_running,
			initiating: base.strings.indicator_running,
			completed: base.strings.indicator_done,
			failed: base.strings.indicator_failed,
		}[ state ];

		indicator.dataset.state = state;
		const text = indicator.querySelector( '.shield-scan-indicator__text' );
		if ( text.textContent !== label ) {
			text.textContent = label;
		}
		indicator.querySelector( '.shield-scan-indicator__detail' ).textContent = detail.length > 0 ? ` · ${detail}` : '';
	}

	static ensure( base ) {
		const bar = ScanIndicator.bar();
		let indicator = bar.querySelector( '[data-shield-scan-indicator="1"]' );
		if ( indicator === null ) {
			indicator = document.createElement( 'a' );
			indicator.className = 'shield-scan-indicator';
			indicator.dataset.shieldScanIndicator = '1';
			indicator.href = base.hrefs.scan_run;
			indicator.title = base.strings.indicator_view;
			indicator.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>'
				+ '<i class="bi bi-check-circle-fill shield-scan-indicator__done" aria-hidden="true"></i>'
				+ '<i class="bi bi-exclamation-triangle-fill shield-scan-indicator__failed" aria-hidden="true"></i>'
				+ '<span class="shield-scan-indicator__text" role="status"></span>'
				+ '<span class="shield-scan-indicator__detail"></span>'
				+ '<i class="bi bi-arrow-right" aria-hidden="true"></i>';
			bar.insertBefore( indicator, bar.querySelector( ':scope > .actions-queue-sections__view, :scope > .operator-step-tabs__actions' ) );
		}
		return indicator;
	}
}
