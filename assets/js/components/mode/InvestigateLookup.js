import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { getActiveLayerIndex, getLayersForShell } from "./DrillDownShared";
import { ShieldTableActivityLog } from "../tables/ShieldTableActivityLog";
import { ShieldTableTrafficLog } from "../tables/ShieldTableTrafficLog";
import { ShieldTableIpRules } from "../tables/ShieldTableIpRules";
import { ShieldTableSessions } from "../tables/ShieldTableSessions";

/**
 * Investigate's one lookup, and its tabs.
 *
 * Typing searches users, IP addresses, plugins, themes and WordPress core together; "Looking up" narrows
 * it to one kind, and a kind with nothing typed lists that kind to browse. Each result carries its kind's
 * subject, so a click opens that subject beneath the lookup through the landing's own handler, with the
 * result's value as the lookup. The lookup stays where it is, ready for the next search.
 *
 * Every tab opens in place. The logs and IP rules each start their table the first time their tab opens.
 * Live traffic opens the live subject where the lookup's subjects open, and stops when another tab opens.
 */
export class InvestigateLookup {

	static ROOT = '[data-investigate-lookup="1"]';
	static LANDING = '[data-investigate-landing="1"]';
	static DELAY_MS = 250;
	static TABLES = {
		activity: ShieldTableActivityLog,
		requests: ShieldTableTrafficLog,
		ip_rules: ShieldTableIpRules,
		sessions: ShieldTableSessions,
	};

	/**
	 * @param {Object<string,Object>} logTables each log tab's table settings
	 */
	constructor( logTables = {} ) {
		this.timer = null;
		this.requestId = 0;
		this.logTables = logTables || {};
		this.startedTables = new Set();

		document.addEventListener( 'input', ( evt ) => {
			const input = evt.target;
			if ( input instanceof HTMLInputElement && input.matches( '[data-lookup-input="1"]' ) ) {
				this.schedule( input.closest( InvestigateLookup.ROOT ) );
			}
		} );

		document.addEventListener( 'keydown', ( evt ) => {
			const input = evt.target;
			if ( !( input instanceof HTMLInputElement ) || !input.matches( '[data-lookup-input="1"]' ) ) {
				return;
			}
			const first = input.closest( InvestigateLookup.ROOT )?.querySelector( '.investigate-lookup__row' );
			if ( evt.key === 'Enter' && first instanceof HTMLElement ) {
				evt.preventDefault();
				first.click();
			}
			else if ( evt.key === 'ArrowDown' && first instanceof HTMLElement ) {
				evt.preventDefault();
				first.focus();
			}
		} );

		shieldEventsHandler_Main.add_Click( `${InvestigateLookup.ROOT} [data-lookup-kind]`, ( button ) => {
			const root = button.closest( InvestigateLookup.ROOT );
			root?.querySelectorAll( '[data-lookup-kind]' ).forEach(
				( kind ) => kind.setAttribute( 'aria-pressed', String( kind === button ) )
			);
			// WordPress core opens straight away (it carries its subject); the other kinds list or search.
			if ( button.getAttribute( 'data-lookup-kind' ) === 'core' ) {
				this.clearResults( root );
				return;
			}
			this.search( root );
			root?.querySelector( '[data-lookup-input="1"]' )?.focus();
		} );

		shieldEventsHandler_Main.add_Click( `${InvestigateLookup.LANDING} [data-investigate-tab]`, ( tab ) => {
			this.activate( tab.closest( InvestigateLookup.LANDING ), tab.getAttribute( 'data-investigate-tab' ) || 'lookup' );
		}, true );

		// Choosing a result hands the space to the subject, even when another subject was already open.
		shieldEventsHandler_Main.add_Click( `${InvestigateLookup.ROOT} .investigate-lookup__row`, ( row ) => {
			const results = row.closest( '[data-lookup-results="1"]' );
			results instanceof HTMLElement && ( results.hidden = true );
			const root = row.closest( InvestigateLookup.ROOT );
			root instanceof HTMLElement && this.setStatus( root, '' );
		} );

		// A subject opening beneath the lookup takes the results' place; the tabs follow what's open.
		[ 'shield:drill-to', 'shield:drill-back' ].forEach( ( name ) => document.addEventListener( name, ( evt ) => {
			const landing = evt.target instanceof Element ? evt.target.closest( InvestigateLookup.LANDING ) : null;
			if ( !( landing instanceof HTMLElement ) ) {
				return;
			}
			if ( name === 'shield:drill-to' ) {
				const results = landing.querySelector( '[data-lookup-results="1"]' );
				results instanceof HTMLElement && ( results.hidden = true );
				this.setStatus( landing, '' );
			}
			window.setTimeout( () => this.syncTabs( landing ) );
		} ) );

		this.startCurrentTab();
	}

	/**
	 * A link straight to a log tab starts that tab's table.
	 */
	startCurrentTab() {
		const landing = document.querySelector( InvestigateLookup.LANDING );
		const tab = landing instanceof HTMLElement ? String( landing.dataset.investigateCurrentTab || '' ) : '';
		if ( tab in InvestigateLookup.TABLES ) {
			this.startTable( tab );
		}
	}

	/**
	 * @param {Element|null} landing
	 * @param {string} tab
	 */
	activate( landing, tab ) {
		if ( !( landing instanceof HTMLElement ) ) {
			return;
		}
		const shell = landing.querySelector( '[data-drill-shell="1"]' );
		// Live traffic only runs while its tab is open.
		if ( tab !== 'live' && this.isLiveOpen( landing ) && shell instanceof HTMLElement ) {
			shieldAppMain?.components?.drill_down?.drillTo( shell, 0 );
		}

		this.showTab( landing, tab );
		if ( tab in InvestigateLookup.TABLES ) {
			this.startTable( tab );
		}
		if ( tab === 'lookup' ) {
			const input = landing.querySelector( '[data-lookup-input="1"]' );
			input instanceof HTMLElement && input.focus();
		}

		const url = new URL( window.location.href );
		[ 'tab', 'subject' ].forEach( ( param ) => url.searchParams.delete( param ) );
		if ( tab !== 'lookup' ) {
			url.searchParams.set( 'tab', tab );
		}
		window.history.replaceState( window.history.state, '', url.toString() );
	}

	/**
	 * @param {HTMLElement} landing
	 * @param {string} tab
	 */
	showTab( landing, tab ) {
		landing.dataset.investigateCurrentTab = tab;
		landing.querySelectorAll( '[data-investigate-tab]' ).forEach( ( link ) => {
			const isCurrent = link.getAttribute( 'data-investigate-tab' ) === tab;
			link.classList.toggle( 'is-current', isCurrent );
			isCurrent ? link.setAttribute( 'aria-current', 'page' ) : link.removeAttribute( 'aria-current' );
		} );
		landing.querySelectorAll( '[data-investigate-tabpanel]' ).forEach( ( panel ) => {
			if ( panel instanceof HTMLElement ) {
				panel.hidden = panel.getAttribute( 'data-investigate-tabpanel' ) !== tab;
			}
		} );
		const subjects = landing.querySelector( '[data-investigate-section="drilldown"]' );
		subjects instanceof HTMLElement && ( subjects.hidden = ![ 'lookup', 'live' ].includes( tab ) );
	}

	/**
	 * @param {HTMLElement} landing
	 */
	syncTabs( landing ) {
		const current = String( landing.dataset.investigateCurrentTab || 'lookup' );
		if ( this.isLiveOpen( landing ) ) {
			current !== 'live' && this.showTab( landing, 'live' );
		}
		else if ( current === 'live' ) {
			this.showTab( landing, 'lookup' );
		}
	}

	/**
	 * @param {HTMLElement} landing
	 * @return {boolean}
	 */
	isLiveOpen( landing ) {
		const shell = landing.querySelector( '[data-drill-shell="1"]' );
		const panel = landing.querySelector( '[data-investigate-panel="1"]' );
		return shell instanceof HTMLElement
			&& getActiveLayerIndex( getLayersForShell( shell ) ) > 0
			&& panel instanceof HTMLElement
			&& panel.dataset.investigatePanelSubject === 'live_traffic';
	}

	/**
	 * @param {string} tab
	 */
	startTable( tab ) {
		const TableClass = InvestigateLookup.TABLES[ tab ];
		const settings = this.logTables[ tab ];
		if ( this.startedTables.has( tab ) || !TableClass || !settings ) {
			return;
		}
		this.startedTables.add( tab );
		new TableClass( settings );
	}

	/**
	 * @param {Element|null} root
	 */
	clearResults( root ) {
		if ( root instanceof HTMLElement ) {
			root.querySelector( '[data-lookup-results="1"]' )?.replaceChildren();
			this.setStatus( root, '' );
		}
	}

	/**
	 * @param {Element|null} root
	 */
	schedule( root ) {
		window.clearTimeout( this.timer );
		this.timer = window.setTimeout( () => this.search( root ), InvestigateLookup.DELAY_MS );
	}

	/**
	 * @param {Element|null} root
	 */
	search( root ) {
		if ( !( root instanceof HTMLElement ) ) {
			return;
		}
		window.clearTimeout( this.timer );

		const text = String( root.querySelector( '[data-lookup-input="1"]' )?.value || '' ).trim();
		const kind = root.querySelector( '[data-lookup-kind][aria-pressed="true"]' )?.getAttribute( 'data-lookup-kind' ) || 'any';
		const strings = this.strings( root );
		const requestId = ++this.requestId;

		const intro = root.querySelector( '[data-lookup-intro="1"]' );
		const results = root.querySelector( '[data-lookup-results="1"]' );
		if ( text.length === 0 && kind === 'any' ) {
			intro instanceof HTMLElement && ( intro.hidden = false );
			results?.replaceChildren();
			this.setStatus( root, '' );
			return;
		}
		intro instanceof HTMLElement && ( intro.hidden = true );
		results instanceof HTMLElement && ( results.hidden = false );
		results?.setAttribute( 'aria-busy', 'true' );
		this.setStatus( root, strings.searching || '' );

		let action = {};
		try {
			action = JSON.parse( root.dataset.lookupAction || '{}' );
		}
		catch {
			return;
		}

		( new AjaxService() )
		.bg( ObjectOps.Merge( action, { kind, search: text } ) )
		.then( ( resp ) => {
			if ( requestId !== this.requestId ) {
				return;
			}
			if ( !resp?.success ) {
				throw new Error( 'lookup failed' );
			}
			this.render( root, resp.data?.groups || [], text, kind );
		} )
		.catch( () => {
			if ( requestId === this.requestId ) {
				results?.replaceChildren();
				this.setStatus( root, strings.failed || '' );
			}
		} )
		.finally( () => {
			if ( requestId === this.requestId ) {
				results?.setAttribute( 'aria-busy', 'false' );
			}
		} );
	}

	/**
	 * @param {HTMLElement} root
	 * @param {Array<{kind:string,label:string,icon_class:string,results:Array<{value:string,title:string,meta:string}>}>} groups
	 * @param {string} text
	 * @param {string} kind
	 */
	render( root, groups, text, kind ) {
		const strings = this.strings( root );
		const results = root.querySelector( '[data-lookup-results="1"]' );
		if ( !( results instanceof HTMLElement ) ) {
			return;
		}

		const total = groups.reduce( ( sum, group ) => sum + group.results.length, 0 );
		if ( total === 0 ) {
			results.replaceChildren();
			this.setStatus( root, text.length > 0 ? String( strings.none || '' ).replace( '%s', text ) : ( strings.none_kind || '' ) );
			return;
		}
		this.setStatus( root, total === 1 ? ( strings.count_one || '' ) : String( strings.count || '' ).replace( '%s', String( total ) ) );

		const fragment = document.createDocumentFragment();
		groups.forEach( ( group ) => {
			const subject = root.querySelector( `[data-lookup-kind="${group.kind}"]` );
			if ( !( subject instanceof HTMLElement ) ) {
				return;
			}

			const section = document.createElement( 'section' );
			section.className = 'investigate-lookup__group';
			const head = document.createElement( 'h3' );
			head.className = 'investigate-lookup__group-head';
			head.append( this.icon( group.icon_class ), document.createTextNode( group.label ) );
			if ( kind === 'any' ) {
				const count = document.createElement( 'span' );
				count.className = 'investigate-lookup__group-count';
				count.textContent = String( group.results.length );
				head.append( count );
			}
			section.append( head );

			group.results.forEach( ( result ) => section.append( this.row( subject, group, result, text ) ) );
			fragment.append( section );
		} );
		results.replaceChildren( fragment );
	}

	/**
	 * A result opens its subject's panel: it carries the subject's attributes, as the kind button does,
	 * plus the value to look up.
	 * @param {HTMLElement} subject
	 * @param {{kind:string,icon_class:string}} group
	 * @param {{value:string,title:string,meta:string}} result
	 * @param {string} text
	 * @return {HTMLButtonElement}
	 */
	row( subject, group, result, text ) {
		const row = document.createElement( 'button' );
		row.type = 'button';
		row.className = 'investigate-lookup__row';
		[ 'investigateSubject', 'investigateHeader', 'investigateRenderAction', 'investigateLookupKey', 'investigateIsLive' ]
		.forEach( ( key ) => row.dataset[ key ] = subject.dataset[ key ] || '' );
		row.dataset.drillTarget = 'panel';
		row.dataset.investigateLookupValue = result.value;

		const copy = document.createElement( 'span' );
		copy.className = 'investigate-lookup__row-copy';
		const title = document.createElement( 'span' );
		title.className = 'investigate-lookup__row-title';
		title.append( ...this.highlight( result.title, text ) );
		copy.append( title );
		if ( result.meta ) {
			const meta = document.createElement( 'span' );
			meta.className = 'investigate-lookup__row-meta';
			meta.append( ...this.highlight( result.meta, text ) );
			copy.append( meta );
		}

		const chevron = this.icon( 'bi bi-chevron-right' );
		chevron.classList.add( 'investigate-lookup__row-go' );
		row.append( this.icon( group.icon_class ), copy, chevron );
		return row;
	}

	/**
	 * The parts of a label with the typed text marked.
	 * @param {string} label
	 * @param {string} text
	 * @return {Node[]}
	 */
	highlight( label, text ) {
		const at = text.length > 0 ? label.toLowerCase().indexOf( text.toLowerCase() ) : -1;
		if ( at < 0 ) {
			return [ document.createTextNode( label ) ];
		}
		const mark = document.createElement( 'mark' );
		mark.textContent = label.slice( at, at + text.length );
		return [
			document.createTextNode( label.slice( 0, at ) ),
			mark,
			document.createTextNode( label.slice( at + text.length ) ),
		];
	}

	icon( className ) {
		const icon = document.createElement( 'i' );
		icon.className = className;
		icon.setAttribute( 'aria-hidden', 'true' );
		return icon;
	}

	setStatus( root, message ) {
		const status = root.querySelector( '[data-lookup-status="1"]' );
		if ( status instanceof HTMLElement ) {
			status.textContent = message;
		}
	}

	/**
	 * @param {HTMLElement} root
	 * @return {Object<string,string>}
	 */
	strings( root ) {
		try {
			return JSON.parse( root.dataset.lookupStrings || '{}' );
		}
		catch {
			return {};
		}
	}
}
