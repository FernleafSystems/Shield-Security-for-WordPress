const { test, expect } = require( './support/shield-test' );
const { dismissBlockingDialogs, openShieldRoute } = require( './support/shield-browser' );
const {
	expectFocusWithin,
	expectNamedDialog,
} = require( './support/modal-accessibility' );
const {
	collectRuntimeErrors,
	expectNoAxeViolationsInDialog,
	expectNoRuntimeErrors,
} = require( './support/security-assertions' );

test.setTimeout( 180_000 );

const dashboardRoute = {
	nav: 'dashboard',
	nav_sub: 'overview',
};

test( 'dashboard task guide opens an accessible chooser and exposes deep links', async ( { page } ) => {
	const runtimeErrors = collectRuntimeErrors( page );
	await openShieldRoute( page, dashboardRoute );
	await dismissBlockingDialogs( page );

	const guide = page.locator( '.dashboard-task-guide' );
	const launcher = guide.locator( '[data-dashboard-task-guide-launch="1"]' );
	await expect( guide ).toBeVisible();
	await expect( launcher ).toHaveRole( 'button' );
	await expect( launcher ).toHaveAttribute( 'aria-haspopup', 'dialog' );
	await expect( launcher ).toHaveAttribute( 'aria-controls', 'ShieldMainAccessibleDialog' );
	await expect( launcher ).not.toHaveAttribute( 'data-bs-toggle' );

	await launcher.focus();
	await page.keyboard.press( 'Enter' );

	const modal = page.locator( '#ShieldMainAccessibleDialog' );
	await expect( modal ).toBeVisible();
	await expectNamedDialog( page, modal, 'ShieldMainAccessibleDialogTitle' );
	await expect( modal ).toHaveAccessibleName( /\S/ );
	await expectFocusWithin( modal );
	await expect( modal.locator( '[data-dashboard-task-guide-next-node]' ) ).toHaveCount( 5 );
	await expect( modal.locator( '.dashboard-task-guide-modal__choice-description' ) ).toHaveCount( 0 );
	await expectFocusWithin( modal );
	await expectNoAxeViolationsInDialog( page, { dialog: 'ShieldMainAccessibleDialog' } );

	await modal.locator( '[data-dashboard-task-guide-next-node="ip_access"]' ).click();
	const ipRuleLink = modal.locator( '[data-dashboard-task-guide-leaf="1"]' ).first();
	await expect( modal.getByRole( 'button', { name: 'Back', exact: true } ) ).toHaveRole( 'button' );
	await expect( modal.locator( '[data-dashboard-task-guide-leaf="1"]' ) ).toHaveCount( 2 );
	await expect( ipRuleLink ).toHaveRole( 'link' );
	await expectFocusWithin( modal );
	expect( await ipRuleLink.evaluate( ( link ) => {
		const url = new URL( link.href );
		return {
			nav: url.searchParams.get( 'nav' ),
			subnav: url.searchParams.get( 'nav_sub' ),
		};
	} ) ).toEqual( {
		nav: 'ips',
		subnav: 'rules',
	} );

	await modal.getByRole( 'button', { name: 'Back', exact: true } ).click();
	await expect( modal.locator( '[data-dashboard-task-guide-next-node]' ) ).toHaveCount( 5 );
	await modal.locator( '[data-dashboard-task-guide-next-node="scans"]' ).click();
	const scanResultsLink = modal.locator( '[data-dashboard-task-guide-leaf="1"]' ).first();
	expect( await scanResultsLink.evaluate( ( link ) => {
		const url = new URL( link.href );
		return {
			nav: url.searchParams.get( 'nav' ),
			subnav: url.searchParams.get( 'nav_sub' ),
			zone: url.searchParams.get( 'zone' ),
		};
	} ) ).toEqual( {
		nav: 'scans',
		subnav: 'overview',
		zone: 'scans',
	} );
	await page.keyboard.press( 'Escape' );
	await expect( modal ).not.toBeVisible();
	await expect( launcher ).toBeFocused();

	await openShieldRoute( page, {
		nav: 'reports',
		nav_sub: 'overview',
	} );
	await dismissBlockingDialogs( page );
	const urlBeforeSidebarLaunch = page.url();
	const sidebarGuide = page.locator( '#NavSideBar .sidebar-task-guide-link' );
	await expect( sidebarGuide ).toHaveRole( 'button' );
	await expect( sidebarGuide ).not.toHaveAttribute( 'href' );
	await expect( sidebarGuide ).toHaveAttribute( 'aria-haspopup', 'dialog' );
	await expect( sidebarGuide ).toHaveAttribute( 'aria-controls', 'ShieldMainAccessibleDialog' );
	await sidebarGuide.focus();
	await page.keyboard.press( 'Enter' );
	await expect( modal ).toBeVisible();
	await expectFocusWithin( modal );
	expect( page.url() ).toBe( urlBeforeSidebarLaunch );
	await page.keyboard.press( 'Escape' );
	await expect( modal ).not.toBeVisible();
	await expect( sidebarGuide ).toBeFocused();
	await expectNoRuntimeErrors( runtimeErrors, 'dashboard task guide' );
} );

test( 'dashboard task guide appears as a distinct guidance panel before dashboard stats', async ( { page } ) => {
	await page.setViewportSize( { width: 1800, height: 1100 } );
	await openShieldRoute( page, dashboardRoute );
	await dismissBlockingDialogs( page );

	const launchpad = page.locator( '.dashboard-launchpad-section' );
	const taskGuide = page.locator( '.dashboard-task-guide__launch' );
	const stats = page.locator( '.dashboard-activity-charts-section' );
	await expect( launchpad ).toBeVisible();
	await expect( taskGuide ).toBeVisible();
	await expect( stats ).toBeVisible();

	const [ launchpadBox, taskGuideBox, statsBox ] = await Promise.all( [
		launchpad.boundingBox(),
		taskGuide.boundingBox(),
		stats.boundingBox(),
	] );
	expect( launchpadBox ).not.toBeNull();
	expect( taskGuideBox ).not.toBeNull();
	expect( statsBox ).not.toBeNull();
	expect( taskGuideBox.y ).toBeGreaterThanOrEqual( launchpadBox.y + launchpadBox.height );
	expect( taskGuideBox.y + taskGuideBox.height ).toBeLessThanOrEqual( statsBox.y );
	expect( Math.abs( taskGuideBox.width - statsBox.width ) ).toBeLessThanOrEqual( 2 );
} );
