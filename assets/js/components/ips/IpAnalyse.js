import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { OffCanvasService } from "../ui/OffCanvasService";
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
		const nav = PageQueryParam.Retrieve( 'nav' );
		const subNav = PageQueryParam.Retrieve( 'nav_sub' );
		if ( nav === 'activity' && subNav === 'by_ip' ) {
			return;
		}

		let theIP = PageQueryParam.Retrieve( 'analyse_ip' );
		if ( theIP ) {
			this.render( theIP ).finally();
		}
	};

	handleInvestigateLookupSubmit( form, evt ) {
		evt.preventDefault();
		this.render( String( ( new FormData( form ) ).get( 'analyse_ip' ) || '' ) ).finally();
	}

	render( ip = '' ) {
		return OffCanvasService.RenderCanvas(
			ObjectOps.Merge( this._base_data.ajax.render_offcanvas, { ip: ip } )
		).then( () => {
			this.lookupSelect2.initializeWithin( OffCanvasService.offCanvasEl );
		} );
	};
}
