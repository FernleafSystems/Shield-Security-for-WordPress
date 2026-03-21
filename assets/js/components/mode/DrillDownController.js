import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { UiContentActivator } from "../ui/UiContentActivator";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";
import {
	getActiveLayerIndex,
	getLayerForShell,
	getLayersForShell,
	isDrillShell,
	parseLayerIndex
} from "./DrillDownShared";

export class DrillDownController extends BaseAutoExecComponent {

	canRun() {
		return true;
	}

	run() {}

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

		const header = headerData && typeof headerData === 'object'
			? headerData
			: {};
		layer.dataset.drillLayerHeader = JSON.stringify( header );
		shellEl.dispatchEvent( new CustomEvent( 'shield:drill-header-updated', {
			bubbles: true,
			detail: {
				mode: shellEl.dataset.drillShellMode || '',
				layer_key: layer.dataset.drillLayerKey || '',
				layer_index: parseLayerIndex( layer.dataset.drillLayer ),
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
