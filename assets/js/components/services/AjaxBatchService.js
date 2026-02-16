import { AjaxService } from "./AjaxService";
import { ObjectOps } from "../../util/ObjectOps";

export class AjaxBatchService {

	constructor( batchRequestData = {} ) {
		this.batchRequestData = ObjectOps.ObjClone( batchRequestData );
		this.batchItems = [];
	}

	add( item = {} ) {
		const id = typeof item.id === 'string' ? item.id.trim() : '';
		const request = item.request || {};
		if ( id.length > 0 && !ObjectOps.IsEmpty( request ) ) {
			const nextItem = {
				id: id,
				request: request,
				onSuccess: typeof item.onSuccess === 'function' ? item.onSuccess : null,
				onError: typeof item.onError === 'function' ? item.onError : null,
			};

			const existingIndex = this.batchItems.findIndex( ( queued ) => queued.id === id );
			if ( existingIndex >= 0 ) {
				this.batchItems.splice( existingIndex, 1 );
				this.batchItems.push( nextItem );
				console.warn( `AjaxBatchService: duplicate batch item id replaced: ${id}` );
			}
			else {
				this.batchItems.push( nextItem );
			}
		}
		return this;
	}

	flush() {
		if ( this.batchItems.length < 1 ) {
			return Promise.resolve( { success: true, data: { results: {} } } );
		}

		const queuedItems = this.batchItems.slice();
		this.batchItems = [];

		const callbacks = {};
		const requests = queuedItems.map( ( item ) => {
			callbacks[ item.id ] = {
				onSuccess: item.onSuccess,
				onError: item.onError,
			};
			return {
				id: item.id,
				request: item.request
			};
		} );

		const requestData = ObjectOps.ObjClone( this.batchRequestData );
		requestData.requests = requests;

		return ( new AjaxService() )
		.bg( requestData )
		.then( ( resp ) => {
			const defaultError = resp?.data?.message || 'No response result was returned for this request item.';
			const results = resp?.success ? ( resp?.data?.results || {} ) : {};

			Object.keys( callbacks ).forEach( ( id ) => {
				const result = results[ id ] || this.buildFailureResult( defaultError );
				this.dispatchResult( callbacks[ id ], result );
			} );

			return resp;
		} );
	}

	dispatchResult( callbacks, result ) {
		if ( result.success ) {
			if ( callbacks.onSuccess ) {
				callbacks.onSuccess( result );
			}
		}
		else if ( callbacks.onError ) {
			callbacks.onError( result );
		}
	}

	buildFailureResult( message ) {
		return {
			success: false,
			status_code: 500,
			error: message,
			data: {
				success: false,
				page_reload: false,
				message: message,
				error: message,
				html: ''
			}
		};
	}
}
