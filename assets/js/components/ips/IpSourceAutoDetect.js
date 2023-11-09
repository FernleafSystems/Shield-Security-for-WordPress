import $ from 'jquery';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";

export class IpSourceAutoDetect extends BaseAutoExecComponent {

	canRun() {
		return this._base_data.flags.is_check_required;
	}

	run() {
		$.getJSON( this._base_data.url, ( resp ) => {
			if ( typeof resp !== 'undefined' && typeof resp[ 'ip' ] !== 'undefined' ) {

				let self = this;
				this._base_data.ajax[ 'ip' ] = resp[ 'ip' ];

				( new AjaxService() )
				.send( self._base_data.ajax )
				.then( ( resp ) => {
					if ( resp.success ) {
						let msg = self._base_data.strings.source_found
							+ ' ' + self._base_data.strings.ip_source
							+ ': ' + resp.data.ip_source;
						if ( !self._base_data.flags.quiet ) {
							alert( msg );
						}
					}
				} )
				.finally();
			}
		} );
	};
}