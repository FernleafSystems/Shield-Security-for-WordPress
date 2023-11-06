import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { ShieldOverlay } from "../ui/ShieldOverlay";

export class DashboardWidget extends BaseComponent {

	init() {
		this.widgetContainer = document.getElementById( 'ShieldDashboardWidget' ) || false;
		this.exec();
	}

	canRun() {
		return this.widgetContainer;
	}

	run() {
		shieldEventsHandler_Main.add_Click( `#${this.widgetContainer.id} a.refresh_widget`, () => this.renderWidget( true ) );
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
		.catch( ( error ) => {
			this.widgetContainer.textContent = 'There was a problem loading the content.';
			console.log( error );
			ShieldOverlay.Hide();
		} )
		.finally();
	};
}