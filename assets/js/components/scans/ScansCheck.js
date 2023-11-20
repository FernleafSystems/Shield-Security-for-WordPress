import $ from 'jquery';
import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";

export class ScansCheck extends BaseComponent {

	init() {
		this.scansRunning = false;
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
		.send( this._base_data.ajax.check, false )
		.then( ( resp ) => {

			if ( resp.data.success ) {
				this.scansRunning = false;
				if ( resp.data.running ) {
					for ( const scanKey of Object.keys( resp.data.running ) ) {
						if ( resp.data.running[ scanKey ] ) {
							this.scansRunning = true;
						}
					}
				}

				let modal = $( '#ScanProgressModal' );
				$( '.modal-body', modal ).html( resp.data.vars.progress_html );
				modal.modal( 'show' );
			}
		} )
		.finally( () => {
			this.scansRunning ?
				setTimeout( () => this.check(), 3000 )
				: setTimeout( () => window.location.href = this._base_data.hrefs.results, 1000 );
		} );
	};
}