import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { LiveTrafficPoller } from "./LiveTrafficPoller";
import { announceStatus } from "../ui/ShieldA11y";
import { ObjectOps } from "../../util/ObjectOps";

export class TrafficLiveLogs extends BaseComponent {

	init() {
		this.liveLogsSection = document.getElementById( 'SectionTrafficLiveLogs' );
		this.poller = null;
		this.hasAnnouncedInitialLoad = false;
		this.lastPollFailed = false;
		this.lastPollFailureMessage = '';
		this.exec();
	}

	canRun() {
		return this.liveLogsSection instanceof HTMLElement;
	}

	run() {
		this.registerLiveLogToggle();
		this.focusOutput();
		this.announceLiveLogsStatus( this.getLiveLogsLoadingMessage(), {
			politeness: 'polite',
			allowRepeat: false,
		} );
		this.poller = new LiveTrafficPoller( {
			requestData: this._base_data.ajax.render_live,
			onSuccess: ( resp ) => this.handlePollSuccess( resp ),
			onFailure: ( resp ) => this.handlePollFailure( resp ),
		} );
		this.poller.start();
	}

	registerLiveLogToggle() {
		shieldEventsHandler_Main.add_Change( '[data-traffic-live-log-toggle]', ( targetEl ) => {
			this.setLiveLogEnabled( targetEl );
		} );
	}

	setLiveLogEnabled( targetEl ) {
		const toggle = targetEl instanceof Element ? targetEl.closest( '[data-traffic-live-log-toggle]' ) : null;
		if ( !( toggle instanceof HTMLInputElement ) || toggle.disabled ) {
			return;
		}

		const previousState = !toggle.checked;
		toggle.disabled = true;
		( new AjaxService() )
		.send( ObjectOps.Merge(
			this._base_data.ajax.set_live_log_enabled,
			{ enabled: toggle.checked ? 'Y' : 'N' }
		) )
		.then( ( resp ) => {
			if ( !resp?.success ) {
				toggle.checked = previousState;
			}
		} )
		.finally( () => {
			toggle.disabled = false;
		} );
	}

	focusOutput() {
		const output = this.liveLogsSection?.querySelector( '.output' ) || null;
		if ( output instanceof HTMLElement ) {
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
