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

export function normalizeDrillColorKey( colorKey = '' ) {
	const normalized = normalizeDrillText( colorKey );
	return [
		'home',
		'actions',
		'configure',
		'investigate',
		'reports',
		'critical',
		'warning',
		'good',
		'info',
		'neutral',
	].includes( normalized )
		? normalized
		: 'neutral';
}

export function normalizeLayerHeaderData( headerData ) {
	const source = headerData && typeof headerData === 'object' ? headerData : {};

	return {
		compact_back_label: normalizeDrillText( source.compact_back_label ),
		active_back_label: normalizeDrillText( source.active_back_label ),
		breadcrumb_label: normalizeDrillText( source.breadcrumb_label ),
		title: normalizeDrillText( source.title ),
		meta: normalizeDrillText( source.meta ),
		summary: normalizeDrillText( source.summary ),
		focus: normalizeDrillText( source.focus ),
		next_step: normalizeDrillText( source.next_step ),
		icon_class: normalizeDrillText( source.icon_class ),
		badge: normalizeDrillText( source.badge ),
		badge_status: normalizeDrillStatus( source.badge_status ),
		color_key: normalizeDrillColorKey( source.color_key ),
	};
}

export function parseJsonAttribute( rawValue, fallback = {} ) {
	if ( typeof rawValue !== 'string' || rawValue.trim().length < 1 ) {
		return fallback;
	}

	try {
		const parsed = JSON.parse( rawValue );
		return parsed && typeof parsed === 'object' ? parsed : fallback;
	}
	catch {
		return fallback;
	}
}

export function updateOperatorRootStep( rootEl, rootStepJson ) {
	if ( !( rootEl instanceof Element ) || typeof rootStepJson !== 'string' || rootStepJson.length < 1 ) {
		return;
	}

	const operatorShell = rootEl.closest( '[data-mode-shell="1"][data-operator-chrome="1"]' );
	if ( !( operatorShell instanceof HTMLElement ) ) {
		return;
	}

	operatorShell.dataset.operatorRootStep = rootStepJson;
	const stepTabs = shieldAppMain?.components?.step_tabs || null;
	if ( stepTabs !== null && typeof stepTabs.renderShell === 'function' ) {
		stepTabs.renderShell( operatorShell );
	}
}
