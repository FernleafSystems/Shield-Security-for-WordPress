import { Tab } from 'bootstrap';
import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { ObjectOps } from "./ObjectOps";

export class Navigation extends BaseService {

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
		let placeholder = document.getElementById( 'ShieldLoadingPlaceholder' ).cloneNode( true );
		placeholder.id = '';
		placeholder.classList.remove( 'd-none' );
		document.querySelector( '#PageMainBody_Inner-Shield' ).innerHTML = placeholder.innerHTML;

		let req = ObjectOps.ObjClone( this._base_data.ajax.dynamic_load );
		req.dynamic_load_params = params;

		( new AjaxService() )
		.send( req, false )
		.then( ( resp ) => this.handleDynamicLoad( resp ) )
		.finally();
	};

	handleDynamicLoad( response ) {
		document.querySelector( '#PageMainBody_Inner-Shield' ).innerHTML = response.data.html;

		let urlHash = window.location.hash ? window.location.hash : '';
		// Using links to specific config sections, we extract the section and trigger the tab show()
		if ( urlHash ) {
			let theTabToShow = document.querySelector( '#tab-navlink-' + urlHash.split( '-' )[ 1 ] );
			if ( theTabToShow ) {
				( new Tab( theTabToShow ) ).show();
			}
		}

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

		let activeLinks = document.querySelectorAll( '#NavSideBar a.nav-link.active' );
		for ( let i = 0; i < activeLinks.length; i++ ) {
			activeLinks[ i ].classList.remove( 'active' );
		}
		this.activeMenuItem.classList.add( 'active' );

		let parentNav = this.activeMenuItem.closest( 'ul' ).closest( 'li.nav-item' );
		if ( parentNav !== null ) {
			parentNav.querySelector( 'li.nav-item > a.nav-link' ).classList.add( 'active' );
		}
	};
}