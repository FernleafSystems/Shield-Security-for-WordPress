import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { ScanRunPage } from "./ScanRunPage";
import { ScanIndicator } from "./ScanIndicator";

/**
 * Polls the scans' progress while they run, and shows it in Run Manual Scan's page or the Actions Queue's
 * indicator. Only the latest request counts: an answer to one that something else has since replaced (a start,
 * or an attempt to recover a stalled scan) is ignored.
 */
export class ScansCheck extends BaseComponent {

	static MISSED_UPDATES_BEFORE_NOTICE = 3;

	init() {
		this.state = null;
		this.nextCheckTimer = null;
		this.requestRevision = 0;
		this.recoveryInFlight = false;
		this.trackedScanIds = [];
		this.missedUpdates = 0;
		shieldEventsHandler_Main.add_Click(
			'[data-scan-attempt-recovery="1"]',
			( button ) => this.attemptRecovery( button )
		);
		this.exec();
	}

	canRun() {
		// Only the Actions Queue and Run Manual Scan have somewhere to show the progress.
		return this._base_data.flags.initial_check === true
			&& ( ScanRunPage.IsActive() || ScanIndicator.IsAvailable() );
	}

	run() {
		this.check();
	}

	/**
	 * A start replaces whatever the polling was doing.
	 */
	stop() {
		this.requestRevision++;
		this.clearNextCheck();
	}

	/**
	 * Follows the scans a start reported running, so a failure among them is reported.
	 * @param {number[]} scanIds
	 */
	track( scanIds ) {
		this.stop();
		this.trackedScanIds = scanIds;
		this.state = 'running';
		this.missedUpdates = 0;
		this.scheduleCheck( 1000 );
	}

	check() {
		if ( this.recoveryInFlight ) {
			this.scheduleCheck( 3000 );
			return;
		}
		this.request( this._base_data.ajax.check );
	}

	/**
	 * The recovered scan is followed from then on, so a failure after it resumes is reported.
	 */
	attemptRecovery( button ) {
		if ( this.recoveryInFlight ) {
			return;
		}
		this.clearNextCheck();
		this.recoveryInFlight = true;
		ScansCheck.setRecoveryBusy( true );
		const scanId = Number( button.dataset.scanId );
		this.trackedScanIds = [ ...new Set( [ ...this.trackedScanIds, scanId ] ) ];
		this.request( this._base_data.ajax.recover, { scan_id: scanId }, true )
		.finally( () => {
			this.recoveryInFlight = false;
			ScansCheck.setRecoveryBusy( false );
		} );
	}

	request( ajax, extra = {}, showOverlay = false ) {
		const revision = ++this.requestRevision;
		const isLatest = () => revision === this.requestRevision;
		return ( new AjaxService() )
		.send( { ...ajax, ...extra, scan_ids: this.trackedScanIds }, showOverlay, true )
		.then( ( resp ) => {
			if ( isLatest() ) {
				this.handleResponse( resp );
			}
		} )
		.catch( ( error ) => {
			console.log( error );
			if ( isLatest() ) {
				this.fail();
			}
		} )
		.finally( () => {
			if ( isLatest() ) {
				this.scheduleNextStep();
			}
		} );
	}

	/**
	 * A refusal with a reason is a failure; an update that goes unanswered for reasons that have nothing to do with
	 * the scans, such as leaving the page while it's on its way, is only missed.
	 */
	handleResponse( resp ) {
		if ( !ScanRunPage.HasProgress( resp ) ) {
			const message = ScanRunPage.ErrorMessage( resp );
			if ( message.length > 0 ) {
				this.fail( message );
			}
			else {
				this.noteMissedUpdate();
			}
			return;
		}

		this.missedUpdates = 0;
		this.state = resp.data.progress_state;
		if ( ScanIndicator.IsAvailable() ) {
			ScanIndicator.Show( this.state, resp.data.progress_detail, this._base_data );
		}
		else {
			ScanRunPage.Show( resp.data.progress_html );
		}
	}

	/**
	 * The polling carries on, and only says so when updates keep going missing. The next update to arrive replaces
	 * that.
	 */
	noteMissedUpdate() {
		this.state = 'running';
		this.missedUpdates++;
		if ( this.missedUpdates !== ScansCheck.MISSED_UPDATES_BEFORE_NOTICE ) {
			return;
		}
		if ( ScanIndicator.IsAvailable() ) {
			ScanIndicator.Show( 'running', this._base_data.strings.indicator_stale, this._base_data );
		}
		else {
			ScanRunPage.ShowLocal( 'stale' );
		}
	}

	fail( message = '' ) {
		this.state = 'failed';
		if ( ScanIndicator.IsAvailable() ) {
			ScanIndicator.Show( 'failed', '', this._base_data );
		}
		else {
			ScanRunPage.ShowLocal( 'failed', message );
		}
	}

	/**
	 * Run Manual Scan shows the result itself; the Actions Queue reloads with it.
	 */
	scheduleNextStep() {
		if ( this.state === 'running' ) {
			this.scheduleCheck( 3000 );
			return;
		}
		this.clearNextCheck();
		if ( this.state === 'completed' && !ScanRunPage.IsActive() ) {
			this.nextCheckTimer = setTimeout( () => {
				window.location.href = this._base_data.hrefs.actions_queue_scans;
			}, 1000 );
		}
	}

	scheduleCheck( delay ) {
		this.clearNextCheck();
		this.nextCheckTimer = setTimeout( () => {
			this.nextCheckTimer = null;
			this.check();
		}, delay );
	}

	clearNextCheck() {
		if ( this.nextCheckTimer !== null ) {
			clearTimeout( this.nextCheckTimer );
			this.nextCheckTimer = null;
		}
	}

	static setRecoveryBusy( isBusy ) {
		document.querySelectorAll( '[data-scan-attempt-recovery="1"]' ).forEach( ( button ) => {
			button.disabled = isBusy;
			button.setAttribute( 'aria-busy', isBusy ? 'true' : 'false' );
		} );
	}
}
