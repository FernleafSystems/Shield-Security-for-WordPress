import {
	Chart as ChartJS,
	DoughnutController,
	ArcElement
} from 'chart.js';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { OffCanvasService } from "../ui/OffCanvasService";
import { AjaxService } from "../services/AjaxService";
import { AjaxBatchService } from "../services/AjaxBatchService";

ChartJS.register( DoughnutController, ArcElement );

export class ProgressMeters extends BaseAutoExecComponent {

	run() {
		this.activeAnalysis = '';
		this.heroChart = null;
		this.renderMetersAll();
		this.events();
	}

	renderMeters( meterElements ) {
		if ( this._base_data?.ajax?.batch_requests ) {
			return this.renderMetersBatch( meterElements );
		}

		const promises = [];
		meterElements.forEach( ( elem ) => {
			if ( elem.dataset.meter_slug ) {
				promises.push( this.renderMeter( elem, elem.dataset.meter_slug ) );
			}
		} );
		return Promise.all( promises );
	}

	renderMetersBatch( meterElements ) {
		const batch = new AjaxBatchService( this._base_data.ajax.batch_requests );

		meterElements.forEach( ( container ) => {
			const slug = container.dataset.meter_slug || '';
			if ( slug.length < 1 ) {
				return;
			}

			const isHero = container.classList.contains( 'progress-metercard-hero' );
			batch.add( {
				id: this.buildMeterBatchID( slug, isHero ),
				request: this.buildMeterRenderRequest( slug, isHero ),
				onSuccess: ( result ) => this.handleMeterBatchSuccess( container, isHero, result ),
				onError: ( result ) => this.handleMeterBatchFailure( container, result ),
			} );
		} );

		return batch.flush();
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

	buildMeterBatchID( slug, isHero ) {
		return `meter-${slug}${isHero ? '-hero' : ''}`;
	}

	buildMeterRenderRequest( slug, isHero ) {
		return ObjectOps.Merge( this._base_data.ajax.render_metercard, {
			meter_slug: slug,
			is_hero: isHero ? 1 : 0
		} );
	}

	handleMeterBatchSuccess( container, isHero, result ) {
		const html = result?.data?.html || '';
		if ( html.length > 0 ) {
			container.innerHTML = html;
			if ( isHero ) {
				this.renderHeroGauge( container );
			}
		}
		else {
			this.handleMeterBatchFailure( container, result );
		}
	}

	handleMeterBatchFailure( container, result ) {
		container.innerHTML = this.getBatchFailureHtml( result?.data?.message || 'Unable to load this security meter.' );
	}

	getBatchFailureHtml( message ) {
		return `<div class="card h-100"><div class="card-body text-muted small">${this.escapeHtml( message )}</div></div>`;
	}

	escapeHtml( str = '' ) {
		return String( str )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' );
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
			const t = this._base_data.thresholds || { good: 70, warning: 40 };
			mainColor = percentage > t.good ? '#008000' : ( percentage > t.warning ? '#edb41d' : '#c62f3e' );
			bgColor = percentage > t.good ? 'rgba(0,128,0,0.12)' : ( percentage > t.warning ? 'rgba(237,180,29,0.12)' : 'rgba(198,47,62,0.12)' );
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
				if ( progressBar.classList.contains( 'status-good' ) ) {
					good++;
				}
				else if ( progressBar.classList.contains( 'status-warning' ) ) {
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
