export class ObjectOps {
	static ObjClone( obj ) {
		return JSON.parse( JSON.stringify( obj ) )
	};

	/**
	 * https://gomakethings.com/merging-objects-with-vanilla-javascript/
	 * @returns {{}}
	 * @constructor
	 */
	static Merge() {

		let extended = {};

		// Merge the object into the extended object
		const merge = function ( obj ) {
			for ( const prop in obj ) {
				if ( obj.hasOwnProperty( prop ) ) {
					extended[ prop ] = obj[ prop ]; // Push each value from `obj` into `extended`
				}
			}
		};

		// Loop through each object and conduct a merge
		for ( let i = 0; i < arguments.length; i++ ) {
			merge( arguments[ i ] );
		}

		return extended;
	};

	static IsEmpty( obj ) {
		return typeof obj === 'object' && Object.keys( obj ).length === 0;
	}
}