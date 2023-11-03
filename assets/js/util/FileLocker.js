import { BaseService } from "./BaseService";
import { AjaxService } from "./AjaxService";
import { ObjectOps } from "./ObjectOps";

export class FileLocker extends BaseService {

	init() {
		shieldEventsHandler_Main.add_Change( '#FileLockerFileSelect', ( targetEl ) => {
			this.#select( targetEl );
		} );
		shieldEventsHandler_Main.add_Submit( 'form.filelocker_fileaction', ( targetEl ) => {
			this.#fileAction( targetEl );
		} );
	}

	#select( targetEl ) {
		let selected = targetEl.options[ targetEl.selectedIndex ];
		if ( selected.value !== '-' ) {

			const params = ObjectOps.ObjClone( this._base_data.ajax.render_diff );
			params.rid = selected.value;

			( new AjaxService() )
			.send( params )
			.then( ( resp ) => {
				if ( resp.success ) {
					document.getElementById( 'FileLockerDiffContents' ).innerHTML = resp.data.html;
				}
				else {
					alert( resp.data.error );
				}
			} )
			.finally( () => {
				targetEl.selectedIndex = 0;
			} );
		}
	};

	#fileAction( form ) {
		const buttonSubmit = form.querySelector( 'input[type=submit]' );
		if ( buttonSubmit ) {
			buttonSubmit.setAttribute( 'disabled', 'disabled' );

			( new AjaxService() )
			.send( ObjectOps.Merge( this._base_data.ajax.file_action, {
				confirmed: form.querySelector( 'input[type=checkbox]' ).checked ? 1 : 0,
				rid: buttonSubmit.dataset[ 'rid' ],
				file_action: buttonSubmit.dataset[ 'action' ]
			} ) )
			.finally( () => buttonSubmit.removeAttribute( 'disabled' ) );
		}

		return false;
	}
}