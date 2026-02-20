import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { OffCanvasService } from "../ui/OffCanvasService";
import { AjaxService } from "../services/AjaxService";
import { AjaxBatchService } from "../services/AjaxBatchService";
import { renderHeroGauge } from "./HeroGaugeRenderer";

export class ProgressMeters extends BaseAutoExecComponent {

	run() {
		this.activeAnalysis = '';
		this.heroCharts = new Map();
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
				promises.push( this.renderMeter(
					elem,
					elem.dataset.meter_slug,
					elem.dataset.meter_channel || ''
				) );
			}
		} );
		return Promise.all( promises );
	}

	renderMetersBatch( meterElements ) {
		const batch = new AjaxBatchService( this._base_data.ajax.batch_requests );

		meterElements.forEach( ( container, index ) => {
			const slug = container.dataset.meter_slug || '';
			const meterChannel = container.dataset.meter_channel || '';
			if ( slug.length < 1 ) {
				return;
			}

			const isHero = container.classList.contains( 'progress-metercard-hero' );
			batch.add( {
				id: this.buildMeterBatchID( slug, isHero, meterChannel, index ),
				request: this.buildMeterRenderRequest( slug, isHero, meterChannel ),
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

	renderMeter( container, slug, meterChannel = '' ) {
		const isHero = container.classList.contains( 'progress-metercard-hero' );

		return ( new AjaxService() )
		.bg( this.buildMeterRenderRequest( slug, isHero, meterChannel ) )
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

	buildMeterBatchID( slug, isHero, meterChannel = '', index = 0 ) {
		return `meter-${slug}${isHero ? '-hero' : ''}${meterChannel ? `-${meterChannel}` : ''}-${index}`;
	}

	buildMeterRenderRequest( slug, isHero, meterChannel = '' ) {
		const request = ObjectOps.Merge( this._base_data.ajax.render_metercard, {
			meter_slug: slug,
			is_hero: isHero ? 1 : 0
		} );
		if ( meterChannel.length > 0 ) {
			request.meter_channel = meterChannel;
		}
		return request;
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
		this.destroyHeroGauge( container );

		const canvas = container.querySelector( '.hero-gauge-chart' );
		if ( !canvas ) {
			return;
		}

		const chart = renderHeroGauge( canvas, {
			percentage: canvas.dataset.percentage,
			rgbs: canvas.dataset.rgbs || '',
			thresholds: this._base_data.thresholds || { good: 70, warning: 40 },
		} );
		if ( chart ) {
			this.heroCharts.set( container, chart );
		}
	}

	destroyHeroGauge( container ) {
		const chart = this.heroCharts.get( container );
		if ( chart ) {
			chart.destroy();
			this.heroCharts.delete( container );
		}
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
		document.addEventListener( 'shield:dashboard-view-changed', () => this.refreshVisibleHeroGauges() );
	}

	refreshVisibleHeroGauges() {
		document.querySelectorAll( '.dashboard-overview-panel.is-active .progress-metercard-hero' ).forEach( ( container ) => {
			const chart = this.heroCharts.get( container );
			if ( chart ) {
				chart.resize();
				chart.update( 'none' );
			}
			else {
				this.renderHeroGauge( container );
			}
		} );
	}
}
