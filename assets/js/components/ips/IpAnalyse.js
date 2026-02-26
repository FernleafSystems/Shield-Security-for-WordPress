import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { OffCanvasService } from "../ui/OffCanvasService";
import { PageQueryParam } from "../../util/PageQueryParam";

export class IpAnalyse extends BaseAutoExecComponent {

	canRun() {
		return shieldServices.container_ShieldPage();
	}

	run() {
		this.runAnalysisOnLoad();

		shieldEventsHandler_Main.add_Click( '.offcanvas_ip_analysis', ( targetEl ) => {
			this.render( targetEl.dataset[ 'ip' ] );
		} );

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
			this.render( theIP );
		}
	};

	render( ip ) {
		OffCanvasService.RenderCanvas( ObjectOps.Merge( this._base_data.ajax.render_offcanvas, { ip: ip } ) ).finally();
	};
}
