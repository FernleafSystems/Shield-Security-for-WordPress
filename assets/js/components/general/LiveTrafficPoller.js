import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";

export class LiveTrafficPoller {

	constructor( {
		requestData = {},
		intervalMs = 5000,
		maxPolls = 256,
		shouldPoll = null,
		onSuccess = null,
		onFailure = null,
	} = {} ) {
		this.requestData = requestData;
		this.intervalMs = Math.max( 1000, parseInt( intervalMs, 10 ) || 5000 );
		this.maxPolls = Math.max( 1, parseInt( maxPolls, 10 ) || 256 );
		this.shouldPoll = typeof shouldPoll === 'function' ? shouldPoll : () => document.hasFocus();
		this.onSuccess = typeof onSuccess === 'function' ? onSuccess : null;
		this.onFailure = typeof onFailure === 'function' ? onFailure : null;
		this.isRunning = false;
		this.runToken = 0;
	}

	start() {
		if ( this.isRunning ) {
			return;
		}
		this.isRunning = true;
		const token = ++this.runToken;
		this.runLoop( token ).finally();
	}

	stop() {
		this.isRunning = false;
		this.runToken++;
	}

	async runLoop( token ) {
		let remaining = this.maxPolls;
		do {
			if ( this.shouldPoll() ) {
				await this.fetchOnce();
			}
			remaining--;
			if ( !this.isRunning || this.runToken !== token || remaining < 1 ) {
				break;
			}
			await this.sleep( this.intervalMs );
		} while ( true );

		if ( this.runToken === token ) {
			this.isRunning = false;
		}
	}

	fetchOnce() {
		if ( !this.requestData || typeof this.requestData !== 'object' || Object.keys( this.requestData ).length < 1 ) {
			return Promise.resolve( null );
		}

		return ( new AjaxService() )
		.send( ObjectOps.ObjClone( this.requestData ), false )
		.then( ( resp ) => {
			if ( resp?.success ) {
				if ( this.onSuccess ) {
					this.onSuccess( resp );
				}
			}
			else if ( this.onFailure ) {
				this.onFailure( resp );
			}
			return resp;
		} )
		.catch( ( error ) => {
			if ( this.onFailure ) {
				this.onFailure( error );
			}
			return null;
		} );
	}

	sleep( ms ) {
		return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
	}
}
