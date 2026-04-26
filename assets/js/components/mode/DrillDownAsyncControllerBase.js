import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { UiContentActivator } from "../ui/UiContentActivator";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";
import { announceWithin, setElementBusy } from "../ui/ShieldA11y";
import {
	getLayersForShell,
	parseJsonAttribute,
	parseLayerIndex,
	updateOperatorRootStep
} from "./DrillDownShared";

export class DrillDownAsyncControllerBase extends BaseAutoExecComponent {

	layerRequests = {};
	rootEl = null;
	shellEl = null;

	initializeCurrentRoot() {
		this.rootEl = this.getRoot();
		this.shellEl = this.getShell( this.rootEl );
	}

	getRoot() {
		return null;
	}

	getShell( root = this.rootEl ) {
		return root?.querySelector( '[data-drill-shell="1"]' ) || null;
	}

	getLayerByKey( shell, layerKey ) {
		return getLayersForShell( shell )
			.find( ( layer ) => String( layer.dataset.drillLayerKey || '' ).trim() === layerKey ) || null;
	}

	getLayerIndexByKey( shell, layerKey ) {
		const layer = this.getLayerByKey( shell, layerKey );
		return layer === null ? -1 : parseLayerIndex( layer.dataset.drillLayer );
	}

	getDrillDownController() {
		return window.shieldAppMain?.components?.drill_down || null;
	}

	cancelLayerRequest( layerKey ) {
		const normalizedLayerKey = String( layerKey || '' ).trim();
		if ( normalizedLayerKey.length < 1 ) {
			return;
		}

		if ( this.layerRequests[ normalizedLayerKey ] !== undefined ) {
			delete this.layerRequests[ normalizedLayerKey ];
		}

		const layer = this.getLayerByKey( this.shellEl, normalizedLayerKey );
		if ( layer !== null ) {
			this.setLayerBusy( layer, false );
		}
	}

	buildRenderAction( source, extraData ) {
		const action = this.parseJsonDataset( source );
		if ( ObjectOps.IsEmpty( action ) ) {
			return {};
		}

		return {
			...action,
			...extraData,
		};
	}

	buildLoadingHeader( header, loadingText ) {
		return {
			...( header && typeof header === 'object' ? header : {} ),
			summary: String( loadingText || '' ).trim(),
		};
	}

	buildLoadingMarkup( message ) {
		void message;
		return '';
	}

	loadLayerContent( layerKey, renderAction, showPlaceholder, loadingText, onSuccess ) {
		if ( this.shellEl === null || ObjectOps.IsEmpty( renderAction ) ) {
			return Promise.resolve( null );
		}

		const layer = this.getLayerByKey( this.shellEl, layerKey );
		const body = layer?.querySelector( '.drill-layer__body' ) || null;
		if ( layer === null || body === null ) {
			return Promise.resolve( null );
		}

		const requestKey = `${Date.now()}-${Math.random()}`;
		this.layerRequests[ layerKey ] = requestKey;

		if ( showPlaceholder ) {
			this.replaceLayerBodyHtml( body, this.buildLoadingMarkup( loadingText ) );
		}
		this.setLayerBusy( layer, true, loadingText );

		return ( new AjaxService() )
			.send( renderAction, false, true )
			.then( ( resp ) => {
				if ( this.layerRequests[ layerKey ] !== requestKey ) {
					return null;
				}

				if ( !resp.success || typeof resp?.data?.html !== 'string' ) {
					this.renderLayerFailure( body, layerKey );
					this.announceLayerMessage( layer, this.getLayerFailureText( layerKey ) );
					return null;
				}

				this.applyLayerHtml( body, resp.data.html );
				onSuccess( resp.data );
				return resp.data;
			} )
			.catch( () => {
				if ( this.layerRequests[ layerKey ] === requestKey ) {
					this.renderLayerFailure( body, layerKey );
					this.announceLayerMessage( layer, this.getLayerFailureText( layerKey ) );
				}
				return null;
			} )
			.finally( () => {
				if ( this.layerRequests[ layerKey ] === requestKey ) {
					this.setLayerBusy( layer, false );
					delete this.layerRequests[ layerKey ];
				}
			} );
	}

	replaceLayerBodyHtml( body, html, activate = false ) {
		BootstrapTooltips.DisposeTooltipsWithin( body );
		body.innerHTML = html;
		if ( activate ) {
			UiContentActivator.activateCurrentSubtree( body );
		}
	}

	applyLayerHtml( body, html ) {
		this.replaceLayerBodyHtml( body, html, true );
	}

	updateOperatorRootStep( rootStepJson ) {
		updateOperatorRootStep( this.rootEl, rootStepJson );
	}

	parseJsonDataset( value = '{}' ) {
		return parseJsonAttribute( value, {} );
	}

	readSelectionPayload( selection ) {
		return {
			key: String( selection?.key || '' ).trim(),
			label: String( selection?.label || '' ).trim(),
			status: String( selection?.status || 'neutral' ).trim(),
			icon_class: String( selection?.icon_class || '' ).trim(),
			header: selection?.header && typeof selection.header === 'object' ? selection.header : {},
		};
	}

	readCountedSelectionPayload( selection ) {
		return {
			...this.readSelectionPayload( selection ),
			item_count: this.parseInteger( selection?.item_count ?? 0 ),
		};
	}

	parseInteger( value ) {
		const parsed = parseInt( String( value ?? '0' ), 10 );
		return Number.isNaN( parsed ) ? 0 : parsed;
	}

	escapeHtml( text = '' ) {
		return String( text )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	renderLayerFailure( body, layerKey ) {
		void body;
		void layerKey;
	}

	getLayerFailureText( layerKey ) {
		void layerKey;
		return '';
	}

	setLayerBusy( layer, isBusy, message = '' ) {
		setElementBusy( layer, isBusy );
		if ( isBusy ) {
			this.announceLayerMessage( layer, message );
		}
	}

	announceLayerMessage( layer, message ) {
		announceWithin( layer, message );
	}
}
