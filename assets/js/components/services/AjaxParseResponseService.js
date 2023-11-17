export class AjaxParseResponseService {

	static ParseIt( raw ) {
		let parsed = {};
		try {
			parsed = JSON.parse( raw );
		}
		catch ( e ) {
			const openJsonTag = '##APTO_OPEN##';
			const closeJsonTag = '##APTO_CLOSE##';
			let start = 0;
			let end = 0;

			if ( raw.indexOf( openJsonTag ) >= 0 ) {
				start = raw.indexOf( openJsonTag ) + openJsonTag.length;
				end = raw.lastIndexOf( closeJsonTag );
			}
			else {
				start = raw.indexOf( '{' );
				end = raw.lastIndexOf( '}' );
			}

			try {
				parsed = JSON.parse( raw.substring( start, end ) );
			}
			catch ( e ) {
				parsed = {};
			}
		}
		return parsed;
	};
}