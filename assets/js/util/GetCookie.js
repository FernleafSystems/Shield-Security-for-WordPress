export class GetCookie {
	static Get( name ) {
		let parts = ( "; " + document.cookie ).split( "; " + name + "=" );
		return parts.length === 2 ? parts.pop().split( ";" ).shift() : '';
	}
}