const { openShieldRoute, test, expect } = require( './support/shield-test' );

// Table downloads don't need Pro, so this runs without the license fixture.
async function expectTableDownload( page, route, tableSelector, label, filenamePattern ) {
	await openShieldRoute( page, route );
	const download = page.locator( `${ tableSelector }_wrapper a.table-download`, { hasText: label } );
	await expect( download ).toBeVisible( { timeout: 20_000 } );

	const downloadedFile = page.waitForEvent( 'download', { timeout: 20_000 } );
	await download.click();
	const file = await downloadedFile;
	expect( await file.failure() ).toBeNull();
	expect( file.suggestedFilename() ).toMatch( filenamePattern );
}

test( 'table download buttons download their files', async ( { page, fixtureApi } ) => {
	await expectTableDownload(
		page,
		{ nav: 'traffic', nav_sub: 'logs' },
		'#ShieldTable-TrafficViewer',
		'Download Request Logs',
		/^traffic-\d+\.log$/
	);

	await fixtureApi.withIpRulesTableFixture( () => expectTableDownload(
		page,
		{ nav: 'ips', nav_sub: 'rules' },
		'#ShieldTable-IpRules',
		'Download IP Rules as CSV',
		/^ip_rules-\d+\.csv$/
	) );
} );
