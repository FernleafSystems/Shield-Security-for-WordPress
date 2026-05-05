const liveRegionAnnouncements = new WeakMap();
let globalLiveRegion = null;

export function announceWithin( contextEl, message, options = {} ) {
	const text = String( message || '' ).trim();
	if ( text.length < 1 ) {
		return;
	}

	const liveRegion = findLiveRegion( contextEl );
	if ( liveRegion === null ) {
		return;
	}

	const politeness = normalizePoliteness( options?.politeness );
	const announcementKey = {
		text,
		politeness: politeness || String( liveRegion.getAttribute( 'aria-live' ) || '' ).trim(),
	};
	if ( options?.allowRepeat === false ) {
		const previousAnnouncement = liveRegionAnnouncements.get( liveRegion ) || null;
		if ( previousAnnouncement?.text === announcementKey.text
			&& previousAnnouncement?.politeness === announcementKey.politeness ) {
			return;
		}
	}

	liveRegionAnnouncements.set( liveRegion, announcementKey );
	if ( politeness.length > 0 ) {
		liveRegion.setAttribute( 'aria-live', politeness );
	}
	liveRegion.textContent = '';
	setTimeout( () => {
		liveRegion.textContent = text;
	}, 20 );
}

export function announceGlobal( message, options = {} ) {
	const text = String( message || '' ).trim();
	if ( text.length < 1 ) {
		return;
	}

	const liveRegion = ensureGlobalLiveRegion();
	if ( liveRegion === null ) {
		return;
	}

	const politeness = normalizePoliteness( options?.politeness ) || 'assertive';
	liveRegion.setAttribute( 'aria-live', politeness );
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

	const modalShell = context.matches( '#ShieldModalContainer' )
		? context
		: context.closest( '#ShieldModalContainer' );
	if ( modalShell instanceof HTMLElement ) {
		const modalLiveRegion = modalShell.querySelector( '[data-shield-modal-live-region="1"]' );
		return modalLiveRegion instanceof HTMLElement ? modalLiveRegion : null;
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

function ensureGlobalLiveRegion() {
	if ( globalLiveRegion instanceof HTMLElement && globalLiveRegion.isConnected ) {
		return globalLiveRegion;
	}

	if ( typeof document === 'undefined' || !( document.body instanceof HTMLElement ) ) {
		return null;
	}

	globalLiveRegion = document.createElement( 'div' );
	globalLiveRegion.setAttribute( 'role', 'status' );
	globalLiveRegion.setAttribute( 'aria-live', 'assertive' );
	globalLiveRegion.setAttribute( 'aria-atomic', 'true' );
	globalLiveRegion.style.position = 'absolute';
	globalLiveRegion.style.width = '1px';
	globalLiveRegion.style.height = '1px';
	globalLiveRegion.style.padding = '0';
	globalLiveRegion.style.margin = '-1px';
	globalLiveRegion.style.overflow = 'hidden';
	globalLiveRegion.style.clip = 'rect(0 0 0 0)';
	globalLiveRegion.style.whiteSpace = 'nowrap';
	globalLiveRegion.style.border = '0';
	document.body.appendChild( globalLiveRegion );
	return globalLiveRegion;
}

function normalizePoliteness( politeness ) {
	const value = String( politeness || '' ).trim();
	return value === 'polite' || value === 'assertive'
		? value
		: '';
}
