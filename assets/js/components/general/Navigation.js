import { Tab } from 'bootstrap';
import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { ObjectOps } from "../../util/ObjectOps";

export class Navigation extends BaseComponent {

	init() {
		this.navSideBar = document.getElementById( 'NavSideBar' ) || false;
		this.exec();
	}

	canRun() {
		return this.navSideBar;
	}

	run() {
		shieldEventsHandler_Main.add_Click( '#NavSideBar a.dynamic_body_load', ( targetEl ) => {
			this.activeMenuItem = targetEl;
			this.renderFromActiveMenuItem();
		} );

		// Add the active nav-tab to the current URL to support refresh.
		shieldEventsHandler_Main.addHandler(
			'shown.bs.tab',
			'#PageMainBody_Inner-Apto .nav-item > .nav-link',
			( targetEl ) => {
				const d = targetEl.dataset;
				if ( 'bsToggle' in d && targetEl.id ) {
					let href = window.location.href;
					window.history.replaceState(
						{},
						'',
						( href.indexOf( '#' ) > 0 ? href.substring( 0, href.indexOf( '#' ) ) : href ) + '#' + targetEl.id
					);
				}
			}
		);

		this.setActiveNavTab( window.location.hash );

		let activePageLink = document.querySelector( '#NavSideBar a.active.body_content_link.dynamic_body_load' );
		if ( activePageLink ) {
			this.activeMenuItem = activePageLink;
			this.renderFromActiveMenuItem();
		}
	}

	renderFromActiveMenuItem() {
		this.renderDynamicPageLoad( JSON.parse( this.activeMenuItem.dataset[ 'dynamic_page_load' ] ) );
	}

	renderDynamicPageLoad( params ) {
		let placeholder = document.querySelector( '.shield_loading_placeholder_config' ).cloneNode( true );
		placeholder.id = '';
		placeholder.classList.remove( 'd-none' );
		document.querySelector( '#PageMainBody_Inner-Apto' ).innerHTML = placeholder.innerHTML;

		let req = ObjectOps.ObjClone( this._base_data.ajax.dynamic_load );
		req.dynamic_load_params = params;

		( new AjaxService() )
		.send( req, false )
		.then( ( resp ) => this.handleDynamicLoad( resp ) )
		.finally();
	};

	setActiveNavTab( urlHash = null ) {
		if ( urlHash ) {
			let theTabToShow = document.querySelector( urlHash );
			if ( theTabToShow ) {
				( new Tab( theTabToShow ) ).show();
			}
		}
	};

	handleDynamicLoad( response ) {
		document.querySelector( '#PageMainBody_Inner-Apto' ).innerHTML = response.data.html;

		const urlHash = window.location.hash ? window.location.hash : '';
		this.setActiveNavTab( '#tab-navlink-' + urlHash.split( '-' )[ 1 ] );

		window.scroll( { top: 0, left: 0, behavior: 'smooth' } );

		/**
		 *  we then update the window URL (only after triggering tabs)
		 *  We need to take into account the window's hash link. We do this by checking
		 *  for its existence on-page and only add it back to the URL if it's applicable.
		 */
		let replaceStateUrl = response.data.page_url;
		let hashOnPageElement = document.getElementById( urlHash.replace( /#/, '' ) );
		if ( hashOnPageElement ) {
			replaceStateUrl += urlHash;
		}
		window.history.replaceState(
			{},
			response.data.page_title,
			replaceStateUrl
		);

		let activeLinks = document.querySelectorAll( '#NavSideBar .nav-link.active' );
		for ( let i = 0; i < activeLinks.length; i++ ) {
			activeLinks[ i ].classList.remove( 'active' );
		}
		this.activeMenuItem.closest( '.nav-link' ).classList.add( 'active' );

		let parentNav = this.activeMenuItem.closest( 'ul' ).closest( 'li.nav-item' );
		if ( parentNav !== null ) {
			parentNav.querySelector( 'li.nav-item > .nav-link' ).classList.add( 'active' );
		}
	};
}