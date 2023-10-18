export class AjaxParseResponseService {

	static ParseIt( raw ) {
		let parsed = {};
		try {
			parsed = JSON.parse( raw );
		}
		catch ( e ) {
			let openJsonTag = '##APTO_OPEN##';
			let closeJsonTag = '##APTO_CLOSE##';
			let start = 0;
			let end = 0;

			if ( raw.indexOf( openJsonTag ) >= 0 ) {
				start = raw.indexOf( openJsonTag ) + openJsonTag.length;
				end = raw.indexOf( closeJsonTag );
				try {
					parsed = JSON.parse( raw.substring( start, end ) );
				}
				catch ( e ) {
					start = raw.indexOf( '{' );
					end = raw.lastIndexOf( '}' ) + 1;
					parsed = JSON.parse( raw.substring( start, end ) );
				}
			}
		}
		return parsed;
	};
}