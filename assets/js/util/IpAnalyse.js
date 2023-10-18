import $ from 'jquery';
import { BaseService } from "./BaseService";
import { AjaxService } from "./AjaxService";
import { ObjectOps } from "./ObjectOps";
import { OffCanvasService } from "./OffCanvasService";

export class IpAnalyse extends BaseService {

	init() {
		this.runAnalysisOnLoad();

		$( document ).on( 'click', '.offcanvas_ip_analysis', ( evt ) => {
			evt.preventDefault();
			this.render( $( evt.currentTarget ).data( 'ip' ) );
			return false;
		} );

		$( document ).on( 'click', 'a.ip_analyse_action', ( evt ) => {
			evt.preventDefault();
			if ( confirm( 'Are you sure?' ) ) {
				let $thisHref = $( evt.currentTarget );
				let params = ObjectOps.ObjClone( this._base_data.ajax.action );
				params.ip = $thisHref.data( 'ip' );
				params.ip_action = $thisHref.data( 'ip_action' );
				( new AjaxService() ).send( params ).finally();
			}
			return false;
		} );
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