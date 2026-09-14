import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";

export class FileLocker extends BaseComponent {

	init() {
		shieldEventsHandler_Main.add_Submit( 'form.filelocker_fileaction', ( targetEl ) => {
			this.#fileAction( targetEl );
		} );
	}

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
