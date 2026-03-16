const { expect } = require( '@playwright/test' );

const SHIELD_PAGE = 'icwp-wpsf-plugin';

function buildShieldUrl( params = {} ) {
	const search = new URLSearchParams( {
		page: SHIELD_PAGE,
		...params,
	} );

	return `/wp-admin/admin.php?${search.toString()}`;
}

async function waitForShieldPage( page ) {
	await expect( page.locator( '#PageContainer-Apto' ) ).toBeVisible();
	await expect( page.locator( '#PageMain-Shield' ) ).toBeVisible();
}

async function loginIfNeeded( page ) {
	const loginForm = page.locator( '#loginform' );
	if ( !( await loginForm.count() ) ) {
		return;
	}

	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
		page.locator( '#wp-submit' ).click(),
	] );
}

async function openShieldRoute( page, params = {} ) {
	const url = buildShieldUrl( params );

	await page.goto( url, { waitUntil: 'domcontentloaded' } );
	await loginIfNeeded( page );
	if ( !page.url().includes( 'page=' + SHIELD_PAGE ) ) {
		await page.goto( url, { waitUntil: 'domcontentloaded' } );
	}

	await waitForShieldPage( page );
	return url;
}

async function selectSelect2Option( page, selectName, searchTerm, optionMatcher, waitForUrlMatcher ) {
	const select = page.locator( `select[name="${selectName}"]` ).first();
	await expect( select ).toBeAttached();

	const container = select.locator( 'xpath=following-sibling::span[contains(@class,"select2")]' ).first();
	await expect( container.locator( '.select2-selection' ) ).toBeVisible();
	await container.locator( '.select2-selection' ).click();

	const searchInput = page.locator( '.select2-container--open .select2-search__field' );
	await expect( searchInput ).toBeVisible();
	await searchInput.fill( searchTerm );

	const option = page.locator( '.select2-results__option' ).filter( {
		hasText: optionMatcher,
	} ).first();
	await expect( option ).toBeVisible();

	const navigationWaiter = waitForUrlMatcher
		? page.waitForURL( waitForUrlMatcher, { timeout: 20_000 } )
		: page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 20_000 } );

	await Promise.all( [
		navigationWaiter,
		option.click(),
	] );

	await waitForShieldPage( page );
}

module.exports = {
	buildShieldUrl,
	openShieldRoute,
	selectSelect2Option,
	waitForShieldPage,
};
