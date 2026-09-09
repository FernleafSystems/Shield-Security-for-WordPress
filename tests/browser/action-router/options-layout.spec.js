const { test, expect } = require( './support/shield-test' );
const { expectNoAxeViolations } = require( './support/accessibility' );
const { openShieldRoute } = require( './support/shield-browser' );

async function expectSingleSectionOptionsAccessible( page, form, axeScope ) {
	await expect( form ).toBeVisible();
	await expect( form.locator( '[role="tablist"]' ) ).toHaveCount( 0 );
	const panel = form.locator( '.shield-options-panel' );
	await expect( panel ).toHaveCount( 1 );
	await expect( panel ).toHaveAttribute( 'role', 'region' );
	await expect( panel ).toHaveAccessibleName( /\S/ );
	await expectNoAxeViolations( page, axeScope );
}

async function expectAllOptionsSectionsKeyboardAccessible( page, form, axeScope ) {
	await expect( form ).toBeVisible();
	const tablist = form.locator( '[role="tablist"]' );
	await expect( tablist ).toHaveCount( 1 );
	await expect( tablist ).toHaveAttribute( 'aria-orientation', 'vertical' );

	const tabs = tablist.locator( '[role="tab"]' );
	const tabCount = await tabs.count();
	expect( tabCount ).toBeGreaterThan( 1 );
	const relationships = [];
	for ( let index = 0; index < tabCount; index++ ) {
		const tab = tabs.nth( index );
		const tabId = await tab.getAttribute( 'id' );
		const panelId = await tab.getAttribute( 'aria-controls' );
		expect( tabId || '' ).not.toHaveLength( 0 );
		expect( panelId || '' ).not.toHaveLength( 0 );
		const panel = form.locator( `[id="${panelId}"][role="tabpanel"]` );
		await expect( panel ).toHaveCount( 1 );
		await expect( panel ).toHaveAttribute( 'aria-labelledby', tabId );
		relationships.push( `${tabId}:${panelId}` );
	}
	expect( new Set( relationships ).size ).toBe( tabCount );

	const selectedTab = tablist.locator( '[role="tab"][aria-selected="true"]' );
	await expect( selectedTab ).toHaveCount( 1 );
	await selectedTab.focus();
	await page.keyboard.press( 'Home' );

	for ( let index = 0; index < tabCount; index++ ) {
		const tab = tabs.nth( index );
		const panelId = await tab.getAttribute( 'aria-controls' );
		await expect( tab ).toBeFocused();
		await expect( tab ).toHaveAttribute( 'aria-selected', 'true' );
		await expect( form.locator( `[id="${panelId}"]` ) ).toBeVisible();
		await expect( form.locator( `[id="${panelId}"]` ) ).toHaveAccessibleName( /\S/ );
		await expectNoAxeViolations( page, axeScope );
		if ( index < tabCount - 1 ) {
			await page.keyboard.press( 'ArrowDown' );
		}
	}
}

test( 'multi-section settings stack navigation before the options become cramped', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
		component: 'module_integrations',
	} );
	const form = page.locator( '.offcanvas.show .options_form_for--modern' );
	await expect( form ).toBeVisible();
	for ( const [ width, stacked ] of [ [ 768, true ], [ 1280, true ], [ 1440, true ], [ 1920, false ] ] ) {
		await page.setViewportSize( { width, height: 1000 } );
		await expect.poll( () => form.evaluate( ( element ) => {
			const rail = element.querySelector( '.shield-options-rail' ).getBoundingClientRect();
			const content = element.querySelector( '.shield-options-content' ).getBoundingClientRect();
			return rail.bottom <= content.top;
		} ), `navigation placement at ${width}px` ).toBe( stacked );
		const layout = await form.evaluate( ( element ) => {
			const content = element.querySelector( '.shield-options-content' );
			return {
				unusedWidth: element.clientWidth - content.clientWidth,
				overflow: element.scrollWidth - element.clientWidth,
			};
		} );
		expect( layout.overflow, `form overflow at ${width}px` ).toBeLessThanOrEqual( 1 );
		if ( stacked ) {
			expect( layout.unusedWidth, `options use the full form width at ${width}px` ).toBeLessThanOrEqual( 1 );
		}
		await expect( form.locator( '.shield-options-rail-save button[type="submit"]' ) ).toBeVisible();
	}
} );

for ( const component of [ 'whitelabel', 'login_hide' ] ) {
	test( `${component} single-section offcanvas exposes an accessible loaded form`, async ( { page } ) => {
		await openShieldRoute( page, {
			nav: 'zones',
			nav_sub: 'overview',
			component,
		} );

		await expectSingleSectionOptionsAccessible(
			page,
			page.locator( '#AptoOffcanvas .options_form_for--modern' ),
			'#AptoOffcanvas'
		);
	} );
}

test( 'Integrations offcanvas exposes every section through keyboard-first tab relationships', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
		component: 'module_integrations',
	} );
	// Bootstrap installs the focus trap and async tab handlers when opening completes.
	await expect( page.locator( '#AptoOffcanvas' ) ).toHaveClass( /(^|\s)show(\s|$)/ );

	await expectAllOptionsSectionsKeyboardAccessible(
		page,
		page.locator( '#AptoOffcanvas .options_form_for--modern' ),
		'#AptoOffcanvas'
	);
} );

test( 'Reports settings exposes every inline section through keyboard-first tab relationships', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'reports',
		nav_sub: 'overview',
		workspace: 'settings',
	} );

	await expectAllOptionsSectionsKeyboardAccessible(
		page,
		page.locator( '[data-reports-workspace="settings"] .options_form_for--modern' ),
		'#PageContainer-Apto'
	);
} );
