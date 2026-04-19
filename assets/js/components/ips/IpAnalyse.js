import { Tab } from 'bootstrap';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { OffCanvasService } from "../ui/OffCanvasService";
import { UiContentActivator } from "../ui/UiContentActivator";
import { PageQueryParam } from "../../util/PageQueryParam";
import { InvestigateLookupSelect2 } from "../mode/InvestigateLookupSelect2";

export class IpAnalyse extends BaseAutoExecComponent {

	canRun() {
		return shieldServices.container_ShieldPage();
	}

	run() {
		this.lookupSelect2 = new InvestigateLookupSelect2();
		this.runAnalysisOnLoad();

		shieldEventsHandler_Main.add_Click( '.offcanvas_ip_analysis', ( targetEl ) => {
			this.render( targetEl.dataset[ 'ip' ] ).finally();
		} );
		shieldEventsHandler_Main.add_Submit(
			'.offcanvas.offcanvas_ipanalysis form[data-investigate-panel-form="1"]',
			( form, evt ) => this.handleInvestigateLookupSubmit( form, evt ),
			false
		);
		shieldEventsHandler_Main.add_Click(
			'.offcanvas.offcanvas_ipanalysis .investigate-panel__tabs [data-investigate-panel-tab="1"]',
			( tabButton, evt ) => this.handleStandaloneTabClick( tabButton, evt ),
			false
		);
		shieldEventsHandler_Main.addHandler(
			'shown.bs.tab',
			'.offcanvas.offcanvas_ipanalysis .shield-options-rail [data-bs-toggle="tab"]',
			( sourceTab ) => this.handleStandaloneSourceTabShown( sourceTab ),
			false
		);

		shieldEventsHandler_Main.add_Click( '.ip_analyse_action', ( targetEl ) => {
			if ( confirm( 'Are you sure?' ) ) {
				const dataset = targetEl.dataset;
				( new AjaxService() ).send(
					ObjectOps.Merge( this._base_data.ajax.action, {
						ip: dataset[ 'ip' ],
						ip_action: dataset[ 'ip_action' ],
					} )
				).finally();
			}
		} );
	}

	runAnalysisOnLoad() {
		const theIP = String( PageQueryParam.Retrieve( 'analyse_ip' ) || '' ).trim();
		if ( theIP.length < 1 ) {
			return;
		}

		const nav = PageQueryParam.Retrieve( 'nav' );
		const subNav = PageQueryParam.Retrieve( 'nav_sub' );
		const subject = PageQueryParam.Retrieve( 'subject' );
		if (
			( nav === 'activity' && subNav === 'by_ip' )
			|| ( nav === 'activity' && subNav === 'overview' && subject === 'ip' )
		) {
			return;
		}

		this.render( theIP ).finally();
	};

	handleInvestigateLookupSubmit( form, evt ) {
		evt.preventDefault();
		this.render(
			String( ( new FormData( form ) ).get( 'analyse_ip' ) || '' ),
			{
				historyMode: form.dataset.offcanvasHistoryMode || '',
			}
		).finally();
	}

	handleStandaloneTabClick( tabButton, evt ) {
		const targetSelector = String( tabButton.dataset.investigatePanelTarget || '' ).trim();
		if ( !targetSelector.startsWith( '#' ) ) {
			return;
		}

		const scope = tabButton.closest( '.shield-ipanalyse' );
		if ( scope === null ) {
			return;
		}

		const sourceTab = this.findStandaloneSourceTab( scope, targetSelector );
		if ( sourceTab === null ) {
			return;
		}

		evt.preventDefault();
		Tab.getOrCreateInstance( sourceTab ).show();
	}

	handleStandaloneSourceTabShown( sourceTab ) {
		// Offcanvas inline tabs proxy the shared rail tabs so Bootstrap tab state,
		// pane activation, and investigation table initialization stay on the
		// existing IP analysis tab system.
		const targetSelector = String( sourceTab.dataset.bsTarget || sourceTab.getAttribute( 'href' ) || '' ).trim();
		if ( !targetSelector.startsWith( '#' ) ) {
			return;
		}

		const scope = sourceTab.closest( '.shield-ipanalyse' );
		if ( scope === null ) {
			return;
		}
		scope.querySelectorAll( '[data-investigate-panel-tab="1"]' ).forEach( ( candidate ) => {
			const isActive = candidate.dataset.investigatePanelTarget === targetSelector;
			candidate.classList.toggle( 'is-active', isActive );
			candidate.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
		} );

		const targetPane = scope.querySelector( targetSelector );
		if ( targetPane !== null ) {
			UiContentActivator.activateCurrentSubtree( targetPane );
		}
	}

	findStandaloneSourceTab( scope, targetSelector ) {
		return Array.from( scope.querySelectorAll( '.shield-options-rail [data-bs-toggle="tab"]' ) ).find( ( candidate ) => {
			return String( candidate.dataset.bsTarget || candidate.getAttribute( 'href' ) || '' ).trim() === targetSelector;
		} ) || null;
	}

	render( ip = '', options = {} ) {
		return OffCanvasService.RenderCanvas(
			ObjectOps.Merge( this._base_data.ajax.render_offcanvas, { ip: ip } ),
			options
		).then( () => {
			this.lookupSelect2.initializeWithin( OffCanvasService.offCanvasEl );
		} );
	};
}
