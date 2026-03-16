const { test, expect } = require( '@playwright/test' );
const { openShieldRoute } = require( './support/shield-browser' );

const route = {
	nav: 'scans',
	nav_sub: 'overview',
};

async function drillToLayer( page, layerIndex ) {
	await page.evaluate( ( targetLayer ) => {
		const shell = document.querySelector( '[data-drill-shell="1"]' );
		globalThis.shieldServices.components.drill_down.drillTo( shell, targetLayer );
	}, layerIndex );
}

test( 'temporary drill-down landing normalizes layers and supports drill transitions', async ( { page } ) => {
	await openShieldRoute( page, route );

	const shell = page.locator( '[data-drill-shell="1"][data-drill-shell-mode="actions"][data-drill-shell-id="actions_drill_shell"]' );
	const layers = shell.locator( '[data-drill-layer]' );
	const contextCard = page.locator( '[data-drill-context-card="actions_drill_shell"]' );

	await expect( shell ).toBeVisible();
	await expect( layers ).toHaveCount( 3 );
	await expect( page.locator( '[data-drill-layer="0"]' ) ).not.toHaveClass( /drill-layer--compact|drill-layer--hidden/ );
	await expect( page.locator( '[data-drill-layer="1"]' ) ).toHaveClass( /drill-layer--hidden/ );
	await expect( page.locator( '[data-drill-layer="2"]' ) ).toHaveClass( /drill-layer--hidden/ );
	await expect( page.locator( '[data-drill-layer="2"] .shield-badge' ) ).toHaveClass( /badge-neutral/ );
	await expect( contextCard ).toContainText( 'Start' );
	await expect( contextCard ).toContainText( 'Queue' );
	await expect( contextCard ).toContainText( 'Focus on the highest-priority bucket.' );
	await expect( contextCard ).toContainText( 'Choose the first area to inspect.' );

	const layerContexts = await page.locator( '[data-drill-layer]' ).evaluateAll( ( shellLayers ) => {
		return shellLayers.map( ( layer ) => JSON.parse( layer.dataset.drillLayerContext || '{}' ) );
	} );
	expect( layerContexts[ 0 ] ).toEqual( {
		path: [ 'Start', 'Queue' ],
		focus: 'Focus on the highest-priority bucket.',
		next_step: 'Choose the first area to inspect.',
	} );
	expect( layerContexts[ 2 ] ).toEqual( {
		path: [ 'Start', 'Queue', 'Bucket', 'Item' ],
		focus: 'Review the selected item.',
		next_step: 'Take the specific recommended action.',
	} );

	await drillToLayer( page, 1 );
	await expect( page.locator( '[data-drill-layer="0"]' ) ).toHaveClass( /drill-layer--compact/ );
	await expect( page.locator( '[data-drill-layer="1"]' ) ).not.toHaveClass( /drill-layer--compact|drill-layer--hidden/ );
	await expect( page.locator( '[data-drill-layer="2"]' ) ).toHaveClass( /drill-layer--hidden/ );
	await expect( contextCard ).toContainText( 'Bucket' );
	await expect( contextCard ).toContainText( 'Narrow the queue to a specific group.' );

	await drillToLayer( page, 2 );
	await expect( page.locator( '[data-drill-layer="0"]' ) ).toHaveClass( /drill-layer--compact/ );
	await expect( page.locator( '[data-drill-layer="1"]' ) ).toHaveClass( /drill-layer--compact/ );
	await expect( page.locator( '[data-drill-layer="2"]' ) ).not.toHaveClass( /drill-layer--compact|drill-layer--hidden/ );
	await expect( contextCard ).toContainText( 'Item' );
	await expect( contextCard ).toContainText( 'Review the selected item.' );

	await page.locator( '[data-drill-layer="1"] [data-drill-strip="1"]' ).click();
	await expect( page.locator( '[data-drill-layer="1"]' ) ).not.toHaveClass( /drill-layer--compact|drill-layer--hidden/ );
	await expect( page.locator( '[data-drill-layer="2"]' ) ).toHaveClass( /drill-layer--hidden/ );

	await page.locator( '[data-drill-layer="0"] [data-drill-strip="1"]' ).click();
	await expect( page.locator( '[data-drill-layer="0"]' ) ).not.toHaveClass( /drill-layer--compact|drill-layer--hidden/ );
	await expect( page.locator( '[data-drill-layer="1"]' ) ).toHaveClass( /drill-layer--hidden/ );
} );

test( 'temporary drill-down landing honors deep-link active layer index', async ( { page } ) => {
	await openShieldRoute( page, {
		...route,
		layer: '1',
	} );

	await expect( page.locator( '[data-drill-layer="0"]' ) ).toHaveClass( /drill-layer--compact/ );
	await expect( page.locator( '[data-drill-layer="1"]' ) ).not.toHaveClass( /drill-layer--compact|drill-layer--hidden/ );
	await expect( page.locator( '[data-drill-layer="2"]' ) ).toHaveClass( /drill-layer--hidden/ );
	await expect( page.locator( '[data-drill-context-card="actions_drill_shell"]' ) ).toContainText( 'Bucket' );
} );
