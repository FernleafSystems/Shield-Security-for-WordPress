import {
	Chart as ChartJS,
	DoughnutController,
	ArcElement
} from 'chart.js';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { OffCanvasService } from "../ui/OffCanvasService";
import { AjaxService } from "../services/AjaxService";

ChartJS.register( DoughnutController, ArcElement );

export class ProgressMeters extends BaseAutoExecComponent {

	run() {
		this.activeAnalysis = '';
		this.heroChart = null;
		this.renderMetersAll();
		this.events();
	}

	renderMeters( meterElements ) {
		const promises = [];
		meterElements.forEach( ( elem ) => {
			if ( elem.dataset.meter_slug ) {
				promises.push( this.renderMeter( elem, elem.dataset.meter_slug ) );
			}
		} );
		return Promise.all( promises );
	}

	renderMetersAll() {
		return this.renderMeters( document.querySelectorAll( '.progress-metercard' ) )
			.then( () => this.updateStatsBar() );
	}

	renderMeter( container, slug ) {
		const isHero = container.classList.contains( 'progress-metercard-hero' );

		return ( new AjaxService() )
		.bg( ObjectOps.Merge( this._base_data.ajax.render_metercard, {
			meter_slug: slug,
			is_hero: isHero ? 1 : 0
		} ) )
		.then( ( resp ) => {
			container.innerHTML = resp.data.html;
		} )
		.then( () => {
			if ( isHero ) {
				this.renderHeroGauge( container );
			}
		} )
		.catch( ( error ) => {
			console.log( error );
		} )
		.finally();
	}

	renderHeroGauge( container ) {
		if ( this.heroChart ) {
			this.heroChart.destroy();
			this.heroChart = null;
		}

		const canvas = container.querySelector( '.hero-gauge-chart' );
		if ( !canvas ) {
			return;
		}

		const percentage = parseInt( canvas.dataset.percentage, 10 ) || 0;
		const rgbsRaw = canvas.dataset.rgbs || '';
		const rgbParts = rgbsRaw.split( ',' ).map( v => parseInt( v, 10 ) );

		let mainColor, bgColor;
		if ( rgbParts.length >= 3 ) {
			mainColor = 'rgb(' + rgbParts[ 0 ] + ',' + rgbParts[ 1 ] + ',' + rgbParts[ 2 ] + ')';
			bgColor = 'rgba(' + rgbParts[ 0 ] + ',' + rgbParts[ 1 ] + ',' + rgbParts[ 2 ] + ',0.12)';
		}
		else {
			mainColor = percentage > 85 ? '#008000' : ( percentage > 55 ? '#d48a00' : '#c62f3e' );
			bgColor = percentage > 85 ? 'rgba(0,128,0,0.12)' : ( percentage > 55 ? 'rgba(212,138,0,0.12)' : 'rgba(198,47,62,0.12)' );
		}

		this.heroChart = new ChartJS( canvas, {
			type: 'doughnut',
			data: {
				datasets: [ {
					data: [ percentage, 100 - percentage ],
					backgroundColor: [ mainColor, bgColor ],
					borderWidth: 0,
					borderRadius: [ 6, 0 ],
				} ]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				rotation: -90,
				circumference: 180,
				cutout: '75%',
				plugins: {
					legend: { display: false },
					tooltip: { enabled: false },
				},
				animation: {
					animateRotate: true,
					duration: 1200,
					easing: 'easeOutQuart',
				}
			}
		} );
	}

	updateStatsBar() {
		const statsBar = document.querySelector( '.meter-stats-bar' );
		if ( !statsBar ) {
			return;
		}

		let good = 0, warning = 0, critical = 0;

		document.querySelectorAll( '.progress-metercard:not(.progress-metercard-hero)' ).forEach( ( card ) => {
			const progressBar = card.querySelector( '.progress-bar' );
			if ( progressBar ) {
				const widthStr = progressBar.style.width;
				const pct = parseInt( widthStr, 10 ) || 0;
				if ( pct > 85 ) {
					good++;
				}
				else if ( pct > 55 ) {
					warning++;
				}
				else {
					critical++;
				}
			}
		} );

		const goodEl = statsBar.querySelector( '.stat-count-good' );
		const warningEl = statsBar.querySelector( '.stat-count-warning' );
		const criticalEl = statsBar.querySelector( '.stat-count-critical' );

		if ( goodEl ) goodEl.textContent = good;
		if ( warningEl ) warningEl.textContent = warning;
		if ( criticalEl ) criticalEl.textContent = critical;

		statsBar.style.display = '';
	}

	events() {
		shieldEventsHandler_Main.add_Click( 'a.offcanvas_meter_analysis', ( targetEl ) => {
			this.activeAnalysis = targetEl.dataset.meter;
			OffCanvasService.RenderCanvas( ObjectOps.Merge( this._base_data.ajax.render_offcanvas, targetEl.dataset ) )
							.finally();
		} );
		shieldEventsHandler_Main.add_Click( 'div.progress-metercard .description > :not(.alert)', ( targetEl ) => {
			targetEl.querySelectorAll( '.toggleable-text' ).forEach(
				( toggleableElem ) => toggleableElem.classList.toggle( 'hidden' )
			);
		} );
		shieldEventsHandler_Main.addHandler(
			'hidden.bs.offcanvas',
			'.offcanvas.offcanvas_meter_analysis',
			() => this.renderMetersAll()
		);
	}
}
