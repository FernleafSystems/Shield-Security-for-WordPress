const liveRegionAnnouncements = new WeakMap();
let globalLiveRegion = null;

export function announceStatus( contextEl, message, options = {} ) {
	const text = String( message || '' ).trim();
	if ( text.length < 1 ) {
		return;
	}

	const liveRegion = resolveStatusRegion( contextEl );
	if ( liveRegion instanceof HTMLElement ) {
		announceInRegion( liveRegion, text, options );
		return;
	}

	announceGlobal( text, options );
}

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
	replaceLiveRegionText( liveRegion, text );
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
	replaceLiveRegionText( liveRegion, text );
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

function resolveStatusRegion( contextEl ) {
	const context = contextEl instanceof Element ? contextEl : null;
	if ( context === null ) {
		return null;
	}

	const existingRegion = findStatusRegion( context );
	if ( existingRegion instanceof HTMLElement ) {
		return existingRegion;
	}

	return ensureLocalStatusRegion( context );
}

function findStatusRegion( context ) {
	if ( context instanceof HTMLElement && context.matches( '[data-shield-status-region="1"]' ) ) {
		return context;
	}

	const containedRegion = context.querySelector( '[data-shield-status-region="1"]' );
	if ( containedRegion instanceof HTMLElement ) {
		return containedRegion;
	}

	let parent = context.parentElement;
	while ( parent instanceof HTMLElement && parent !== document.body ) {
		if ( parent.matches( '[data-shield-status-region="1"]' ) ) {
			return parent;
		}

		const parentRegion = [ ...parent.children ].find(
			( child ) => child instanceof HTMLElement && child.matches( '[data-shield-status-region="1"]' )
		);
		if ( parentRegion instanceof HTMLElement ) {
			return parentRegion;
		}
		parent = parent.parentElement;
	}

	return null;
}

function ensureLocalStatusRegion( context ) {
	if ( !( context instanceof HTMLElement ) || !context.isConnected ) {
		return null;
	}

	const liveRegion = document.createElement( 'div' );
	liveRegion.className = 'screen-reader-text';
	liveRegion.setAttribute( 'role', 'status' );
	liveRegion.setAttribute( 'aria-live', 'polite' );
	liveRegion.setAttribute( 'aria-atomic', 'true' );
	liveRegion.dataset.shieldStatusRegion = '1';
	applyHiddenRegionStyles( liveRegion );
	context.appendChild( liveRegion );
	return liveRegion;
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
	globalLiveRegion.dataset.shieldStatusRegion = '1';
	applyHiddenRegionStyles( globalLiveRegion );
	document.body.appendChild( globalLiveRegion );
	return globalLiveRegion;
}

function announceInRegion( liveRegion, text, options = {} ) {
	const politeness = normalizePoliteness( options?.politeness )
		|| String( liveRegion.getAttribute( 'aria-live' ) || '' ).trim()
		|| 'polite';
	const announcementKey = {
		text,
		politeness,
	};
	if ( options?.allowRepeat === false ) {
		const previousAnnouncement = liveRegionAnnouncements.get( liveRegion ) || null;
		if ( previousAnnouncement?.text === announcementKey.text
			&& previousAnnouncement?.politeness === announcementKey.politeness ) {
			return;
		}
	}

	liveRegionAnnouncements.set( liveRegion, announcementKey );
	liveRegion.setAttribute( 'aria-live', politeness );
	replaceLiveRegionText( liveRegion, text );
}

function replaceLiveRegionText( liveRegion, text ) {
	liveRegion.textContent = '';
	setTimeout( () => {
		liveRegion.textContent = text;
	}, 20 );
}

function applyHiddenRegionStyles( liveRegion ) {
	liveRegion.style.position = 'absolute';
	liveRegion.style.width = '1px';
	liveRegion.style.height = '1px';
	liveRegion.style.padding = '0';
	liveRegion.style.margin = '-1px';
	liveRegion.style.overflow = 'hidden';
	liveRegion.style.clip = 'rect(0 0 0 0)';
	liveRegion.style.whiteSpace = 'nowrap';
	liveRegion.style.border = '0';
}

function normalizePoliteness( politeness ) {
	const value = String( politeness || '' ).trim();
	return value === 'polite' || value === 'assertive'
		? value
		: '';
}
