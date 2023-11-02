import $ from 'jquery';
import { BaseService } from "./BaseService";
import { AjaxService } from "./AjaxService";
import { ObjectOps } from "./ObjectOps";

export class FileLocker extends BaseService {

	init() {
		$( document ).on( 'change', '#FileLockerFileSelect', ( evt ) => this.#select( evt ) );
		$( document ).on( 'submit', 'form.filelocker_fileaction', ( evt ) => this.#fileAction( evt ) );
	}

	#select( evt ) {
		let $selected = $( evt.currentTarget ).find( ":selected" );
		if ( $selected.val() !== '-' ) {

			const params = ObjectOps.ObjClone( this._base_data.ajax.render_diff );
			params.rid = $selected.val();

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
				$( 'option[value="-"]', $selected ).prop( 'selected', true );
			} );
		}
	};

	#fileAction( evt ) {
		evt.preventDefault();

		const form = evt.currentTarget;
		const buttonSubmit = form.querySelector( 'input[type=submit]' );
		if ( buttonSubmit ) {
			buttonSubmit.setAttribute( 'disabled', 'disabled' );

			const params = ObjectOps.ObjClone( this._base_data.ajax.file_action );
			params.confirmed = form.querySelector( 'input[type=checkbox]' ).checked ? 1 : 0;
			params.rid = buttonSubmit.dataset[ 'rid' ];
			params.file_action = buttonSubmit.dataset[ 'action' ];

			( new AjaxService() )
			.send( params )
			.finally( () => buttonSubmit.removeAttribute( 'disabled' ) );
		}

		return false;
	}
}