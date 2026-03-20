const { expect } = require( '@playwright/test' );
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const SHIELD_PAGE = 'icwp-wpsf-plugin';
const PROJECT_ROOT = path.resolve( __dirname, '..', '..', '..', '..' );
const ACTIONS_QUEUE_DETAIL_FIXTURE = '/app/tests/browser/action-router/support/actions-queue-detail-fixture.php';

function buildShieldUrl( params = {} ) {
	const search = new URLSearchParams( {
		page: SHIELD_PAGE,
		...params,
	} );

	return `/wp-admin/admin.php?${search.toString()}`;
}

async function waitForShieldPage( page ) {
	await dismissBlockingDialogs( page );
	await expect( page.locator( '#PageContainer-Apto' ) ).toBeVisible();
	await expect( page.locator( '#PageMain-Shield' ) ).toBeVisible();
}

async function clearIntroJsLayers( page ) {
	await page.evaluate( () => {
		const selectors = [
			'.introjs-overlay',
			'.introjs-helperLayer',
			'.introjs-tooltipReferenceLayer',
			'.introjs-disableInteraction',
			'.introjs-tooltip',
			'.introjs-hints',
			'.modal-backdrop',
			'.modal.show',
		];

		for ( const selector of selectors ) {
			document.querySelectorAll( selector ).forEach( ( node ) => {
				node.remove();
			} );
		}
	} ).catch( () => {} );
}

async function dismissBlockingDialogs( page ) {
	if ( page.isClosed() ) {
		return;
	}

	const closeButtons = [
		'.modal.show .btn-close',
		'.modal.show [data-bs-dismiss="modal"]',
		'.introjs-skipbutton',
		'.introjs-donebutton',
	];

	for ( const selector of closeButtons ) {
		const button = await page.$( selector );
		if ( button ) {
			await button.click( { timeout: 250 } ).catch( () => {} );
			await page.waitForTimeout( 150 );
		}
	}

	const hasOverlay = (
		( await page.$( '.modal.show' ) ) ||
		( await page.$( '.introjs-overlay' ) ) ||
		( await page.$( '.modal-backdrop' ) )
	) !== null;
	if ( hasOverlay ) {
		await page.keyboard.press( 'Escape' ).catch( () => {} );
		await page.waitForTimeout( 250 );
	}

	await clearIntroJsLayers( page );
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

function runTestSiteWpCli( args = [] ) {
	execFileSync( 'php', [
		'bin/shield',
		'test:site:wp',
		...args,
	], {
		cwd: PROJECT_ROOT,
		stdio: 'pipe',
	} );
}

function seedActionsQueueDetailFixture() {
	runTestSiteWpCli( [
		'eval-file',
		ACTIONS_QUEUE_DETAIL_FIXTURE,
		'--',
		'seed',
	] );
}

function cleanupActionsQueueDetailFixture() {
	runTestSiteWpCli( [
		'eval-file',
		ACTIONS_QUEUE_DETAIL_FIXTURE,
		'--',
		'cleanup',
	] );
}

module.exports = {
	buildShieldUrl,
	cleanupActionsQueueDetailFixture,
	dismissBlockingDialogs,
	openShieldRoute,
	seedActionsQueueDetailFixture,
	selectSelect2Option,
	waitForShieldPage,
};
