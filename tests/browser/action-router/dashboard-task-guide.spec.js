const { test, expect } = require( './support/shield-test' );
const { dismissBlockingDialogs, openShieldRoute } = require( './support/shield-browser' );
const { collectRuntimeErrors, expectNoRuntimeErrors } = require( './support/security-assertions' );

test.setTimeout( 180_000 );

const dashboardRoute = {
	nav: 'dashboard',
	nav_sub: 'overview',
};

test( 'dashboard task guide opens an accessible chooser and exposes deep links', async ( { page } ) => {
	const runtimeErrors = collectRuntimeErrors( page );
	await openShieldRoute( page, dashboardRoute );
	await dismissBlockingDialogs( page );

	const guide = page.locator( '[data-dashboard-task-guide="1"]' );
	const launcher = guide.locator( '[data-dashboard-task-guide-launch="1"]' );
	await expect( guide ).toBeVisible();
	await expect( launcher ).toHaveRole( 'button' );
	await expect( launcher ).toContainText( 'Help me navigate' );
	await expect( launcher ).toHaveAttribute( 'aria-haspopup', 'dialog' );
	await expect( launcher ).toHaveAttribute( 'aria-controls', 'ShieldModalContainer' );

	await launcher.click();

	const modal = page.locator( '#ShieldModalContainer' );
	const firstChoice = modal.locator( '[data-dashboard-task-guide-next-node]' ).first();
	await expect( modal ).toBeVisible();
	await expect( modal ).toHaveAttribute( 'role', 'dialog' );
	await expect( modal ).toHaveAttribute( 'aria-modal', 'true' );
	await expect( modal ).toHaveAccessibleName( /\S/ );
	await expect( modal.locator( '.modal-dialog' ) ).toHaveClass( /modal-dialog-centered/ );
	await expect( modal.locator( '[data-dashboard-task-guide-next-node]' ) ).toHaveCount( 5 );
	await expect( modal.locator( '.dashboard-task-guide-modal__choice-description' ) ).toHaveCount( 0 );
	await expect( firstChoice ).toBeFocused();

	await modal.locator( '[data-dashboard-task-guide-next-node="ip_access"]' ).click();
	const ipRuleLink = modal.locator( '[data-dashboard-task-guide-leaf="1"]' ).first();
	await expect( modal.locator( '[data-dashboard-task-guide-back="1"]' ) ).toHaveRole( 'button' );
	await expect( modal.locator( '[data-dashboard-task-guide-leaf="1"]' ) ).toHaveCount( 2 );
	await expect( ipRuleLink ).toHaveRole( 'link' );
	await expect( ipRuleLink ).toBeFocused();
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

	await modal.locator( '[data-dashboard-task-guide-back="1"]' ).click();
	await expect( modal.locator( '[data-dashboard-task-guide-next-node]' ) ).toHaveCount( 5 );
	await modal.locator( '[data-dashboard-task-guide-next-node="scans"]' ).click();
	const scanResultsLink = modal.locator( '[data-dashboard-task-guide-leaf="1"]' ).first();
	await expect( scanResultsLink ).toContainText( 'View my scan results' );
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
	await modal.locator( '[data-bs-dismiss="modal"]' ).first().click();
	await expect( modal ).not.toBeVisible();

	const sidebarGuide = page.locator( '#NavSideBar .sidebar-task-guide-link' );
	await expect( sidebarGuide ).toContainText( 'Help me find where to go' );
	await expect( sidebarGuide ).toHaveAttribute( 'href', /task_guide=1/ );
	await sidebarGuide.click();
	await expect( modal ).toBeVisible();
	await expect( modal.locator( '[data-dashboard-task-guide-next-node]' ).first() ).toBeFocused();
	await modal.locator( '[data-bs-dismiss="modal"]' ).first().click();
	await expect( modal ).not.toBeVisible();
	await expectNoRuntimeErrors( runtimeErrors, 'dashboard task guide' );
} );

test( 'dashboard task guide is centred below the three Launchpad destination tiles', async ( { page } ) => {
	await page.setViewportSize( { width: 1800, height: 1100 } );
	await openShieldRoute( page, dashboardRoute );
	await dismissBlockingDialogs( page );

	const configure = page.locator( '.operator-mode-overview__destination[data-mode="configure"]' );
	const taskGuide = page.locator( '.dashboard-task-guide__launch' );
	await expect( configure ).toBeVisible();
	await expect( taskGuide ).toBeVisible();

	const [ configureBox, taskGuideBox ] = await Promise.all( [ configure.boundingBox(), taskGuide.boundingBox() ] );
	expect( configureBox ).not.toBeNull();
	expect( taskGuideBox ).not.toBeNull();
	expect( Math.abs( taskGuideBox.width - configureBox.width ) ).toBeLessThanOrEqual( 2 );
	expect( Math.abs( taskGuideBox.x - configureBox.x ) ).toBeLessThanOrEqual( 2 );
	expect( taskGuideBox.y ).toBeGreaterThan( configureBox.y );
} );
