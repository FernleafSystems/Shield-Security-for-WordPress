import $ from 'jquery';
import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";

export class ScansCheck extends BaseComponent {

	init() {
		this.scansRunning = false;
		this.scanFailed = false;
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
		}, false )
		.then( ( resp ) => {

			if ( resp.data.success ) {
				this.scanFailed = resp.data.failed === true;
				this.scansRunning = Object.values( resp.data.running ).some( Boolean );

				let modal = $( '#ScanProgressModal' );
				$( '.modal-body', modal ).html( resp.data.vars.progress_html );
				modal.modal( 'show' );
			}
		} )
		.finally( () => {
			this.scanFailed ? null :
			this.scansRunning ?
				setTimeout( () => this.check(), 3000 )
				: setTimeout( () => window.location.href = this._base_data.hrefs.actions_queue_scans, 1000 );
		} );
	};
}
