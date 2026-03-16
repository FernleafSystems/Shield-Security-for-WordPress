import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { UiContentActivator } from "../ui/UiContentActivator";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";
import {
	getActiveLayerIndex,
	getLayerForShell,
	getLayersForShell,
	isDrillShell,
	normalizeDrillText,
	normalizeLayerContextData,
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

	/**
	 * Update the strip badge for a shell layer.
	 *
	 * @param {HTMLElement|null} shellEl
	 * @param {number} layerIndex
	 * @param {string|number} text
	 * @param {string} statusClass
	 */
	updateStripBadge( shellEl, layerIndex, text, statusClass ) {
		if ( !isDrillShell( shellEl ) ) {
			return;
		}

		const layer = getLayerForShell( shellEl, layerIndex );
		const badge = layer?.querySelector( '[data-drill-strip="1"] .shield-badge' ) || null;
		if ( badge === null ) {
			return;
		}

		[ ...badge.classList ]
			.filter( ( className ) => className.startsWith( 'badge-' ) )
			.forEach( ( className ) => badge.classList.remove( className ) );

		const nextStatusClass = String( statusClass || '' ).trim();
		if ( nextStatusClass.length > 0 ) {
			badge.classList.add( `badge-${nextStatusClass}` );
		}

		badge.textContent = String( text ?? '' );
	}

	updateStripText( shellEl, layerIndex, text ) {
		if ( !isDrillShell( shellEl ) ) {
			return;
		}

		const layer = getLayerForShell( shellEl, layerIndex );
		const strip = layer?.querySelector( '[data-drill-strip="1"]' ) || null;
		const title = layer?.querySelector( '[data-drill-strip="1"] .drill-strip__title' ) || null;
		if ( title === null ) {
			return;
		}

		const normalizedText = normalizeDrillText( text );
		title.textContent = normalizedText;

		if ( strip instanceof HTMLElement ) {
			const ariaPrefix = String( strip.dataset.drillStripAriaPrefix || '' ).trim();
			strip.setAttribute(
				'aria-label',
				[ ariaPrefix, normalizedText ]
					.filter( ( value ) => value.length > 0 )
					.join( ' ' )
			);
		}
	}

	updateLayerContext( shellEl, layerIndex, contextData ) {
		if ( !isDrillShell( shellEl ) ) {
			return;
		}

		const layer = getLayerForShell( shellEl, layerIndex );
		if ( layer === null ) {
			return;
		}

		const normalizedContext = normalizeLayerContextData( contextData );
		layer.dataset.drillLayerContext = JSON.stringify( normalizedContext );

		const layers = getLayersForShell( shellEl );
		if ( getActiveLayerIndex( layers ) !== parseLayerIndex( layerIndex ) ) {
			return;
		}

		shellEl.dispatchEvent( new CustomEvent( 'shield:drill-context-updated', {
			bubbles: true,
			detail: {
				mode: shellEl.dataset.drillShellMode || '',
				layer_key: layer.dataset.drillLayerKey || '',
				layer_index: parseLayerIndex( layerIndex ),
				context: normalizedContext,
			}
		} ) );
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
}
