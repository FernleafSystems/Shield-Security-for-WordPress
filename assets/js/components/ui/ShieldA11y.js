export function announceWithin( contextEl, message ) {
	const text = String( message || '' ).trim();
	if ( text.length < 1 ) {
		return;
	}

	const liveRegion = findLiveRegion( contextEl );
	if ( liveRegion === null ) {
		return;
	}

	liveRegion.textContent = '';
	setTimeout( () => {
		liveRegion.textContent = text;
	}, 20 );
}

export function focusElement( element ) {
	if ( !( element instanceof HTMLElement ) || !element.isConnected ) {
		return false;
	}

	if ( element.hasAttribute( 'disabled' )
		|| element.getAttribute( 'aria-hidden' ) === 'true'
		|| element.closest( '[aria-hidden="true"]' ) !== null ) {
		return false;
	}

	element.focus();
	return document.activeElement === element;
}

export function setElementBusy( element, isBusy ) {
	if ( element instanceof HTMLElement ) {
		element.setAttribute( 'aria-busy', isBusy ? 'true' : 'false' );
	}
}

function findLiveRegion( contextEl ) {
	const context = contextEl instanceof Element ? contextEl : null;
	if ( context === null ) {
		return null;
	}

	const shell = context.matches( '[data-drill-shell="1"]' )
		? context
		: context.closest( '[data-drill-shell="1"]' );
	if ( !( shell instanceof HTMLElement ) ) {
		return null;
	}

	const liveRegion = shell.querySelector( '[data-drill-live-region="1"]' );
	return liveRegion instanceof HTMLElement ? liveRegion : null;
}
