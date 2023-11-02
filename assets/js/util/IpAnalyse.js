import { BaseService } from "./BaseService";
import { AjaxService } from "./AjaxService";
import { ObjectOps } from "./ObjectOps";
import { OffCanvasService } from "./OffCanvasService";

export class IpAnalyse extends BaseService {

	init() {
		this.runAnalysisOnLoad();

		shieldServices.container_ShieldPage().addEventListener( 'click', ( evt ) => {
			const t = evt.target;
			if ( 'classList' in t ) {

				if ( t.classList.contains( 'offcanvas_ip_analysis' ) ) {
					evt.preventDefault();
					this.render( t.dataset[ 'ip' ] );
					return false;
				}
				else if ( t.classList.contains( 'ip_analyse_action' ) ) {
					evt.preventDefault();
					if ( confirm( 'Are you sure?' ) ) {
						let params = ObjectOps.ObjClone( this._base_data.ajax.action );
						params.ip = t.dataset[ 'ip' ];
						params.ip_action = t.dataset[ 'ip_action' ];
						( new AjaxService() ).send( params ).finally();
					}
					return false;
				}
			}
		}, false );
	}

	runAnalysisOnLoad() {
		let theIP = ( new URLSearchParams( window.location.search ) ).get( 'analyse_ip' );
		if ( theIP ) {
			this.render( theIP );
		}
	};

	render( ip ) {
		OffCanvasService.RenderCanvas( ObjectOps.Merge( this._base_data.ajax.render_offcanvas, { ip: ip } ) ).finally();
	};
}