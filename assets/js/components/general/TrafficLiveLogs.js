import { BaseComponent } from "../BaseComponent";
import { LiveTrafficPoller } from "./LiveTrafficPoller";

export class TrafficLiveLogs extends BaseComponent {

	init() {
		this.liveLogsSection = document.getElementById( 'SectionTrafficLiveLogs' ) || false;
		this.poller = null;
		this.exec();
	}

	canRun() {
		return this.liveLogsSection;
	}

	run() {
		this.focusOutput();
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
	}

	handlePollFailure( resp ) {
		if ( typeof resp?.data?.message === 'string' && resp.data.message.length > 0 ) {
			alert( resp.data.message );
		}
		else if ( resp ) {
			console.log( resp );
		}
	}
}
