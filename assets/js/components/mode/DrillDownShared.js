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

export function normalizeDrillStatus( status = '' ) {
	const normalized = normalizeDrillText( status );
	return [ 'critical', 'warning', 'good', 'info', 'neutral' ].includes( normalized )
		? normalized
		: 'neutral';
}

export function normalizeLayerHeaderData( headerData ) {
	const source = headerData && typeof headerData === 'object' ? headerData : {};

	return {
		compact_back_label: normalizeDrillText( source.compact_back_label ),
		active_back_label: normalizeDrillText( source.active_back_label ),
		title: normalizeDrillText( source.title ),
		meta: normalizeDrillText( source.meta ),
		summary: normalizeDrillText( source.summary ),
		icon_class: normalizeDrillText( source.icon_class ),
		badge: normalizeDrillText( source.badge ),
		badge_status: normalizeDrillStatus( source.badge_status ),
	};
}
