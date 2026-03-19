import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { UiContentActivator } from "../ui/UiContentActivator";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";
import {
	getActiveLayerIndex,
	getLayerForShell,
	getLayersForShell,
	isDrillShell,
	normalizeDrillStatus,
	normalizeDrillText,
	normalizeLayerHeaderData,
	parseLayerIndex
} from "./DrillDownShared";

export class DrillDownController extends BaseAutoExecComponent {

	canRun() {
		return true;
	}

	run() {
		shieldEventsHandler_Main.add_Click(
			'[data-drill-strip="1"]',
			( strip ) => this.handleStripClick( strip ),
			false
		);
	}

	handleStripClick( strip ) {
		const layer = strip.closest( '[data-drill-layer]' );
		const shell = layer?.closest( '[data-drill-shell="1"]' ) || null;
		if ( layer === null || shell === null ) {
			return;
		}

		this.drillTo( shell, parseLayerIndex( layer.dataset.drillLayer ) );
	}

	/**
	 * Drill a shell to the requested layer index.
	 *
	 * @param {HTMLElement|null} shellEl
	 * @param {number} layerIndex
	 */
	drillTo( shellEl, layerIndex ) {
		if ( !isDrillShell( shellEl ) ) {
			return;
		}

		const layers = getLayersForShell( shellEl );
		if ( layers.length < 1 ) {
			return;
		}

		const targetIndex = this.clampLayerIndex( layerIndex, layers.length );
		const currentActiveIndex = getActiveLayerIndex( layers );
		if ( targetIndex === currentActiveIndex ) {
			return;
		}

		const eventName = targetIndex > currentActiveIndex
			? 'shield:drill-to'
			: 'shield:drill-back';
		const activeLayer = getLayerForShell( shellEl, targetIndex );

		layers.forEach( ( layer ) => {
			const currentLayerIndex = parseLayerIndex( layer.dataset.drillLayer );
			const nextState = currentLayerIndex < targetIndex
				? 'compact'
				: ( currentLayerIndex === targetIndex ? 'active' : 'hidden' );

			if ( nextState !== 'active' ) {
				BootstrapTooltips.DisposeTooltipsWithin( layer );
			}

			this.applyLayerState( layer, nextState );
		} );

		const activeLayerBody = activeLayer?.querySelector( '.drill-layer__body' ) || null;
		if ( activeLayerBody !== null ) {
			UiContentActivator.activateCurrentSubtree( activeLayerBody );
		}

		shellEl.dispatchEvent( new CustomEvent( eventName, {
			bubbles: true,
			detail: {
				mode: shellEl.dataset.drillShellMode || '',
				layer_key: activeLayer?.dataset.drillLayerKey || '',
				layer_index: targetIndex,
			}
		} ) );
	}

	updateLayerHeader( shellEl, layerIndex, headerData ) {
		if ( !isDrillShell( shellEl ) ) {
			return;
		}

		const layer = getLayerForShell( shellEl, layerIndex );
		if ( layer === null ) {
			return;
		}

		const header = normalizeLayerHeaderData( headerData );
		layer.dataset.drillLayerHeader = JSON.stringify( header );
		this.applyLayerHeader( layer, header );
	}

	clampLayerIndex( layerIndex, layerCount ) {
		const parsedIndex = parseLayerIndex( layerIndex );
		if ( layerCount < 1 ) {
			return -1;
		}

		return Math.min( Math.max( parsedIndex, 0 ), layerCount - 1 );
	}

	applyLayerState( layer, state ) {
		layer.classList.add( 'drill-layer' );
		layer.classList.remove( 'drill-layer--compact', 'drill-layer--hidden' );

		if ( state === 'compact' ) {
			layer.classList.add( 'drill-layer--compact' );
		}
		else if ( state === 'hidden' ) {
			layer.classList.add( 'drill-layer--hidden' );
		}
	}

	applyLayerHeader( layer, headerData ) {
		if ( !( layer instanceof HTMLElement ) ) {
			return;
		}

		const header = normalizeLayerHeaderData( headerData );
		const compactBack = layer.querySelector( '[data-drill-layer-compact-back="1"]' );
		const activeBack = layer.querySelector( '[data-drill-layer-active-back="1"]' );
		const scope = layer.querySelector( '[data-drill-layer-header-scope="1"]' );
		const iconWrap = layer.querySelector( '[data-drill-layer-header-icon-wrap="1"]' );
		const icon = layer.querySelector( '[data-drill-layer-header-icon="1"]' );
		const meta = layer.querySelector( '[data-drill-layer-header-meta="1"]' );
		const title = layer.querySelector( '[data-drill-layer-header-title="1"]' );
		const summary = layer.querySelector( '[data-drill-layer-header-summary="1"]' );
		const badge = layer.querySelector( '[data-drill-layer-header-badge="1"]' );

		this.updateBackButton( compactBack, header.compact_back_label );
		this.updateBackButton( activeBack, header.active_back_label );
		this.updateTextBlock( meta, header.meta );
		this.updateTextBlock( title, header.title );
		this.updateTextBlock( summary, header.summary );
		this.updateHeaderBadge( badge, header.badge, header.badge_status );
		this.updateHeaderIcon( iconWrap, icon, header.icon_class );
		this.updateScopeStatus( scope, header.badge_status );
	}

	updateBackButton( button, label ) {
		if ( !( button instanceof HTMLElement ) ) {
			return;
		}

		const normalizedLabel = normalizeDrillText( label );
		const title = button.querySelector( '.drill-strip__title' );
		if ( title instanceof HTMLElement ) {
			title.textContent = normalizedLabel;
		}
		button.setAttribute( 'aria-label', normalizedLabel );
	}

	updateTextBlock( el, text ) {
		if ( !( el instanceof HTMLElement ) ) {
			return;
		}

		const normalizedText = normalizeDrillText( text );
		el.textContent = normalizedText;
		el.classList.toggle( 'd-none', normalizedText.length < 1 );
	}

	updateHeaderBadge( badge, text, status ) {
		if ( !( badge instanceof HTMLElement ) ) {
			return;
		}

		[ ...badge.classList ]
			.filter( ( className ) => className.startsWith( 'badge-' ) )
			.forEach( ( className ) => badge.classList.remove( className ) );

		badge.classList.add( `badge-${normalizeDrillStatus( status )}` );
		const normalizedText = normalizeDrillText( text );
		badge.textContent = normalizedText;
		badge.classList.toggle( 'd-none', normalizedText.length < 1 );
	}

	updateHeaderIcon( iconWrap, icon, iconClass ) {
		if ( !( iconWrap instanceof HTMLElement ) || !( icon instanceof HTMLElement ) ) {
			return;
		}

		const normalizedIconClass = normalizeDrillText( iconClass );
		icon.className = normalizedIconClass;
		iconWrap.classList.toggle( 'd-none', normalizedIconClass.length < 1 );
	}

	updateScopeStatus( scope, status ) {
		if ( !( scope instanceof HTMLElement ) ) {
			return;
		}

		[ ...scope.classList ]
			.filter( ( className ) => className.startsWith( 'status-' ) )
			.forEach( ( className ) => scope.classList.remove( className ) );
		scope.classList.add( `status-${normalizeDrillStatus( status )}` );
	}
}
