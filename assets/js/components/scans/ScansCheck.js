import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { ScanProgressModal } from "./ScanProgressModal";

export class ScansCheck extends BaseComponent {

	init() {
		this.scansRunning = false;
		this.scanFailed = false;
		this.scanCompleted = false;
		this.exec();
	}

	canRun() {
		return this._base_data.flags && this._base_data.flags.initial_check;
	}

	run() {
		this.check();
	}

	check() {
		( new AjaxService() )
		.send( {
			...this._base_data.ajax.check,
			scan_ids: this._base_data.started_scan_ids || []
		}, false, true )
		.then( ( resp ) => {
			if ( !ScanProgressModal.HasModalResponse( resp ) ) {
				this.scanFailed = true;
				this.scansRunning = false;
				this.scanCompleted = false;
				ScanProgressModal.ShowError( this._base_data.strings || {}, ScanProgressModal.ExtractErrorMessage( resp ) );
				return;
			}

			ScanProgressModal.ShowHtml( resp.data.modal_html );
			const modalState = ScanProgressModal.ModalState( resp );
			this.scanFailed = modalState === 'failed';
			this.scansRunning = modalState === 'running' || modalState === 'initiating';
			this.scanCompleted = modalState === 'completed';
		} )
		.catch( ( error ) => {
			console.log( error );
			this.scanFailed = true;
			this.scansRunning = false;
			this.scanCompleted = false;
			ScanProgressModal.ShowError( this._base_data.strings || {} );
		} )
		.finally( () => {
			if ( this.scanFailed ) {
				return;
			}

			if ( this.scansRunning ) {
				setTimeout( () => this.check(), 3000 );
				return;
			}

			if ( this.scanCompleted ) {
				setTimeout( () => window.location.href = this._base_data.hrefs.actions_queue_scans, 1000 );
			}
		} );
	};
}
