import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { ScanProgressModal } from "./ScanProgressModal";

export class ScansCheck extends BaseComponent {

	init() {
		this.scanState = null;
		this.nextStepTimer = null;
		this.requestRevision = 0;
		this.recoveryInFlight = false;
		this.trackedScanIds = this.normalizeScanIds( this._base_data.started_scan_ids );
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
			ScanProgressModal.ShowError( this._base_data.strings );
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
			ScanProgressModal.ShowError( this._base_data.strings );
			return;
		}

		this.recoveryInFlight = true;
		this.setRecoveryControlsBusy( true );
		( new AjaxService() )
		.send( {
			...recoverAjax,
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
			ScanProgressModal.ShowError( this._base_data.strings );
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

	handleModalResponse( resp ) {
		if ( !ScanProgressModal.HasModalResponse( resp ) ) {
			this.recordScanFailure();
			ScanProgressModal.ShowError( this._base_data.strings, ScanProgressModal.ExtractErrorMessage( resp ) );
			return;
		}

		ScanProgressModal.ShowHtml( resp.data.modal_html );
		this.scanState = resp.data.modal_state;
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
		if ( this.scanState === 'completed' ) {
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
