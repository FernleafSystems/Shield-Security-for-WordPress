import { BaseComponent } from "../BaseComponent";
import { LiveTrafficPoller } from "./LiveTrafficPoller";
import { announceStatus } from "../ui/ShieldA11y";

export class TrafficLiveLogs extends BaseComponent {

	init() {
		this.liveLogsSection = document.getElementById( 'SectionTrafficLiveLogs' ) || false;
		this.poller = null;
		this.hasAnnouncedInitialLoad = false;
		this.lastPollFailed = false;
		this.lastPollFailureMessage = '';
		this.exec();
	}

	canRun() {
		return this.liveLogsSection;
	}

	run() {
		this.focusOutput();
		this.announceLiveLogsStatus( this.getLiveLogsLoadingMessage(), {
			politeness: 'polite',
			allowRepeat: false,
		} );
		this.poller = new LiveTrafficPoller( {
			requestData: this._base_data?.ajax?.render_live || {},
			onSuccess: ( resp ) => this.handlePollSuccess( resp ),
			onFailure: ( resp ) => this.handlePollFailure( resp ),
		} );
		this.poller.start();
	}

	focusOutput() {
		const output = this.liveLogsSection?.querySelector( '.output' ) || null;
		if ( output ) {
			output.focus();
		}
	}

	handlePollSuccess( resp ) {
		const output = this.liveLogsSection?.querySelector( '.live_logs .output' ) || null;
		if ( output && typeof resp?.data?.html === 'string' ) {
			output.innerHTML = resp.data.html;
		}
		this.announcePollRecovery();
	}

	handlePollFailure( resp ) {
		const message = this.extractResponseMessage( resp );
		this.announceLiveLogsFailure( message );
		if ( message.length > 0 ) {
			shieldServices.notification().showMessage( message, false );
		}
		else if ( resp ) {
			console.log( resp );
		}
	}

	announcePollRecovery() {
		if ( this.hasAnnouncedInitialLoad && !this.lastPollFailed ) {
			return;
		}

		this.hasAnnouncedInitialLoad = true;
		this.lastPollFailed = false;
		this.lastPollFailureMessage = '';
		this.announceLiveLogsStatus( this.getLiveLogsReadyMessage(), {
			politeness: 'polite',
			allowRepeat: false,
		} );
	}

	announceLiveLogsFailure( message ) {
		if ( this.lastPollFailed && this.lastPollFailureMessage === message ) {
			return;
		}

		this.lastPollFailed = true;
		this.lastPollFailureMessage = message;
		this.announceLiveLogsStatus( message, {
			politeness: 'assertive',
		} );
	}

	announceLiveLogsStatus( message, options = {} ) {
		announceStatus( this.liveLogsSection, message, options );
	}

	extractResponseMessage( resp ) {
		if ( typeof resp?.data?.message === 'string' && resp.data.message.length > 0 ) {
			return resp.data.message;
		}
		if ( typeof resp?.error === 'string' && resp.error.length > 0 ) {
			return resp.error;
		}
		return this.getLiveLogsFailureMessage();
	}

	getLiveLogsLoadingMessage() {
		return this.normalizeStatusMessage(
			this.liveLogsSection?.dataset.liveLogsLoading,
			'Waiting for live updates...'
		);
	}

	getLiveLogsReadyMessage() {
		return this.normalizeStatusMessage(
			this.liveLogsSection?.dataset.liveLogsReady,
			'Live log entries updated.'
		);
	}

	getLiveLogsFailureMessage() {
		return this.normalizeStatusMessage(
			this.liveLogsSection?.dataset.liveLogsError,
			'Live log update failed.'
		);
	}

	normalizeStatusMessage( message, fallback ) {
		const text = String( message || '' ).trim();
		return text.length > 0 ? text : fallback;
	}
}
