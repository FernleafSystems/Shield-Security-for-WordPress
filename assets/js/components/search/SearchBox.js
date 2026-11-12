import { AjaxService } from "../services/AjaxService";
import { Html } from "../../util/Html";
import { highlightSearchTerms } from "../../util/SearchHighlight";

/**
 * The one search box: Configure's search and the search dialog both use it. Typing searches once there are
 * enough characters, after a pause; the results replace whatever was there, with the searched words marked.
 * The box's container carries data-search-state (idle, loading, ready or error), so styles and tests can
 * follow it.
 */
export class SearchBox {

	/**
	 * @param {Object} options
	 * @param {HTMLElement} options.container carries the state
	 * @param {HTMLInputElement} options.input
	 * @param {HTMLElement} options.body where the results go
	 * @param {(query:string) => Object} options.buildAction the render request for a search
	 * @param {string} options.loadingText
	 * @param {string} options.errorText
	 * @param {number} [options.minChars]
	 * @param {(body:HTMLElement, html:string) => void} [options.render] puts results in place, when the page has its own way
	 */
	constructor( { container, input, body, buildAction, loadingText, errorText, minChars = 3, render = null } ) {
		this.container = container;
		this.input = input;
		this.body = body;
		this.buildAction = buildAction;
		this.loadingText = loadingText;
		this.errorText = errorText;
		this.minChars = minChars;
		this.render = render || ( ( target, html ) => {
			target.innerHTML = html;
		} );
		this.timer = null;
		this.requestKey = null;

		this.input.addEventListener( 'input', () => this.handleInput() );
		this.setState( 'idle' );
	}

	static DEBOUNCE_MS = 350;

	handleInput() {
		const query = this.input.value.trim();
		this.cancel();
		if ( query.length < this.minChars ) {
			this.showResults( '', 'idle' );
			return;
		}
		this.showResults( this.statusMarkup( this.loadingText, true ), 'loading' );
		this.timer = setTimeout( () => this.search( query ), SearchBox.DEBOUNCE_MS );
	}

	search( query ) {
		this.timer = null;
		const requestKey = `${Date.now()}-${Math.random()}`;
		this.requestKey = requestKey;

		( new AjaxService() )
		.send( this.buildAction( query ), false, true )
		.then( ( resp ) => {
			if ( this.requestKey !== requestKey ) {
				return;
			}
			if ( resp?.success && typeof resp.data?.render_output === 'string' ) {
				this.showResults( resp.data.render_output, 'ready' );
				highlightSearchTerms( this.body, query );
			}
			else {
				this.showResults( this.statusMarkup( this.errorText ), 'error' );
			}
		} )
		.catch( () => {
			if ( this.requestKey === requestKey ) {
				this.showResults( this.statusMarkup( this.errorText ), 'error' );
			}
		} );
	}

	/**
	 * Stops a search that's waiting or under way, so its results never arrive.
	 */
	cancel() {
		if ( this.timer ) {
			clearTimeout( this.timer );
			this.timer = null;
		}
		this.requestKey = null;
	}

	clear() {
		this.cancel();
		this.input.value = '';
		this.showResults( '', 'idle' );
	}

	showResults( html, state ) {
		this.render( this.body, html );
		this.setState( state );
	}

	setState( state ) {
		this.container.dataset.searchState = state;
		this.body.setAttribute( 'aria-busy', state === 'loading' ? 'true' : 'false' );
	}

	statusMarkup( message, withSpinner = false ) {
		const spinner = withSpinner
			? '<span class="shield-search-results__spinner"><span class="spinner-border text-success" role="presentation"></span></span>'
			: '';
		return `<div class="shield-search-results" data-search-results="1"><div class="shield-search-results__status">${spinner}<span class="shield-search-results__status-text">${Html.Escape( message )}</span></div></div>`;
	}
}
