import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { getActiveLayerIndex, getLayersForShell } from "./DrillDownShared";
import { ShieldTableActivityLog } from "../tables/ShieldTableActivityLog";
import { ShieldTableTrafficLog } from "../tables/ShieldTableTrafficLog";
import { ShieldTableIpRules } from "../tables/ShieldTableIpRules";
import { ShieldTableSessions } from "../tables/ShieldTableSessions";
import { LiveTrafficPoller } from "../general/LiveTrafficPoller";
import { announceStatus } from "../ui/ShieldA11y";

/**
 * Investigate's one lookup, and its tabs.
 *
 * Typing searches users, IP addresses, plugins, themes and WordPress core together; the kind buttons narrow
 * it to one kind, and a kind with nothing typed lists that kind to browse. Each result carries its kind's
 * subject, so a click opens that subject beneath the lookup through the landing's own handler, with the
 * result's value as the lookup. The lookup stays where it is, ready for the next search.
 *
 * Every tab opens in place and keeps its own link, the one the menu uses. The logs and IP rules each start
 * their table the first time their tab opens. Live traffic updates only while its tab is open, and keeps what
 * it last showed while it waits.
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
		this.livePoller = null;

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
			// WordPress core opens straight away (it carries its subject); the other kinds put away whatever
			// is open, and list or search.
			if ( button.getAttribute( 'data-lookup-kind' ) === 'core' ) {
				this.clearResults( root );
				return;
			}
			this.closeSubject( root?.closest( InvestigateLookup.LANDING ) );
			this.search( root );
			root?.querySelector( '[data-lookup-input="1"]' )?.focus();
		} );

		shieldEventsHandler_Main.add_Click( `${InvestigateLookup.LANDING} [data-investigate-tab]`, ( tab ) => {
			this.activate( tab.closest( InvestigateLookup.LANDING ), tab.getAttribute( 'data-investigate-tab' ) || 'lookup' );
		}, true );

		// The menu's links to the tabs open them in place too.
		document.addEventListener( 'click', ( evt ) => {
			const link = evt.target instanceof Element ? evt.target.closest( '#NavSideBar a[href]' ) : null;
			const landing = document.querySelector( InvestigateLookup.LANDING );
			const tab = link instanceof HTMLAnchorElement && landing instanceof HTMLElement ? this.tabForHref( landing, link.href ) : '';
			if ( tab !== '' && !evt.defaultPrevented && evt.button === 0 && !evt.metaKey && !evt.ctrlKey && !evt.shiftKey ) {
				evt.preventDefault();
				this.activate( landing, tab );
			}
		} );

		// Choosing a result hands the space to the subject, even when another subject was already open.
		shieldEventsHandler_Main.add_Click( `${InvestigateLookup.ROOT} .investigate-lookup__row`, ( row ) => {
			// A search still waiting to run, or still on its way, would put the chosen subject away again.
			window.clearTimeout( this.timer );
			this.requestId++;
			const results = row.closest( '[data-lookup-results="1"]' );
			results instanceof HTMLElement && ( results.hidden = true );
			const root = row.closest( InvestigateLookup.ROOT );
			root instanceof HTMLElement && this.setStatus( root, '' );
		} );

		// A subject opening beneath the lookup takes the results' place.
		document.addEventListener( 'shield:drill-to', ( evt ) => {
			const landing = evt.target instanceof Element ? evt.target.closest( InvestigateLookup.LANDING ) : null;
			if ( landing instanceof HTMLElement ) {
				const results = landing.querySelector( '[data-lookup-results="1"]' );
				results instanceof HTMLElement && ( results.hidden = true );
				this.setStatus( landing, '' );
			}
		} );

		this.startCurrentTab();
	}

	/**
	 * A link straight to a tab starts what that tab runs.
	 */
	startCurrentTab() {
		const landing = document.querySelector( InvestigateLookup.LANDING );
		if ( landing instanceof HTMLElement ) {
			this.startTab( landing, String( landing.dataset.investigateCurrentTab || 'lookup' ) );
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
		this.showTab( landing, tab );
		this.startTab( landing, tab );
		if ( tab === 'lookup' ) {
			const input = landing.querySelector( '[data-lookup-input="1"]' );
			input instanceof HTMLElement && input.focus();
		}

		// The address is the tab's own link, so a reload or a copied link opens this tab.
		const link = landing.querySelector( `a[data-investigate-tab="${tab}"]` );
		if ( link instanceof HTMLAnchorElement && link.href !== window.location.href ) {
			window.history.replaceState( window.history.state, '', link.href );
		}
	}

	/**
	 * @param {HTMLElement} landing
	 * @param {string} tab
	 */
	startTab( landing, tab ) {
		if ( tab in InvestigateLookup.TABLES ) {
			this.startTable( tab );
		}
		tab === 'live' ? this.startLive( landing ) : this.stopLive();
	}

	/**
	 * The tab that a link leads to, if it leads to one and to nothing more.
	 * @param {HTMLElement} landing
	 * @param {string} href
	 * @return {string}
	 */
	tabForHref( landing, href ) {
		const route = ( url ) => {
			const params = new URL( url, window.location.href ).searchParams;
			// A link carrying more than its route (say, an IP to analyse) leads somewhere of its own.
			return [ ...params.keys() ].every( ( key ) => [ 'page', 'nav', 'nav_sub' ].includes( key ) )
				? `${params.get( 'nav' ) || ''}/${params.get( 'nav_sub' ) || ''}`
				: '';
		};
		const wanted = route( href );
		const link = wanted === '' ? null : [ ...landing.querySelectorAll( 'a[data-investigate-tab]' ) ].find(
			( candidate ) => candidate instanceof HTMLAnchorElement && route( candidate.href ) === wanted
		);
		return link ? String( link.getAttribute( 'data-investigate-tab' ) || '' ) : '';
	}

	/**
	 * @param {HTMLElement} landing
	 */
	startLive( landing ) {
		const panel = landing.querySelector( '[data-investigate-tabpanel="live"]' );
		const requestData = window?.shield_vars_main?.comps?.traffic?.ajax?.render_live || null;
		if ( !( panel instanceof HTMLElement ) || !requestData ) {
			return;
		}
		if ( this.livePoller === null ) {
			const section = panel.querySelector( '#SectionTrafficLiveLogs' ) || panel;
			const output = () => panel.querySelector( '.live_logs .output' );
			let lastFailure = null;
			this.livePoller = new LiveTrafficPoller( {
				requestData,
				onSuccess: ( resp ) => {
					if ( output() instanceof HTMLElement && typeof resp?.data?.html === 'string' ) {
						output().innerHTML = resp.data.html;
					}
					// Say when it's ready, and again when it recovers, but not on every update.
					if ( lastFailure !== '' ) {
						lastFailure = '';
						announceStatus( section, section.dataset?.liveLogsReady || '', { politeness: 'polite', allowRepeat: false } );
					}
				},
				onFailure: ( resp ) => {
					const message = String( resp?.data?.message || resp?.error || section.dataset?.liveLogsError || '' );
					if ( message !== '' && message !== lastFailure ) {
						lastFailure = message;
						const note = document.createElement( 'div' );
						note.className = 'text-muted small';
						note.textContent = message;
						output()?.replaceChildren( note );
						announceStatus( section, message, { politeness: 'assertive' } );
					}
				},
			} );
		}
		this.livePoller.start();
	}

	stopLive() {
		this.livePoller?.stop();
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
		subjects instanceof HTMLElement && ( subjects.hidden = tab !== 'lookup' );

		// The menu marks the tab that's open.
		document.querySelectorAll( '#NavSideBar .sidebar-tools a.tool-item[href]' ).forEach( ( item ) => {
			if ( item instanceof HTMLAnchorElement ) {
				item.classList.toggle( 'active', this.tabForHref( landing, item.href ) === tab );
			}
		} );
	}

	/**
	 * Puts away whatever subject is open beneath the lookup.
	 * @param {Element|null} landing
	 */
	closeSubject( landing ) {
		const shell = landing instanceof HTMLElement ? landing.querySelector( '[data-drill-shell="1"]' ) : null;
		if ( shell instanceof HTMLElement && getActiveLayerIndex( getLayersForShell( shell ) ) > 0 ) {
			shieldAppMain?.components?.drill_down?.drillTo( shell, 0 );
		}
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
		// Some kinds are too many to list, so they wait for something to search for.
		const prompt = String( root.querySelector( `[data-lookup-kind="${kind}"]` )?.getAttribute( 'data-lookup-prompt' ) || '' );
		if ( text.length === 0 && prompt.length > 0 ) {
			intro instanceof HTMLElement && ( intro.hidden = true );
			results?.replaceChildren();
			this.setStatus( root, prompt );
			return;
		}
		// A new search takes the place of whatever was open.
		if ( text.length > 0 ) {
			this.closeSubject( root.closest( InvestigateLookup.LANDING ) );
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
