const { test, expect } = require( './support/shield-test' );
const { openShieldRoute } = require( './support/shield-browser' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );

async function expectVisibleFocusIndicator( focusTarget, styleTarget = focusTarget ) {
	await focusTarget.focus();
	await expect( focusTarget ).toBeFocused();

	const focusStyle = await styleTarget.evaluate( ( node ) => {
		const style = window.getComputedStyle( node );
		return {
			boxShadow: style.boxShadow,
			outlineStyle: style.outlineStyle,
			outlineWidth: style.outlineWidth,
		};
	} );

	const outlineWidth = Number.parseFloat( focusStyle.outlineWidth || '0' );
	const hasOutline = focusStyle.outlineStyle !== 'none' && outlineWidth > 0;
	const hasRingShadow = /\b11,\s*87,\s*164\b/.test( focusStyle.boxShadow );

	expect( hasOutline || hasRingShadow, JSON.stringify( focusStyle ) ).toBe( true );
}

async function waitForDataTableReady( table ) {
	await expect( table ).toBeVisible();
	await expect.poll( async () => table.evaluate( ( element ) => {
		return Boolean( globalThis.jQuery?.fn?.dataTable?.isDataTable?.( element ) );
	} ), { timeout: 20_000 } ).toBe( true );
}

test( 'admin and operator controls expose the shared focus indicator', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
	} );

	await expectVisibleFocusIndicator(
		page.locator( '.configure-zone-card:visible' ).first()
	);

	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	await expectVisibleFocusIndicator(
		page.locator( '.investigate-landing__subject-card:not(.is-disabled):visible' ).first()
	);
} );

test( 'datatable controls expose the shared focus indicator', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );

		const table = page.locator( '[data-actions-queue-detail="1"] [data-scan-results-table="1"]' ).first();
		await waitForDataTableReady( table );

		const dataTableContainer = table.locator( 'xpath=ancestor::div[contains(@class,"dt-container")]' ).first();
		await expectVisibleFocusIndicator(
			dataTableContainer.locator( '.dt-search input' ).first()
		);
	} );
} );

test( 'user-profile MFA controls expose the shared focus indicator', async ( { page, fixtureApi } ) => {
	await fixtureApi.withMfaProfileFixture( async ( fixture ) => {
		await page.goto( fixture.profile_path, { waitUntil: 'load' } );
		await expect( page.locator( '#ShieldUserProfileMFA' ) ).toBeVisible( { timeout: 20_000 } );

		await expectVisibleFocusIndicator(
			page.locator( '#ShieldUserProfileMFA .shield-gen-backup-login-code' ).first()
		);
	} );
} );
