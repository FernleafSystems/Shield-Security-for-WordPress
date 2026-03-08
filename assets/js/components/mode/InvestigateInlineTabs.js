import { Tab } from 'bootstrap';

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
			tabButton.textContent = sourceTab.textContent.trim();

			if ( sourceTab.classList.contains( 'active' ) || sourceTab.getAttribute( 'aria-selected' ) === 'true' ) {
				tabButton.classList.add( 'is-active' );
			}

			fragment.appendChild( tabButton );
		} );

		tabsContainer.innerHTML = '';
		tabsContainer.appendChild( fragment );
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

	handleInlineTabClick( tabButton, evt ) {
		const sourceTabID = ( tabButton.dataset.sourceTabId || '' ).trim();
		if ( sourceTabID.length < 1 ) {
			return;
		}

		const scopeEl = this.findScopeRoot( tabButton );
		if ( scopeEl === null ) {
			return;
		}

		const sourceTab = this.collectSourceTabs( scopeEl ).find( ( candidate ) => candidate.id === sourceTabID ) || null;
		if ( sourceTab === null ) {
			return;
		}

		evt.preventDefault();
		Tab.getOrCreateInstance( sourceTab ).show();
	}

	handleSourceTabShown( sourceTab ) {
		const scopeEl = this.findScopeRoot( sourceTab );
		if ( scopeEl === null ) {
			return;
		}
		this.syncInlineActiveTab( scopeEl );
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
			inlineTab.classList.toggle( 'is-active', activeSourceTabID.length > 0 && inlineTab.dataset.sourceTabId === activeSourceTabID );
		} );
	}
}
