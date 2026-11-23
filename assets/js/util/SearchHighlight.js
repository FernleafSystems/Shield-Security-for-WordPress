/**
 * Marks the searched words in each result's name and detail, so it's clear why it matched. Only text
 * inside elements marked data-search-highlight="1" is touched.
 * @param {ParentNode} container
 * @param {string} query
 */
export function highlightSearchTerms( container, query ) {
	const terms = String( query || '' ).toLowerCase().split( /\s+/ ).filter( ( term ) => term.length > 1 );
	if ( terms.length < 1 ) {
		return;
	}
	const escapeTerm = ( term ) => term.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	const pattern = new RegExp( `(${terms.map( escapeTerm ).join( '|' )})`, 'gi' );
	container.querySelectorAll( '[data-search-highlight="1"]' ).forEach( ( element ) => {
		const walker = document.createTreeWalker( element, NodeFilter.SHOW_TEXT );
		const textNodes = [];
		while ( walker.nextNode() ) {
			textNodes.push( walker.currentNode );
		}
		textNodes.forEach( ( node ) => {
			const text = node.nodeValue || '';
			if ( !pattern.test( text ) ) {
				return;
			}
			pattern.lastIndex = 0;
			const fragment = document.createDocumentFragment();
			text.split( pattern ).forEach( ( part, index ) => {
				if ( index % 2 === 1 ) {
					const mark = document.createElement( 'mark' );
					mark.textContent = part;
					fragment.appendChild( mark );
				}
				else if ( part.length > 0 ) {
					fragment.appendChild( document.createTextNode( part ) );
				}
			} );
			node.parentNode?.replaceChild( fragment, node );
		} );
	} );
}
