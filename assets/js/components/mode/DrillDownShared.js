export function isDrillShell( shellEl ) {
	return shellEl instanceof HTMLElement
		&& shellEl.dataset.drillShell === '1';
}

export function parseLayerIndex( layerIndex ) {
	const parsedIndex = parseInt( String( layerIndex ), 10 );
	return Number.isNaN( parsedIndex ) ? -1 : parsedIndex;
}

export function getLayersForShell( shellEl ) {
	if ( !isDrillShell( shellEl ) ) {
		return [];
	}

	return Array.from( shellEl.querySelectorAll( '[data-drill-layer]' ) )
		.filter( ( layer ) => layer.closest( '[data-drill-shell="1"]' ) === shellEl )
		.sort( ( a, b ) => parseLayerIndex( a.dataset.drillLayer ) - parseLayerIndex( b.dataset.drillLayer ) );
}

export function getLayerForShell( shellEl, layerIndex ) {
	return getLayersForShell( shellEl )
		.find( ( layer ) => parseLayerIndex( layer.dataset.drillLayer ) === parseLayerIndex( layerIndex ) ) || null;
}

export function getActiveLayerIndex( layers ) {
	for ( const layer of layers ) {
		if ( !layer.classList.contains( 'drill-layer--compact' )
			&& !layer.classList.contains( 'drill-layer--hidden' ) ) {
			return parseLayerIndex( layer.dataset.drillLayer );
		}
	}

	return -1;
}

export function normalizeDrillText( text = '' ) {
	return String( text ?? '' ).trim();
}

export function normalizeDrillPathSegments( path ) {
	if ( !Array.isArray( path ) ) {
		return [];
	}

	return path
		.map( ( segment ) => normalizeDrillText( segment ) )
		.filter( ( segment ) => segment.length > 0 );
}

export function normalizeLayerContextData( contextData ) {
	const source = contextData && typeof contextData === 'object' ? contextData : {};

	return {
		path: normalizeDrillPathSegments( source.path ),
		focus: normalizeDrillText( source.focus ),
		next_step: normalizeDrillText( source.next_step ),
	};
}

export function hasRenderableLayerContext( contextData ) {
	const context = normalizeLayerContextData( contextData );

	return context.path.length > 0
		|| context.focus.length > 0
		|| context.next_step.length > 0;
}
