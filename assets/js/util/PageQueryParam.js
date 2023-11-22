export class PageQueryParam {
	static Retrieve( param ) {
		return ( new URLSearchParams( window.location.search ) ).get( param )
	};
}