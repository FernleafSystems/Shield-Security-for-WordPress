import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { ShieldOverlay } from "../ui/ShieldOverlay";
import { renderHeroGauge } from "../meters/HeroGaugeRenderer";

export class DashboardWidget extends BaseComponent {

	init() {
		this.widgetContainer = document.getElementById( 'ShieldDashboardWidget' ) || false;
		this.heroChart = null;
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
		this.destroyHeroGauge();

		ShieldOverlay.Show( this.widgetContainer.id );

		const data = ObjectOps.ObjClone( this._base_data.ajax.render );
		data[ 'refresh' ] = refresh;

		( new AjaxService() )
		.bg( data, false, true )
		.then( ( resp ) => {
			ShieldOverlay.Hide();
			if ( resp.success ) {
				this.widgetContainer.innerHTML = resp.data.html;
				this.renderHeroGauge();
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

	renderHeroGauge() {
		const canvas = this.widgetContainer.querySelector( '.hero-gauge-chart' );
		if ( !canvas ) {
			return;
		}

		this.heroChart = renderHeroGauge( canvas, {
			percentage: canvas.dataset.percentage,
			rgbs: canvas.dataset.rgbs || '',
			thresholds: this._base_data.thresholds || { good: 70, warning: 40 },
		} );
	}

	destroyHeroGauge() {
		if ( this.heroChart ) {
			this.heroChart.destroy();
			this.heroChart = null;
		}
	}
}
