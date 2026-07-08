import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { ScanProgressModal } from "./ScanProgressModal";

export class ScansCheck extends BaseComponent {

	static recoveryClickHandlerBound = false;
	static recoveryHandlerInstance = null;
	static requestSerial = 0;
	static activeRequestToken = 0;
	static recoveryInFlight = false;

	init() {
		this.scansRunning = false;
		this.scanFailed = false;
		this.scanCompleted = false;
		this.nextStepTimer = null;
		this.bindRecoveryClickHandler();
		this.exec();
	}

	canRun() {
		return this._base_data.flags && this._base_data.flags.initial_check;
	}

	run() {
		this.check();
	}

	check() {
		if ( ScansCheck.recoveryInFlight ) {
			return;
		}

		ScansCheck.recoveryHandlerInstance = this;
		const requestToken = this.beginModalRequest();
		( new AjaxService() )
		.send( {
			...this._base_data.ajax.check,
			scan_ids: this._base_data.started_scan_ids || []
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
			ScanProgressModal.ShowError( this._base_data.strings || {} );
		} )
		.finally( () => {
			if ( !this.isCurrentModalRequest( requestToken ) ) {
				return;
			}

			this.scheduleNextStep();
		} );
	};

	bindRecoveryClickHandler() {
		ScansCheck.recoveryHandlerInstance = this;
		if ( ScansCheck.recoveryClickHandlerBound ) {
			return;
		}

		shieldEventsHandler_Main.add_Click(
			'[data-shield-scan-attempt-recovery="1"]',
			( targetEl ) => ScansCheck.recoveryHandlerInstance.attemptRecovery( targetEl )
		);
		ScansCheck.recoveryClickHandlerBound = true;
	}

	attemptRecovery( targetEl ) {
		if ( ScansCheck.recoveryInFlight ) {
			return;
		}

		this.clearScheduledNextStep();
		const requestToken = this.beginModalRequest();
		const recoverAjax = this._base_data.ajax?.recover;
		const scanId = parseInt( targetEl.dataset.scanId || '0', 10 );
		if ( !recoverAjax || scanId < 1 ) {
			this.recordScanFailure();
			ScanProgressModal.ShowError( this._base_data.strings || {} );
			return;
		}

		ScansCheck.recoveryInFlight = true;
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
			ScanProgressModal.ShowError( this._base_data.strings || {} );
		} )
		.finally( () => {
			if ( !this.isCurrentModalRequest( requestToken ) ) {
				return;
			}

			ScansCheck.recoveryInFlight = false;
			this.setRecoveryControlsBusy( false );
			this.scheduleNextStep();
		} );
	}

	beginModalRequest() {
		ScansCheck.activeRequestToken = ++ScansCheck.requestSerial;
		return ScansCheck.activeRequestToken;
	}

	isCurrentModalRequest( requestToken ) {
		return requestToken === ScansCheck.activeRequestToken;
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
			ScanProgressModal.ShowError( this._base_data.strings || {}, ScanProgressModal.ExtractErrorMessage( resp ) );
			return;
		}

		ScanProgressModal.ShowHtml( resp.data.modal_html );
		const modalState = ScanProgressModal.ModalState( resp );
		this.scanFailed = modalState === 'failed';
		this.scansRunning = modalState === 'running' || modalState === 'initiating';
		this.scanCompleted = modalState === 'completed';
	}

	recordScanFailure() {
		this.scanFailed = true;
		this.scansRunning = false;
		this.scanCompleted = false;
	}

	scheduleNextStep() {
		this.clearScheduledNextStep();

		if ( this.scanFailed ) {
			return;
		}

		if ( this.scansRunning ) {
			this.nextStepTimer = setTimeout( () => this.check(), 3000 );
			return;
		}

		if ( this.scanCompleted ) {
			this.nextStepTimer = setTimeout( () => window.location.href = this._base_data.hrefs.actions_queue_scans, 1000 );
		}
	}

	clearScheduledNextStep() {
		if ( this.nextStepTimer !== null ) {
			clearTimeout( this.nextStepTimer );
			this.nextStepTimer = null;
		}
	}
}
