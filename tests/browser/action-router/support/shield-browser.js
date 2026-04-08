const { expect } = require( '@playwright/test' );
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const SHIELD_PAGE = 'icwp-wpsf-plugin';
const PROJECT_ROOT = path.resolve( __dirname, '..', '..', '..', '..' );

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

	await page.goto( url, { waitUntil: 'load' } );
	await loginIfNeeded( page );
	if ( !page.url().includes( 'page=' + SHIELD_PAGE ) ) {
		await page.goto( url, { waitUntil: 'load' } );
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
		: page.waitForNavigation( { waitUntil: 'load', timeout: 20_000 } );

	await Promise.all( [
		navigationWaiter,
		option.click(),
	] );

	await waitForShieldPage( page );
}

function runShieldCli( args = [] ) {
	return execFileSync( 'php', [
		'bin/shield',
		...args,
	], {
		cwd: PROJECT_ROOT,
		stdio: 'pipe',
	} ).toString( 'utf8' ).trim();
}

function runWpFixture( fixtureKey, args = [] ) {
	const output = runShieldCli( [
		'test:site:fixture',
		fixtureKey,
		...args,
	] );

	if ( output.length < 1 ) {
		return null;
	}

	return JSON.parse( output );
}

async function withWpFixture( fixtureKey, seedArgs, runScenario ) {
	let scenarioError = null;
	let fixtureContract = null;

	try {
		fixtureContract = runWpFixture( fixtureKey, seedArgs );
		return await runScenario( fixtureContract );
	}
	catch ( error ) {
		scenarioError = error;
		throw error;
	}
	finally {
		try {
			runWpFixture( fixtureKey, [ 'cleanup' ] );
		}
		catch ( cleanupError ) {
			if ( scenarioError === null ) {
				throw cleanupError;
			}
		}
	}
}

async function withActionsQueueFixture( scenario, runScenario ) {
	return withWpFixture(
		'actions-queue',
		[ 'seed', scenario ],
		runScenario
	);
}

async function withIpAnalysisActivityMetaFixture( runScenario ) {
	return withWpFixture(
		'ip-analysis-activity-meta',
		[ 'seed' ],
		runScenario
	);
}

module.exports = {
	buildShieldUrl,
	dismissBlockingDialogs,
	openShieldRoute,
	runWpFixture,
	selectSelect2Option,
	waitForShieldPage,
	withActionsQueueFixture,
	withIpAnalysisActivityMetaFixture,
	withWpFixture,
};
