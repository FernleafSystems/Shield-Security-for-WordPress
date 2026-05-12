import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { UiContentActivator } from "../ui/UiContentActivator";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";
import { announceWithin, focusElement } from "../ui/ShieldA11y";
import {
	getActiveLayerIndex,
	getLayerForShell,
	getLayersForShell,
	isDrillShell,
	parseLayerIndex
} from "./DrillDownShared";

export class DrillDownController extends BaseAutoExecComponent {

	layerActivators = new WeakMap();

	canRun() {
		return true;
	}

	run() {}

	/**
	 * Drill a shell to the requested layer index.
	 *
	 * @param {HTMLElement|null} shellEl
	 * @param {number} layerIndex
	 * @param {{sourceEl?: Element|null}=} options
	 */
	drillTo( shellEl, layerIndex, options = {} ) {
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

		const isForward = targetIndex > currentActiveIndex;
		const eventName = targetIndex > currentActiveIndex
			? 'shield:drill-to'
			: 'shield:drill-back';
		const activeLayer = getLayerForShell( shellEl, targetIndex );
		const sourceEl = options?.sourceEl instanceof Element ? options.sourceEl : null;

		if ( isForward ) {
			this.rememberLayerActivator( shellEl, targetIndex, sourceEl );
		}

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

		this.focusForLayerChange( shellEl, activeLayer, currentActiveIndex, isForward );
		this.announceActiveLayer( shellEl, activeLayer, null, {
			politeness: 'polite',
			allowRepeat: false,
		} );

		shellEl.dispatchEvent( new CustomEvent( eventName, {
			bubbles: true,
			detail: {
				mode: shellEl.dataset.drillShellMode || '',
				layer_key: activeLayer?.dataset.drillLayerKey || '',
				layer_index: targetIndex,
			}
		} ) );
	}

	updateLayerHeader( shellEl, layerIndex, headerData, options = {} ) {
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
		const previousTitle = this.readLayerTitle( layer );
		layer.dataset.drillLayerHeader = JSON.stringify( header );
		this.syncLayerTitle( layer, header );
		const nextTitle = this.readLayerTitle( layer, header );
		if ( this.isLayerActive( layer )
			&& this.shouldAnnounceHeaderUpdate( options, previousTitle, nextTitle ) ) {
			this.announceActiveLayer( shellEl, layer, header, {
				politeness: 'polite',
				allowRepeat: false,
			} );
		}
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

		layer.setAttribute( 'aria-hidden', state === 'active' ? 'false' : 'true' );
	}

	rememberLayerActivator( shellEl, targetIndex, sourceEl ) {
		if ( !( sourceEl instanceof HTMLElement )
			|| !sourceEl.isConnected
			|| sourceEl.closest( '[data-drill-shell="1"]' ) !== shellEl ) {
			return;
		}

		let activators = this.layerActivators.get( shellEl );
		if ( !( activators instanceof Map ) ) {
			activators = new Map();
			this.layerActivators.set( shellEl, activators );
		}
		activators.set( targetIndex, sourceEl );
	}

	focusForLayerChange( shellEl, activeLayer, previousIndex, isForward ) {
		if ( !( activeLayer instanceof HTMLElement ) ) {
			return;
		}

		if ( isForward ) {
			focusElement( activeLayer );
			return;
		}

		const activator = this.layerActivators.get( shellEl )?.get( previousIndex ) || null;
		if ( !focusElement( activator ) ) {
			focusElement( activeLayer );
		}
	}

	announceActiveLayer( shellEl, layer, header = null, options = {} ) {
		const title = this.readLayerTitle( layer, header );
		if ( title.length > 0 ) {
			announceWithin( shellEl, title, options );
		}
	}

	shouldAnnounceHeaderUpdate( options, previousTitle, nextTitle ) {
		if ( options?.announce === false || nextTitle.length < 1 ) {
			return false;
		}

		if ( options?.announce === 'always' ) {
			return true;
		}

		return nextTitle !== previousTitle;
	}

	syncLayerTitle( layer, header ) {
		const title = this.readHeaderTitle( header );
		if ( title.length < 1 ) {
			return;
		}

		const titleEl = layer.querySelector( '[data-drill-layer-title="1"]' );
		if ( titleEl instanceof HTMLElement ) {
			titleEl.textContent = title;
		}
	}

	readLayerTitle( layer, header = null ) {
		const headerTitle = this.readHeaderTitle( header );
		if ( headerTitle.length > 0 ) {
			return headerTitle;
		}

		const titleEl = layer instanceof HTMLElement
			? layer.querySelector( '[data-drill-layer-title="1"]' )
			: null;
		return titleEl instanceof HTMLElement
			? String( titleEl.textContent || '' ).trim()
			: '';
	}

	readHeaderTitle( header ) {
		return header && typeof header === 'object'
			? String( header.title || '' ).trim()
			: '';
	}

	isLayerActive( layer ) {
		return layer instanceof HTMLElement
			&& !layer.classList.contains( 'drill-layer--compact' )
			&& !layer.classList.contains( 'drill-layer--hidden' );
	}
}
