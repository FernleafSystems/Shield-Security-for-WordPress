import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";

export class DashboardWidget extends BaseComponent {

	init() {
		this.widgetContainer = document.getElementById( 'ShieldDashboardWidget' );
		this.exec();
	}

	canRun() {
		return this.widgetContainer !== null
			&& typeof this._base_data?.ajax?.render === 'object'
			&& typeof this._base_data?.strings?.load_failed === 'string';
	}

	run() {
		this.renderWidget();
	}

	renderWidget() {
		( new AjaxService() )
		.bg( this._base_data.ajax.render )
		.then( ( resp ) => {
			if ( resp?.success && typeof resp?.data?.html === 'string' ) {
				this.widgetContainer.innerHTML = resp.data.html;
				this.widgetContainer.removeAttribute( 'aria-busy' );
			}
			else {
				this.showLoadFailure();
			}
			return resp;
		} )
		.catch( () => this.showLoadFailure() );
	}

	showLoadFailure() {
		this.widgetContainer.removeAttribute( 'aria-busy' );
		this.widgetContainer.textContent = this._base_data.strings.load_failed;
	}
}
