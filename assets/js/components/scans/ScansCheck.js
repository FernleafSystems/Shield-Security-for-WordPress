import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { ScanProgressModal } from "./ScanProgressModal";
import { ScanRunPage } from "./ScanRunPage";
import { ScanIndicator } from "./ScanIndicator";

export class ScansCheck extends BaseComponent {

	static MISSED_UPDATES_BEFORE_NOTICE = 3;

	init() {
		this.scanState = null;
		this.nextStepTimer = null;
		this.requestRevision = 0;
		this.recoveryInFlight = false;
		this.trackedScanIds = [];
		this.missedUpdates = 0;
		this.bindRecoveryClickHandler();
		this.exec();
	}

	canRun() {
		return this._base_data.flags?.initial_check === true;
	}

	run() {
		this.check();
	}

	check() {
		if ( this.recoveryInFlight ) {
			this.scheduleCheck( 3000 );
			return;
		}

		const requestToken = this.advanceRequestRevision();
		( new AjaxService() )
		.send( {
			...this._base_data.ajax.check,
			...ScanRunPage.AjaxParams(),
			scan_ids: this.trackedScanIds
		}, false, true )
		.then( ( resp ) => {
			if ( !this.isCurrentModalRequest( requestToken ) ) {
				return;
			}

			this.handleModalResponse( resp );
		} )
		.catch( ( error ) => {
			if ( !this.isCurrentModalRequest( requestToken ) ) {
				return;
			}

			console.log( error );
			this.recordScanFailure();
			this.showError();
		} )
		.finally( () => {
			if ( !this.isCurrentModalRequest( requestToken ) ) {
				return;
			}

			this.scheduleNextStep();
		} );
	}

	bindRecoveryClickHandler() {
		shieldEventsHandler_Main.add_Click(
			'[data-shield-scan-attempt-recovery="1"]',
			( targetEl ) => this.attemptRecovery( targetEl )
		);
	}

	attemptRecovery( targetEl ) {
		if ( this.recoveryInFlight ) {
			return;
		}

		this.clearScheduledNextStep();
		const requestToken = this.advanceRequestRevision();
		const recoverAjax = this._base_data.ajax?.recover;
		const scanId = Number( targetEl.dataset.scanId );
		if ( !recoverAjax || !Number.isSafeInteger( scanId ) || scanId < 1 ) {
			this.recordScanFailure();
			this.showError();
			return;
		}

		this.recoveryInFlight = true;
		this.setRecoveryControlsBusy( true );
		( new AjaxService() )
		.send( {
			...recoverAjax,
			...ScanRunPage.AjaxParams(),
			scan_id: scanId
		}, true, true )
		.then( ( resp ) => {
			if ( !this.isCurrentModalRequest( requestToken ) ) {
				return;
			}

			this.handleModalResponse( resp );
		} )
		.catch( ( error ) => {
			if ( !this.isCurrentModalRequest( requestToken ) ) {
				return;
			}

			console.log( error );
			this.recordScanFailure();
			this.showError();
		} )
		.finally( () => {
			const isCurrentRequest = this.isCurrentModalRequest( requestToken );
			this.recoveryInFlight = false;
			this.setRecoveryControlsBusy( false );
			if ( isCurrentRequest ) {
				this.scheduleNextStep();
			}
		} );
	}

	advanceRequestRevision() {
		return ++this.requestRevision;
	}

	isCurrentModalRequest( requestToken ) {
		return requestToken === this.requestRevision;
	}

	replaceTrackedScans( scanIds ) {
		const normalizedScanIds = this.normalizeScanIds( scanIds );
		if ( normalizedScanIds.length > 0 ) {
			this.trackedScanIds = normalizedScanIds;
		}

		this.advanceRequestRevision();
		this.scanState = 'running';
		this.missedUpdates = 0;
		this.setRecoveryControlsBusy( this.recoveryInFlight );
		this.scheduleCheck( 1000 );
	}

	normalizeScanIds( scanIds ) {
		if ( !Array.isArray( scanIds ) ) {
			return [];
		}

		return [ ...new Set(
			scanIds
			.map( ( scanId ) => Number( scanId ) )
			.filter( ( scanId ) => Number.isSafeInteger( scanId ) && scanId > 0 )
		) ];
	}

	setRecoveryControlsBusy( isBusy ) {
		document.querySelectorAll( '[data-shield-scan-attempt-recovery="1"]' )
		.forEach( ( button ) => {
			if ( isBusy ) {
				button.setAttribute( 'disabled', 'disabled' );
				button.setAttribute( 'aria-disabled', 'true' );
				button.setAttribute( 'aria-busy', 'true' );
				return;
			}

			button.removeAttribute( 'disabled' );
			button.setAttribute( 'aria-disabled', 'false' );
			button.setAttribute( 'aria-busy', 'false' );
		} );
	}

	/**
	 * Progress shows in Run Manual Scan's page, or as the Actions Queue's indicator, and only elsewhere in the
	 * progress dialog.
	 */
	handleModalResponse( resp ) {
		if ( !ScanProgressModal.HasModalResponse( resp ) ) {
			const message = ScanProgressModal.ExtractErrorMessage( resp );
			if ( message.length > 0 ) {
				this.recordScanFailure();
				this.showError( message );
			}
			else {
				this.noteMissedUpdate();
			}
			return;
		}

		this.missedUpdates = 0;
		this.scanState = resp.data.modal_state;
		if ( ScanIndicator.IsAvailable() ) {
			const current = resp.data.scan_rows.find( ( row ) => row.is_current ) || resp.data.scan_rows[ 0 ];
			ScanIndicator.Show(
				this.scanState,
				this.scanState === 'running' && current ? `${current.name} ${current.progress}%` : '',
				this._base_data
			);
		}
		else {
			ScanProgressModal.ShowHtml( resp.data.modal_html );
		}
	}

	/**
	 * An update can go unanswered for reasons that have nothing to do with the scans, such as leaving the page
	 * while it's on its way, so the polling carries on. Only when updates keep going missing does the page say so,
	 * and the next update to arrive replaces that.
	 */
	noteMissedUpdate() {
		this.scanState = 'running';
		this.missedUpdates++;
		if ( this.missedUpdates !== ScansCheck.MISSED_UPDATES_BEFORE_NOTICE ) {
			return;
		}

		if ( ScanIndicator.IsAvailable() ) {
			ScanIndicator.Show( 'running', this._base_data.strings.indicator_stale, this._base_data );
		}
		else {
			ScanProgressModal.ShowStale( this._base_data.strings );
		}
	}

	showError( message = '' ) {
		if ( ScanIndicator.IsAvailable() ) {
			ScanIndicator.Show( 'failed', '', this._base_data );
		}
		else {
			ScanProgressModal.ShowError( this._base_data.strings, message );
		}
	}

	recordScanFailure() {
		this.scanState = 'failed';
	}

	scheduleNextStep() {
		if ( this.scanState === 'running' || this.scanState === 'initiating' ) {
			this.scheduleCheck( 3000 );
			return;
		}

		this.clearScheduledNextStep();
		// Run Manual Scan shows the result in the page; elsewhere the Actions Queue opens, or reloads, with it.
		if ( this.scanState === 'completed' && !ScanRunPage.IsActive() ) {
			this.nextStepTimer = setTimeout( () => {
				this.nextStepTimer = null;
				window.location.href = this._base_data.hrefs.actions_queue_scans;
			}, 1000 );
		}
	}

	scheduleCheck( delay ) {
		this.clearScheduledNextStep();
		this.nextStepTimer = setTimeout( () => {
			this.nextStepTimer = null;
			this.check();
		}, delay );
	}

	clearScheduledNextStep() {
		if ( this.nextStepTimer !== null ) {
			clearTimeout( this.nextStepTimer );
			this.nextStepTimer = null;
		}
	}
}
