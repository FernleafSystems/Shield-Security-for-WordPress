import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";
import { LiveTrafficPoller } from "./LiveTrafficPoller";

export class DashboardLiveMonitor extends BaseComponent {

	init() {
		this.rootEl = document.querySelector( '[data-dashboard-live-monitor="1"]' ) || null;
		this.bodyEl = this.rootEl?.querySelector( '[data-live-monitor-body="1"]' ) || null;
		this.toggleButton = this.rootEl?.querySelector( '[data-live-monitor-toggle="1"]' ) || null;
		this.contentContainer = document.querySelector( '#PageMainBody_Inner-Apto' ) || null;
		this.outputs = {
			ticker: this.rootEl?.querySelector( '[data-live-monitor-output="ticker"]' ) || null,
			traffic: this.rootEl?.querySelector( '[data-live-monitor-output="traffic"]' ) || null,
		};
		this.latestTickerID = 0;
		this.poller = null;
		this.resizeObserver = null;
		this.dockingUpdatePending = false;
		this.boundScheduleDockingUpdate = () => this.scheduleDockingUpdate();
		this.exec();
	}

	canRun() {
		return this.rootEl !== null
			   && this.toggleButton !== null
			   && this.outputs.ticker !== null
			   && this.outputs.traffic !== null;
	}

	run() {
		this.toggleButton.addEventListener( 'click', ( evt ) => {
			evt.preventDefault();
			this.toggleCollapsed();
		} );

		this.poller = new LiveTrafficPoller( {
			requestData: this.buildBatchRequestData(),
			intervalMs: Number.parseInt( this._base_data?.vars?.poll_interval_ms || 5000, 10 ),
			maxPolls: Number.parseInt( this._base_data?.vars?.max_polls || 17280, 10 ),
			shouldPoll: () => !this.isCollapsed() && document.hasFocus(),
			onSuccess: ( resp ) => this.handlePollSuccess( resp ),
			onFailure: ( resp ) => this.handlePollFailure( resp ),
		} );

		if ( !this.isCollapsed() ) {
			this.poller.start();
		}

		this.setupDockingLayout();
	}

	buildBatchRequestData() {
		const batchData = this._base_data?.ajax?.batch_requests || null;
		const tickerData = this._base_data?.ajax?.render_ticker || null;
		const trafficData = this._base_data?.ajax?.render_traffic || null;
		if ( !batchData || !tickerData || !trafficData ) {
			return {};
		}
		return {
			...batchData,
			requests: [
				{
					id: 'ticker',
					request: tickerData,
				},
				{
					id: 'traffic',
					request: trafficData,
				}
			],
		};
	}

	isCollapsed() {
		return this.rootEl?.dataset?.isCollapsed === '1';
	}

	toggleCollapsed() {
		this.applyCollapsedState( !this.isCollapsed() );
		this.persistCollapsedState();
	}

	applyCollapsedState( isCollapsed ) {
		if ( !this.rootEl || !this.toggleButton || !this.bodyEl ) {
			return;
		}

		this.rootEl.dataset.isCollapsed = isCollapsed ? '1' : '0';
		this.rootEl.classList.toggle( 'is-collapsed', isCollapsed );
		this.toggleButton.setAttribute( 'aria-expanded', isCollapsed ? 'false' : 'true' );
		this.bodyEl.setAttribute( 'aria-hidden', isCollapsed ? 'true' : 'false' );

		if ( this.poller ) {
			if ( isCollapsed ) {
				this.poller.stop();
			}
			else {
				this.poller.start();
			}
		}

		this.scheduleDockingUpdate();
	}

	setupDockingLayout() {
		if ( !this.rootEl || !this.contentContainer ) {
			return;
		}

		this.rootEl.classList.add( 'is-docked' );
		this.contentContainer.classList.add( 'has-live-monitor-docked' );

		window.addEventListener( 'resize', this.boundScheduleDockingUpdate );
		window.addEventListener( 'orientationchange', this.boundScheduleDockingUpdate );

		if ( 'ResizeObserver' in window ) {
			this.resizeObserver = new ResizeObserver( this.boundScheduleDockingUpdate );
			this.resizeObserver.observe( this.rootEl );
			this.resizeObserver.observe( this.contentContainer );
		}

		this.scheduleDockingUpdate();
	}

	scheduleDockingUpdate() {
		if ( !this.rootEl || !this.contentContainer || this.dockingUpdatePending ) {
			return;
		}

		this.dockingUpdatePending = true;
		window.requestAnimationFrame( () => {
			this.dockingUpdatePending = false;
			this.updateDockingLayout();
		} );
	}

	updateDockingLayout() {
		if ( !this.rootEl || !this.contentContainer ) {
			return;
		}

		const contentRect = this.contentContainer.getBoundingClientRect();
		const leftOffset = Math.max( Math.round( contentRect.left ), 0 );
		const rightOffset = Math.max( Math.round( window.innerWidth - contentRect.right ), 0 );
		const monitorHeight = Math.max( Math.ceil( this.rootEl.getBoundingClientRect().height ), 0 );

		this.rootEl.style.setProperty( '--dashboard-live-monitor-left', `${leftOffset}px` );
		this.rootEl.style.setProperty( '--dashboard-live-monitor-right', `${rightOffset}px` );
		this.contentContainer.style.setProperty( '--dashboard-live-monitor-clearance', `${monitorHeight}px` );
	}

	persistCollapsedState() {
		const reqData = this._base_data?.ajax?.set_state || null;
		if ( !reqData ) {
			return;
		}

		( new AjaxService() )
		.bg( {
			...reqData,
			is_collapsed: this.isCollapsed() ? 1 : 0,
		} )
		.finally();
	}

	handlePollSuccess( resp ) {
		const results = resp?.data?.results || {};
		this.applyTickerResult( results.ticker || null );
		this.applyTrafficResult( results.traffic || null );
		this.scheduleDockingUpdate();
	}

	applyTickerResult( result ) {
		const output = this.outputs.ticker;
		if ( output === null ) {
			return;
		}

		if ( !result?.success ) {
			this.renderError( output, result?.error || result?.data?.message || '' );
			return;
		}

		if ( typeof result?.data?.html === 'string' ) {
			output.innerHTML = result.data.html;
		}

		const latestID = Number.parseInt( result?.data?.render_data?.vars?.latest_id || '0', 10 );
		if ( latestID > this.latestTickerID && this.latestTickerID > 0 ) {
			output.classList.add( 'is-new' );
			window.setTimeout( () => output.classList.remove( 'is-new' ), 900 );
		}
		this.latestTickerID = Math.max( latestID, this.latestTickerID );
	}

	applyTrafficResult( result ) {
		const output = this.outputs.traffic;
		if ( output === null ) {
			return;
		}

		if ( !result?.success ) {
			this.renderError( output, result?.error || result?.data?.message || '' );
			return;
		}

		if ( typeof result?.data?.html === 'string' ) {
			output.innerHTML = result.data.html;
		}
	}

	handlePollFailure( resp ) {
		const message = resp?.data?.message || resp?.error || '';
		this.renderError( this.outputs.ticker, message );
		this.renderError( this.outputs.traffic, message );
	}

	renderError( output, message = '' ) {
		if ( output === null ) {
			return;
		}
		const safeMessage = this.escapeHtml( message.length > 0 ? message : 'Live monitor update failed.' );
		output.innerHTML = `<div class="text-muted small">${safeMessage}</div>`;
	}

	escapeHtml( text = '' ) {
		return String( text )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' );
	}
}
