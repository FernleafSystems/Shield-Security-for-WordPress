import { BaseService } from "./BaseService";
import { ShieldOverlay } from "./ShieldOverlay";
import { ObjectOps } from "./ObjectOps";
import { AjaxService } from "./AjaxService";

export class DashboardWidget extends BaseService {

	init() {
		this.widgetContainer = document.getElementById( 'ShieldDashboardWidget' ) || false;
		this.exec();
	}

	canRun() {
		return this.widgetContainer;
	}

	run() {
		this.renderWidget();
	}

	renderWidget( refresh = false ) {

		this.widgetContainer.style[ 'min-height' ] = '200px';

		ShieldOverlay.Show( this.widgetContainer.id );

		const data = ObjectOps.ObjClone( this._base_data.ajax.render );
		data[ 'refresh' ] = refresh;

		( new AjaxService() )
		.send( data, false )
		.then( ( resp ) => {
			ShieldOverlay.Hide();
			if ( resp.success ) {
				this.widgetContainer.innerHTML = resp.data.html;
			}
			else {
				this.widgetContainer.textContent = 'There was a problem loading the content.';
			}
			return resp;
		} )
		.then( ( resp ) => {
			if ( resp.success ) {
				this.widgetContainer
					.querySelector( '.refresh_widget' )
					.addEventListener( 'click', () => this.renderWidget( true ) );
			}
		} )
		.catch( ( error ) => {
			this.widgetContainer.textContent = 'There was a problem loading the content.';
			console.log( error );
			ShieldOverlay.Hide();
		} )
		.finally();
	};
}