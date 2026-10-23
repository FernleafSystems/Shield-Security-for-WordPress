import { Tab } from "bootstrap";
import { AjaxService } from "../services/AjaxService";

/**
 * The Changes (Diff) tab is loaded only when it's first shown, because building a diff asks WordPress.org for the
 * original file. The Overview's buttons switch to the File Contents and Diff tabs.
 */
export class ScanItemDiffTab {

	static initializeWithin( contextEl ) {
		if ( !( contextEl instanceof Element ) ) {
			return;
		}

		contextEl.querySelectorAll( '[data-scan-item-diff="1"]' ).forEach(
			( container ) => ScanItemDiffTab.initializeContainer( contextEl, container )
		);
		contextEl.querySelectorAll( '[data-scan-item-show-tab]' ).forEach( ( button ) => {
			if ( button instanceof HTMLElement ) {
				button.addEventListener( 'click', () => ScanItemDiffTab.showTab( contextEl, button.dataset.scanItemShowTab ) );
			}
		} );
	}

	static initializeContainer( contextEl, container ) {
		const pane = container.closest( '.tab-pane' );
		const tabButton = pane === null ? null : contextEl.querySelector( `[data-bs-toggle="tab"][href="#${pane.id}"]` );
		if ( !( tabButton instanceof HTMLElement ) ) {
			return;
		}

		const loadingHtml = container.innerHTML;
		tabButton.addEventListener( 'shown.bs.tab', () => {
			if ( container.dataset.scanItemDiffState === undefined ) {
				ScanItemDiffTab.load( container, loadingHtml );
			}
		} );
		container.addEventListener( 'click', ( evt ) => {
			if ( evt.target instanceof Element && evt.target.closest( '[data-scan-item-diff-retry]' ) !== null ) {
				ScanItemDiffTab.load( container, loadingHtml );
			}
		} );
	}

	static load( container, loadingHtml ) {
		let renderAction;
		try {
			renderAction = JSON.parse( container.dataset.scanItemDiffRender );
		}
		catch ( e ) {
			renderAction = null;
		}
		if ( renderAction === null || typeof renderAction !== 'object' ) {
			ScanItemDiffTab.showFailure( container );
			return;
		}

		container.dataset.scanItemDiffState = 'loading';
		container.innerHTML = loadingHtml;

		( new AjaxService() )
		.send( renderAction, false, true )
		.then( ( resp ) => {
			if ( resp?.success && typeof resp?.data?.html === 'string' ) {
				container.innerHTML = resp.data.html;
				container.dataset.scanItemDiffState = 'loaded';
			}
			else {
				ScanItemDiffTab.showFailure( container );
			}
		} )
		.catch( () => ScanItemDiffTab.showFailure( container ) );
	}

	static showFailure( container ) {
		const state = document.createElement( 'div' );
		state.className = 'scan-item-diff-state scan-item-diff-state--warning';

		const message = document.createElement( 'p' );
		message.className = 'scan-item-diff-state__text';
		message.textContent = container.dataset.scanItemDiffFailed;

		const retry = document.createElement( 'button' );
		retry.type = 'button';
		retry.className = 'btn btn-primary btn-sm mt-2';
		retry.dataset.scanItemDiffRetry = '1';
		retry.textContent = container.dataset.scanItemDiffRetryLabel;

		state.append( message, retry );
		container.replaceChildren( state );
		container.dataset.scanItemDiffState = 'failed';
	}

	static showTab( contextEl, tabButtonId ) {
		const tabButton = contextEl.querySelector( `#${tabButtonId}` );
		if ( tabButton instanceof HTMLElement ) {
			Tab.getOrCreateInstance( tabButton ).show();
			tabButton.focus();
		}
	}
}
