import { Tab } from 'bootstrap';
import { UiContentActivator } from "../ui/UiContentActivator";

export class InvestigateInlineTabs {

	static hasBoundHandlers = false;

	initializeWithin( contextEl ) {
		if ( contextEl === null ) {
			return;
		}

		this.bindHandlers();
		this.collectScopes( contextEl ).forEach( ( scopeEl ) => {
			this.rebuildInlineTabs( scopeEl );
		} );
	}

	bindHandlers() {
		if ( InvestigateInlineTabs.hasBoundHandlers ) {
			return;
		}
		InvestigateInlineTabs.hasBoundHandlers = true;

		shieldEventsHandler_Main.add_Click(
			'[data-investigate-panel-tab="1"]',
			( tabButton, evt ) => this.handleInlineTabClick( tabButton, evt ),
			false
		);
		shieldEventsHandler_Main.addHandler(
			'keydown',
			'[data-investigate-panel-tab="1"]',
			( tabButton, evt ) => this.handleInlineTabKeydown( tabButton, evt ),
			false
		);
		shieldEventsHandler_Main.addHandler(
			'shown.bs.tab',
			'[data-investigate-source-tab="1"]',
			( sourceTab ) => this.handleSourceTabShown( sourceTab ),
			false
		);
	}

	collectScopes( contextEl ) {
		const scopes = [];

		if ( this.isPanelScope( contextEl ) ) {
			scopes.push( contextEl );
		}
		else if ( this.isStandaloneScope( contextEl ) ) {
			scopes.push( contextEl );
		}

		contextEl.querySelectorAll( '[data-investigate-panel]' ).forEach( ( panel ) => {
			scopes.push( panel );
		} );
		contextEl.querySelectorAll( '.investigate-inline-ipanalyse' ).forEach( ( scopeEl ) => {
			if ( this.findScopeRoot( scopeEl ) === scopeEl ) {
				scopes.push( scopeEl );
			}
		} );

		return Array.from( new Set( scopes ) );
	}

	isPanelScope( scopeEl ) {
		return scopeEl instanceof Element && scopeEl.matches( '[data-investigate-panel]' );
	}

	isStandaloneScope( scopeEl ) {
		return scopeEl instanceof Element
			&& scopeEl.matches( '.investigate-inline-ipanalyse' )
			&& scopeEl.closest( '[data-investigate-panel]' ) === null;
	}

	findScopeRoot( el ) {
		return el.closest( '[data-investigate-panel]' )
			|| el.closest( '.investigate-inline-ipanalyse' );
	}

	getScopeContentContainer( scopeEl ) {
		return this.isPanelScope( scopeEl )
			? ( scopeEl.querySelector( '[data-investigate-panel-content="1"]' )
				|| scopeEl.querySelector( '[data-mode-panel-body]' ) )
			: scopeEl;
	}

	getTabsContainer( scopeEl ) {
		return scopeEl.querySelector( '[data-investigate-panel-tabs="1"]' );
	}

	clearInlineTabs( scopeEl ) {
		const tabsContainer = this.getTabsContainer( scopeEl );
		if ( tabsContainer !== null ) {
			tabsContainer.innerHTML = '';
			tabsContainer.removeAttribute( 'role' );
			tabsContainer.removeAttribute( 'aria-orientation' );
		}
	}

	rebuildInlineTabs( scopeEl ) {
		const tabsContainer = this.getTabsContainer( scopeEl );
		if ( tabsContainer === null ) {
			return;
		}

		const sourceTabs = this.collectSourceTabs( scopeEl );
		if ( sourceTabs.length < 1 ) {
			this.clearInlineTabs( scopeEl );
			return;
		}

		tabsContainer.setAttribute( 'role', 'tablist' );
		tabsContainer.setAttribute( 'aria-orientation', 'horizontal' );

		const fragment = document.createDocumentFragment();
		sourceTabs.forEach( ( sourceTab, index ) => {
			if ( sourceTab.id.length < 1 ) {
				sourceTab.id = this.buildSourceTabID( scopeEl, index );
			}

			const targetSelector = this.getTabTargetSelector( sourceTab );
			if ( targetSelector.length < 1 ) {
				return;
			}

			const tabButton = document.createElement( 'button' );
			tabButton.type = 'button';
			tabButton.className = 'investigate-panel__tab';
			tabButton.dataset.investigatePanelTab = '1';
			tabButton.dataset.sourceTabId = sourceTab.id;
			tabButton.id = this.buildInlineTabID( sourceTab );
			tabButton.setAttribute( 'role', 'tab' );
			tabButton.setAttribute( 'aria-controls', targetSelector.substring( 1 ) );
			tabButton.textContent = sourceTab.textContent.trim();

			if ( sourceTab.classList.contains( 'active' ) || sourceTab.getAttribute( 'aria-selected' ) === 'true' ) {
				tabButton.classList.add( 'is-active' );
				tabButton.classList.add( 'active' );
			}

			fragment.appendChild( tabButton );
		} );

		tabsContainer.innerHTML = '';
		tabsContainer.appendChild( fragment );
		this.syncPaneLabelsToInlineTabs( scopeEl );
		this.syncInlineActiveTab( scopeEl );
	}

	collectSourceTabs( scopeEl ) {
		const contentContainer = this.getScopeContentContainer( scopeEl );
		if ( contentContainer === null ) {
			return [];
		}

		return Array.from( contentContainer.querySelectorAll( '.shield-options-rail [data-bs-toggle="tab"]' ) ).filter( ( sourceTab ) => {
			const targetSelector = this.getTabTargetSelector( sourceTab );
			if ( targetSelector.length < 1 || contentContainer.querySelector( targetSelector ) === null ) {
				delete sourceTab.dataset.investigateSourceTab;
				return false;
			}

			sourceTab.dataset.investigateSourceTab = '1';
			return true;
		} );
	}

	getTabTargetSelector( sourceTab ) {
		const targetSelector = ( sourceTab.dataset.bsTarget || sourceTab.getAttribute( 'href' ) || '' ).trim();
		return targetSelector.startsWith( '#' ) ? targetSelector : '';
	}

	buildSourceTabID( scopeEl, index ) {
		const scopeKey = this.isPanelScope( scopeEl )
			? ( scopeEl.dataset.investigatePanel || 'panel' )
			: 'offcanvas-ipanalysis';
		return `investigate-inline-source-tab-${scopeKey.toLowerCase().replace( /[^a-z0-9_-]/g, '-' )}-${index}`;
	}

	buildInlineTabID( sourceTab ) {
		return `${sourceTab.id}-inline-proxy`;
	}

	handleInlineTabClick( tabButton, evt ) {
		const sourceTab = this.findSourceTabForInlineTab( tabButton );
		if ( sourceTab === null ) {
			return;
		}

		evt.preventDefault();
		Tab.getOrCreateInstance( sourceTab ).show();
	}

	handleInlineTabKeydown( tabButton, evt ) {
		if ( !( evt instanceof KeyboardEvent ) ) {
			return;
		}

		const keyMap = {
			ArrowRight: 1,
			ArrowDown: 1,
			ArrowLeft: -1,
			ArrowUp: -1,
		};
		const inlineTabs = this.collectInlineTabs( tabButton );
		if ( inlineTabs.length < 1 ) {
			return;
		}

		const currentIndex = inlineTabs.indexOf( tabButton );
		let nextIndex = null;
		if ( keyMap[ evt.key ] !== undefined ) {
			nextIndex = ( currentIndex + keyMap[ evt.key ] + inlineTabs.length ) % inlineTabs.length;
		}
		else if ( evt.key === 'Home' ) {
			nextIndex = 0;
		}
		else if ( evt.key === 'End' ) {
			nextIndex = inlineTabs.length - 1;
		}

		if ( nextIndex === null ) {
			return;
		}

		evt.preventDefault();
		const nextTab = inlineTabs[ nextIndex ];
		const sourceTab = this.findSourceTabForInlineTab( nextTab );
		if ( sourceTab === null ) {
			return;
		}

		nextTab.focus();
		Tab.getOrCreateInstance( sourceTab ).show();
	}

	handleSourceTabShown( sourceTab ) {
		const scopeEl = this.findScopeRoot( sourceTab );
		if ( scopeEl === null ) {
			return;
		}
		this.activateShownSourcePane( scopeEl, sourceTab );
		this.syncPaneLabelsToInlineTabs( scopeEl );
		this.syncInlineActiveTab( scopeEl );
	}

	activateShownSourcePane( scopeEl, sourceTab ) {
		const contentContainer = this.getScopeContentContainer( scopeEl );
		if ( contentContainer === null ) {
			return;
		}

		const targetSelector = this.getTabTargetSelector( sourceTab );
		if ( targetSelector.length < 1 ) {
			return;
		}

		const targetPane = contentContainer.querySelector( targetSelector );
		if ( targetPane !== null ) {
			UiContentActivator.activateCurrentSubtree( targetPane );
		}
	}

	findSourceTabForInlineTab( tabButton ) {
		const sourceTabID = ( tabButton.dataset.sourceTabId || '' ).trim();
		if ( sourceTabID.length < 1 ) {
			return null;
		}

		const scopeEl = this.findScopeRoot( tabButton );
		if ( scopeEl === null ) {
			return null;
		}

		return this.collectSourceTabs( scopeEl ).find( ( candidate ) => candidate.id === sourceTabID ) || null;
	}

	collectInlineTabs( tabButton ) {
		const tabsContainer = tabButton.closest( '[data-investigate-panel-tabs="1"]' );
		if ( tabsContainer === null ) {
			return [];
		}

		return Array.from( tabsContainer.querySelectorAll( '[data-investigate-panel-tab="1"]' ) );
	}

	connectPaneToInlineTab( scopeEl, targetSelector, inlineTabID ) {
		const contentContainer = this.getScopeContentContainer( scopeEl );
		if ( contentContainer === null || !targetSelector.startsWith( '#' ) || inlineTabID.length < 1 ) {
			return;
		}

		const targetPane = contentContainer.querySelector( targetSelector );
		if ( targetPane !== null ) {
			targetPane.setAttribute( 'aria-labelledby', inlineTabID );
		}
	}

	syncPaneLabelsToInlineTabs( scopeEl ) {
		const tabsContainer = this.getTabsContainer( scopeEl );
		if ( tabsContainer === null ) {
			return;
		}

		const sourceTabs = this.collectSourceTabs( scopeEl );
		tabsContainer.querySelectorAll( '[data-investigate-panel-tab="1"]' ).forEach( ( inlineTab ) => {
			const sourceTab = sourceTabs.find( ( candidate ) => candidate.id === inlineTab.dataset.sourceTabId ) || null;
			if ( sourceTab === null ) {
				return;
			}

			this.connectPaneToInlineTab( scopeEl, this.getTabTargetSelector( sourceTab ), inlineTab.id );
		} );
	}

	syncInlineActiveTab( scopeEl ) {
		const tabsContainer = this.getTabsContainer( scopeEl );
		if ( tabsContainer === null ) {
			return;
		}

		const contentContainer = this.getScopeContentContainer( scopeEl );
		if ( contentContainer === null ) {
			return;
		}

		const activeSourceTab = contentContainer.querySelector( '[data-investigate-source-tab="1"].active' )
			|| contentContainer.querySelector( '[data-investigate-source-tab="1"][aria-selected="true"]' );
		const activeSourceTabID = activeSourceTab !== null ? activeSourceTab.id : '';
		tabsContainer.querySelectorAll( '[data-investigate-panel-tab="1"]' ).forEach( ( inlineTab ) => {
			const isActive = activeSourceTabID.length > 0 && inlineTab.dataset.sourceTabId === activeSourceTabID;
			inlineTab.classList.toggle( 'is-active', isActive );
			inlineTab.classList.toggle( 'active', isActive );
			inlineTab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			inlineTab.setAttribute( 'tabindex', isActive ? '0' : '-1' );
		} );
	}
}
