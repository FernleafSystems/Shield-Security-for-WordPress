const { test, expect } = require( './support/shield-test' );

const SITE_ACTION_SELECTOR = 'button.site_action[data-mainwp-site-action="1"]';
const ACTION_HANDLER = 'mwp_server_site_client_action_handler';

function requestParams( request ) {
	return new URLSearchParams( request.postData() || '' );
}

function isMainwpSiteActionRequest( request, siteID, actionSlug ) {
	if ( request.method() !== 'POST' || !request.url().includes( '/admin-ajax.php' ) ) {
		return false;
	}

	const params = requestParams( request );
	return params.get( 'action' ) === 'shield_action'
		&& params.get( 'ex' ) === ACTION_HANDLER
		&& params.get( 'client_site_id' ) === String( siteID )
		&& params.get( 'client_site_action_data[site_action_slug]' ) === actionSlug;
}

async function expectActionRequestFrom( page, action, siteID, actionSlug, trigger ) {
	const request = page.waitForRequest(
		( candidate ) => isMainwpSiteActionRequest( candidate, siteID, actionSlug ),
		{ timeout: 20_000 }
	);
	await trigger();
	await request;
	await expect( action ).toBeFocused();
}

test( 'MainWP site action buttons preserve click keyboard focus and payload behavior', async ( { page, fixtureApi } ) => {
	await fixtureApi.withMainwpSitesFixture( async ( fixture ) => {
		await page.goto( fixture.page_url, { waitUntil: 'load' } );

		const actionKey = 'sync';
		const actionSlug = fixture.actions[ actionKey ];
		const siteAction = page.locator(
			`${ SITE_ACTION_SELECTOR }[data-mainwp-site-id="${ fixture.site_id }"][data-mainwp-site-action-key="${ actionKey }"]`
		);
		await expect( page.locator( '#mainwp-shield-extension-table-sites' ) ).toBeVisible();
		await expect( siteAction ).toBeVisible();
		await expect( siteAction ).toHaveRole( 'button' );
		await expect( siteAction ).toHaveAttribute( 'type', 'button' );
		expect( await siteAction.getAttribute( 'href' ) ).toBeNull();

		const originalURL = page.url();

		await siteAction.focus();
		await expectActionRequestFrom(
			page,
			siteAction,
			fixture.site_id,
			actionSlug,
			() => siteAction.click()
		);
		expect( page.url() ).toBe( originalURL );

		await expectActionRequestFrom(
			page,
			siteAction,
			fixture.site_id,
			actionSlug,
			() => page.keyboard.press( 'Enter' )
		);
		expect( page.url() ).toBe( originalURL );

		await expectActionRequestFrom(
			page,
			siteAction,
			fixture.site_id,
			actionSlug,
			() => page.keyboard.press( 'Space' )
		);
		expect( page.url() ).toBe( originalURL );
	} );
} );
