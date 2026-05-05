const { test, expect } = require( './support/shield-test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { openShieldRoute } = require( './support/shield-browser' );

async function expectNoAxeViolations( page, selector ) {
	const results = await new AxeBuilder( { page } )
		.include( selector )
		.analyze();

	expect( results.violations, formatAxeViolations( results.violations ) ).toEqual( [] );
}

function formatAxeViolations( violations ) {
	return violations.map( ( violation ) => {
		const targets = violation.nodes
			.flatMap( ( node ) => node.target || [] )
			.slice( 0, 5 )
			.join( ', ' );

		return `${violation.id}: ${targets}`;
	} ).join( '\n' );
}

async function expectConnectedNonEmptyReference( page, locator, attribute ) {
	const referenceValue = ( await locator.getAttribute( attribute ) || '' ).trim();
	expect( referenceValue ).not.toHaveLength( 0 );

	for ( const referenceId of referenceValue.split( /\s+/ ) ) {
		await expect( page.locator( `#${referenceId}` ) ).toHaveCount( 1 );
		expect(
			await page.locator( `#${referenceId}` ).evaluate(
				( node ) => node.isConnected && ( node.textContent || '' ).trim().length > 0
			)
		).toBe( true );
	}
}

async function expectNonEmptyAriaLabel( locator ) {
	expect(
		await locator.evaluate( ( node ) => ( node.getAttribute( 'aria-label' ) || '' ).trim().length > 0 )
	).toBe( true );
}

test( 'dashboard overview passes axe smoke', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'dashboard',
		nav_sub: 'overview',
	} );

	await expect( page.locator( '#PageContainer-Apto' ) ).toBeVisible();
	await expectNoAxeViolations( page, '#PageContainer-Apto' );
} );

async function expectAccessibleAdminShell( page ) {
	const shell = page.locator( '#PageContainer-Apto' );
	await expect( shell ).toHaveAttribute( 'role', 'region' );
	await expectConnectedNonEmptyReference( page, shell, 'aria-labelledby' );

	await expect( page.locator( '#wpbody' ) ).toHaveAttribute( 'role', 'main' );
	await expect( page.getByRole( 'main' ) ).toHaveCount( 1 );

	const shellTitle = page.locator( '#ShieldAdminShellTitle' );
	await expect( shellTitle ).toHaveCount( 1 );
	await expect( shellTitle ).not.toHaveCSS( 'display', 'none' );
	expect(
		await shellTitle.evaluate( ( node ) => ( node.textContent || '' ).trim().length > 0 )
	).toBe( true );

	const contentPane = page.locator( '#PageMainBody_Inner-Apto' );
	await expect( contentPane ).toHaveCount( 1 );
	await expect( contentPane ).toHaveRole( 'group' );
	await expectNonEmptyAriaLabel( contentPane );

	const sidebar = page.locator( '#PageMainSide-Apto' );
	await expect( sidebar ).toHaveCount( 1 );
	await expect( sidebar ).toHaveRole( 'group' );
	await expectNonEmptyAriaLabel( sidebar );

	const navigation = page.locator( 'nav#NavSideBar' );
	await expect( navigation ).toHaveCount( 1 );
	await expect( navigation ).toHaveRole( 'navigation' );
	await expectNonEmptyAriaLabel( navigation );
}

test( 'admin shell exposes stable accessibility landmarks', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'dashboard',
		nav_sub: 'overview',
	} );

	await expectAccessibleAdminShell( page );
	await expectNoAxeViolations( page, '#PageContainer-Apto' );
} );

test( 'plugin investigate page preserves accessible admin shell for a loaded subject state', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'by_plugin',
		plugin_slug: 'wp-simple-firewall/icwp-wpsf.php',
	} );

	await expect( page.locator( '[data-investigate-subject-header="1"]' ) ).toBeVisible();
	await expectAccessibleAdminShell( page );
} );
