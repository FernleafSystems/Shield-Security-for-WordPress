const { AxeBuilder, expect, test } = require( './shield-test' );

async function expectNoAxeViolations( page, selector, disabledRules = [] ) {
	await expect( page.locator( selector ) ).toBeVisible();
	const builder = new AxeBuilder( { page } ).include( selector );
	if ( disabledRules.length > 0 ) {
		builder.disableRules( disabledRules );
	}
	const { violations } = await builder.analyze();
	if ( violations.length > 0 ) {
		await test.info().attach( 'axe-violations', { body: JSON.stringify( violations, null, 2 ), contentType: 'application/json' } );
	}
	const summary = violations.map( ( violation ) => {
		const targets = violation.nodes.flatMap( ( node ) => node.target ).slice( 0, 5 ).join( ', ' );
		return `${violation.id}: ${targets}`;
	} ).join( '\n' );

	expect( violations.length, summary ).toBe( 0 );
}

module.exports = { expectNoAxeViolations };
