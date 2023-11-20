import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";

export class TrafficLiveLogs extends BaseComponent {

	init() {
		this.liveLogsSection = document.getElementById( 'SectionTrafficLiveLogs' ) || false;
		this.exec();
	}

	canRun() {
		return this.liveLogsSection;
	}

	run() {
		this.runAutoTrafficUpdate().finally();
	}

	async runAutoTrafficUpdate() {
		if ( this.liveLogsSection ) {
			this.liveLogsSection.querySelector( '.output' ).focus();
			let max = 256;
			do {
				if ( document.hasFocus() ) {
					this.updateTraffic();
				}
				await this.sleep( 5000 );
			} while ( max-- > 0 );
		}
	}

	updateTraffic() {
		( new AjaxService() )
		.send( ObjectOps.ObjClone( this._base_data.ajax.render_live ), false )
		.then( ( resp ) => {
			if ( resp.success ) {
				this.liveLogsSection.querySelector( '.live_logs .output' ).innerHTML = resp.data.html;
			}
			else {
				alert( resp.data.message );
				// console.log( resp );
			}
		} )
		.catch( ( error ) => {
			console.log( error );
		} )
		.finally();
	}

	sleep( ms ) {
		return new Promise( resolve => setTimeout( resolve, ms ) );
	}
}