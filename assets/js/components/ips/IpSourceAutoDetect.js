import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";

export class IpSourceAutoDetect extends BaseAutoExecComponent {

	canRun() {
		return this._base_data.flags.is_check_required
			&& typeof fetch !== typeof undefined;
	}

	run() {
		fetch( this._base_data.url )
		.then( raw => raw.json() )
		.then( resp => {
			if ( typeof resp !== 'undefined' && typeof resp[ 'ip' ] !== 'undefined' ) {

				this._base_data.ajax[ 'ip' ] = resp[ 'ip' ];

				( new AjaxService() )
				.bg( this._base_data.ajax )
				.then( ( resp ) => {
					if ( resp.success && !this._base_data.flags.quiet ) {
						alert(
							this._base_data.strings.source_found
							+ ' ' + this._base_data.strings.ip_source
							+ ': ' + resp.data.ip_source
						);
					}
				} )
				.finally();
			}
		} )
	};
}