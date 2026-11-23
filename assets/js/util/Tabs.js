/**
 * The tab bars of Reports, Investigate and Configure share one way of showing which tab is open: the open tab
 * carries `is-current` and `aria-current="page"`, and only its panel shows.
 */

/**
 * @param {HTMLElement} root
 * @param {string} tabAttr the attribute holding each tab's key, such as data-reports-tab
 * @param {string} key the open tab
 * @return {Element|null} the open tab
 */
export function markCurrentTab( root, tabAttr, key ) {
	let current = null;
	root.querySelectorAll( `[${tabAttr}]` ).forEach( ( tab ) => {
		const isCurrent = tab.getAttribute( tabAttr ) === key;
		tab.classList.toggle( 'is-current', isCurrent );
		isCurrent ? tab.setAttribute( 'aria-current', 'page' ) : tab.removeAttribute( 'aria-current' );
		if ( isCurrent ) {
			current = tab;
		}
	} );
	return current;
}

/**
 * @param {HTMLElement} root
 * @param {string} panelAttr the attribute holding each panel's tab key, such as data-reports-tabpanel
 * @param {string} key the open tab
 */
export function showTabPanel( root, panelAttr, key ) {
	root.querySelectorAll( `[${panelAttr}]` ).forEach( ( panel ) => {
		if ( panel instanceof HTMLElement ) {
			panel.hidden = panel.getAttribute( panelAttr ) !== key;
		}
	} );
}
