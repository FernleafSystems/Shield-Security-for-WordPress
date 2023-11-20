export class Forms {
	/**
	 * https://gomakethings.com/serializing-form-data-with-the-vanilla-js-formdata-object/
	 */
	static Serialize( form, jsonEncode = false ) {
		let obj = {};
		let formData = new FormData( form );
		for ( let inputName of formData.keys() ) {
			/** Custom handling for multiselect fields */
			if ( inputName.endsWith( '[]' ) ) {
				let cleanName = inputName.replace( /\[]$/, '' );
				if ( !( cleanName in obj ) ) {
					obj[ cleanName ] = formData.getAll( inputName );
				}
			}
			else {
				obj[ inputName ] = formData.get( inputName );
			}
		}
		return jsonEncode ? JSON.stringify( obj ) : obj;
	};
}