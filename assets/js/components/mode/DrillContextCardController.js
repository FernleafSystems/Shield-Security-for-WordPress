import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import {
	getLayerForShell,
	hasRenderableLayerContext,
	isDrillShell,
	normalizeDrillPathSegments,
	normalizeDrillText,
	normalizeLayerContextData
} from "./DrillDownShared";

export class DrillContextCardController extends BaseAutoExecComponent {

	canRun() {
		return true;
	}

	run() {
		document.addEventListener( 'shield:drill-to', ( evt ) => this.handleDrillEvent( evt ) );
		document.addEventListener( 'shield:drill-back', ( evt ) => this.handleDrillEvent( evt ) );
		document.addEventListener( 'shield:drill-context-updated', ( evt ) => this.handleDrillEvent( evt ) );
	}

	handleDrillEvent( evt ) {
		const shell = this.findShellFromEvent( evt );
		if ( !isDrillShell( shell ) ) {
			return;
		}

		const shellId = normalizeDrillText( shell.dataset.drillShellId || '' );
		if ( shellId.length < 1 ) {
			return;
		}

		const card = this.findCardForShellId( shellId );
		if ( !( card instanceof HTMLElement ) ) {
			return;
		}

		const layer = getLayerForShell( shell, evt?.detail?.layer_index );
		const context = this.parseLayerContext( layer );
		if ( !hasRenderableLayerContext( context ) ) {
			this.clearCard( card );
			return;
		}

		this.updateCard( card, context, normalizeDrillText( evt?.detail?.mode || shell.dataset.drillShellMode || '' ) );
	}

	findShellFromEvent( evt ) {
		const target = evt?.target instanceof HTMLElement ? evt.target : null;
		if ( isDrillShell( target ) ) {
			return target;
		}

		return target?.closest( '[data-drill-shell="1"]' ) || null;
	}

	findCardForShellId( shellId ) {
		return Array.from( document.querySelectorAll( '[data-drill-context-card]' ) )
			.find( ( card ) => normalizeDrillText( card.dataset.drillContextCard || '' ) === shellId ) || null;
	}

	parseLayerContext( layerEl ) {
		if ( !( layerEl instanceof HTMLElement ) ) {
			return null;
		}

		const raw = normalizeDrillText( layerEl.dataset.drillLayerContext || '' );
		if ( raw.length < 1 ) {
			return null;
		}

		try {
			const parsed = JSON.parse( raw );
			return parsed && typeof parsed === 'object'
				? normalizeLayerContextData( parsed )
				: null;
		}
		catch ( e ) {
			return null;
		}
	}

	updateCard( cardEl, context, mode ) {
		const pathEl = cardEl.querySelector( '.drill-context-card__path' );
		const focusEl = cardEl.querySelector( '.drill-context-card__focus' );
		const nextStepEl = cardEl.querySelector( '.drill-context-card__next-step' );

		cardEl.classList.remove( 'd-none' );

		const pathSegments = normalizeDrillPathSegments( context.path );
		if ( pathEl instanceof HTMLElement ) {
			pathEl.innerHTML = this.buildPathHtml( pathSegments );
			pathEl.classList.toggle( 'd-none', pathSegments.length < 1 );
		}

		this.updateTextBlock( focusEl, normalizeDrillText( context.focus ) );
		this.updateTextBlock( nextStepEl, normalizeDrillText( context.next_step ) );
		this.syncModeAttribute( cardEl, mode );
	}

	clearCard( cardEl ) {
		const pathEl = cardEl.querySelector( '.drill-context-card__path' );
		const focusEl = cardEl.querySelector( '.drill-context-card__focus' );
		const nextStepEl = cardEl.querySelector( '.drill-context-card__next-step' );

		if ( pathEl instanceof HTMLElement ) {
			pathEl.innerHTML = '';
			pathEl.classList.add( 'd-none' );
		}

		this.updateTextBlock( focusEl, '' );
		this.updateTextBlock( nextStepEl, '' );
		this.syncModeAttribute( cardEl, '' );
		cardEl.classList.add( 'd-none' );
	}

	updateTextBlock( el, text ) {
		if ( !( el instanceof HTMLElement ) ) {
			return;
		}

		el.textContent = text;
		el.classList.toggle( 'd-none', text.length < 1 );
	}

	buildPathHtml( pathSegments ) {
		if ( pathSegments.length < 1 ) {
			return '';
		}

		return pathSegments
			.map( ( segment, index ) => {
				const currentClass = index === pathSegments.length - 1 ? ' is-current' : '';
				return `<span class="drill-context-card__path-segment${currentClass}">${this.escapeHtml( segment )}</span>`;
			} )
			.join( '' );
	}

	syncModeAttribute( cardEl, mode ) {
		if ( !( cardEl instanceof HTMLElement ) ) {
			return;
		}

		const normalizedMode = normalizeDrillText( mode );
		if ( normalizedMode.length > 0 ) {
			cardEl.dataset.drillContextMode = normalizedMode;
		}
		else {
			delete cardEl.dataset.drillContextMode;
		}
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
