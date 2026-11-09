// Captures Shield's admin screens as images for visual review. It asserts nothing, so it only runs
// through `composer test:browser:screenshots`; see "UI screenshots" in TESTING.md.
const fs = require( 'fs' );
const path = require( 'path' );
const { openShieldRoute, test } = require( './support/shield-test' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );

const OUT_DIR = process.env.SHIELD_UI_SCREENSHOTS_DIR || '';
const DEFAULT_WIDTH = 1286;
const WIDTH = parseInt( process.env.SHIELD_UI_WIDTH || String( DEFAULT_WIDTH ), 10 );
const WINDOW_HEIGHT = 860;
const DEFAULT_FIXTURE = process.env.SHIELD_UI_FIXTURE || 'direct_table';

const QUIET_MS = 600;
const SETTLE_LIMIT_MS = 8_000;

const DASHBOARD = { nav: 'dashboard', nav_sub: 'overview' };
const ACTIONS_QUEUE = { nav: 'scans', nav_sub: 'overview' };
const CONFIGURE = { nav: 'zones', nav_sub: 'overview' };
const INVESTIGATE = { nav: 'activity', nav_sub: 'overview' };
const REPORTS = { nav: 'reports', nav_sub: 'overview' };

const CONFIGURE_GOALS = [ 'control', 'accounts', 'login', 'attacks', 'code', 'spam', 'harden', 'shield', 'integrations', 'alerts' ];

/**
 * Screens wait for the page's requests to finish rather than for a fixed time. The limit keeps
 * pages that poll, such as Live Traffic, from holding a capture open.
 */
function networkSettler( page ) {
	let inFlight = 0;
	let lastActivity = Date.now();
	const track = ( change ) => {
		inFlight += change;
		lastActivity = Date.now();
	};
	page.on( 'request', () => track( 1 ) );
	page.on( 'requestfinished', () => track( -1 ) );
	page.on( 'requestfailed', () => track( -1 ) );

	return async () => {
		const limit = Date.now() + SETTLE_LIMIT_MS;
		lastActivity = Date.now();
		while ( Date.now() < limit && ( inFlight > 0 || Date.now() - lastActivity < QUIET_MS ) ) {
			await page.waitForTimeout( 50 );
		}
	};
}

function screenTools( page, screenName, fixture, fixtureApi ) {
	const settle = networkSettler( page );
	const ui = {
		page,
		fixture,
		fixtureApi,
		settle,
		async open( route ) {
			await openShieldRoute( page, route );
			await settle();
		},
		async click( locator ) {
			await locator.click();
			await settle();
		},
		/** Full page unless `viewport` is set (for overlays) or `of` names one element. */
		async shot( state = '', { of = null, viewport = false } = {} ) {
			const nameParts = [ screenName, state, WIDTH === DEFAULT_WIDTH ? '' : String( WIDTH ) ].filter( Boolean );
			const file = path.join( OUT_DIR, `${nameParts.join( '-' )}.png` );
			if ( of !== null ) {
				await of.screenshot( { path: file } );
			}
			else if ( viewport ) {
				await page.screenshot( { path: file } );
			}
			else {
				// Playwright's fullPage leaves fixed bars stranded mid-image, so the window grows to fit instead.
				const height = await page.evaluate( () => document.documentElement.scrollHeight );
				await page.setViewportSize( { width: WIDTH, height: Math.max( height, WINDOW_HEIGHT ) } );
				await page.waitForTimeout( 200 );
				await page.screenshot( { path: file } );
				await page.setViewportSize( { width: WIDTH, height: WINDOW_HEIGHT } );
			}
		},
		/** Clicks a foldable control until none of the matching ones are left closed. */
		async unfoldAll( selector ) {
			const folded = page.locator( `${selector}[aria-expanded="false"]:visible` );
			for ( let remaining = await folded.count(); remaining > 0; remaining-- ) {
				await folded.first().click();
			}
			await settle();
		},
	};
	return ui;
}

const unfoldActionsQueue = async ( ui ) => {
	await ui.unfoldAll( '[data-actions-queue-section-toggle="1"]' );
	await ui.unfoldAll( '[data-actions-queue-box-toggle]' );
};

const SCREENS = [
	{
		name: 'dashboard',
		capture: async ( ui ) => {
			await ui.open( DASHBOARD );
			await ui.shot();
		},
	},
	{
		name: 'dashboard-live-monitor',
		capture: async ( ui ) => {
			await ui.open( DASHBOARD );
			await ui.click( ui.page.locator( '[data-live-monitor-launch="1"]' ).first() );
			await ui.shot( '', { viewport: true } );
		},
	},
	{
		name: 'global-search',
		capture: async ( ui ) => {
			await ui.open( DASHBOARD );
			await ui.page.keyboard.press( 'Control+k' );
			await ui.settle();
			await ui.shot( 'empty', { viewport: true } );
			await ui.page.keyboard.type( 'email' );
			await ui.settle();
			await ui.shot( 'results', { viewport: true } );
		},
	},
	{
		name: 'setup-guide',
		capture: async ( ui ) => {
			await ui.open( DASHBOARD );
			await ui.click( ui.page.locator( '[data-setup-guide-toggle="1"]' ).first() );
			await ui.shot( 'open', { viewport: true } );
			const steps = ui.page.locator( '[data-setup-guide-step]' );
			const slugs = await steps.evaluateAll( ( items ) => items.map( ( item ) => item.dataset.setupGuideStep ) );
			for ( const slug of slugs ) {
				await ui.click( ui.page.locator( `[data-setup-guide-step="${slug}"]` ) );
				await ui.shot( slug, { viewport: true } );
			}
		},
	},
	{
		name: 'actions-queue',
		capture: async ( ui ) => {
			await ui.open( ACTIONS_QUEUE );
			await ui.shot( 'default' );
			await unfoldActionsQueue( ui );
			await ui.shot( 'unfolded' );
		},
	},
	{
		name: 'actions-queue-tabs',
		capture: async ( ui ) => {
			await ui.open( ACTIONS_QUEUE );
			await ui.click( ui.page.locator( '[data-actions-queue-view="tabs"]' ).first() );
			const tabs = await ui.page.locator( '[data-actions-queue-tab]' ).evaluateAll( ( items ) => items.map( ( item ) => item.dataset.actionsQueueTab ) );
			for ( const tab of tabs ) {
				await ui.click( ui.page.locator( `[data-actions-queue-tab="${tab}"]` ) );
				await ui.shot( tab );
			}
		},
	},
	{
		name: 'actions-queue-ignored',
		capture: async ( ui ) => {
			await ui.open( ACTIONS_QUEUE );
			await unfoldActionsQueue( ui );
			await ui.click( ui.page.locator( '[data-actions-queue-maintenance-all="ignore"]' ).first() );
			await unfoldActionsQueue( ui );
			await ui.shot();
		},
	},
	{
		// The test site can't run scans, so the queue is told one is running and the checks are answered here.
		name: 'actions-queue-scan-running',
		capture: async ( ui ) => {
			await ui.page.route( '**/admin.php*', async ( route ) => {
				if ( route.request().resourceType() !== 'document' ) {
					return route.fallback();
				}
				const response = await route.fetch();
				const body = ( await response.text() ).replace( '"initial_check":false', '"initial_check":true' );
				return route.fulfill( { response, body } );
			} );
			await ui.page.route( '**/admin-ajax.php', async ( route ) => {
				if ( new URLSearchParams( route.request().postData() || '' ).get( 'ex' ) !== 'scans_check' ) {
					return route.fallback();
				}
				return route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( { success: true, data: {
					running: {},
					failed: false,
					failure_message: '',
					modal_state: 'running',
					modal_html: '<div></div>',
					scan_rows: [ { id: 1, scan: 'afs', name: 'WordPress Filesystem Scan', is_current: true, progress: 42 } ],
				} } ) } );
			} );
			await ui.open( ACTIONS_QUEUE );
			await ui.shot( '', { viewport: true } );
		},
	},
	{
		name: 'scan-results',
		capture: async ( ui ) => {
			await ui.open( ACTIONS_QUEUE );
			await new ActionsQueuePage( ui.page ).drillToDetail( ui.fixture );
			await ui.settle();
			await ui.shot( '', { viewport: true } );
		},
	},
	{
		name: 'scan-results-malware',
		fixture: 'malware_direct_table',
		capture: async ( ui ) => {
			await ui.open( ACTIONS_QUEUE );
			await new ActionsQueuePage( ui.page ).drillToDetail( ui.fixture );
			await ui.settle();
			await ui.shot( '', { viewport: true } );
			await ui.click( ui.page.locator( '[data-drill-layer-key="detail"] table[data-scan-results-table="1"] tbody tr td:nth-child(2)' ).first() );
			await ui.shot( 'row-selected', { viewport: true } );
		},
	},
	{
		name: 'scan-file-details',
		fixture: 'malware_assessments',
		capture: async ( ui ) => {
			await ui.open( ACTIONS_QUEUE );
			await new ActionsQueuePage( ui.page ).drillToDetail( ui.fixture );
			const details = ui.page.locator( '[data-scan-results-table="1"] button.scan-results-malware-assessment' );
			await details.first().waitFor();
			const dialog = ui.page.locator( '#ShieldModalContainer.modal.show' );
			for ( let index = 0; index < await details.count(); index++ ) {
				await details.nth( index ).click();
				await dialog.locator( '#tabInfo' ).waitFor();
				await ui.settle();
				await ui.shot( String( index + 1 ), { of: dialog.locator( '.modal-content' ) } );
				await ui.page.keyboard.press( 'Escape' );
				await dialog.waitFor( { state: 'hidden' } );
			}
		},
	},
	{
		name: 'file-locker',
		fixture: 'file_locker_lazy',
		capture: async ( ui ) => {
			await ui.open( ACTIONS_QUEUE );
			const queue = new ActionsQueuePage( ui.page );
			await queue.drillToDetail( ui.fixture );
			await queue.openAssetPanel( ui.fixture.panel_target );
			await ui.settle();
			await ui.shot( '', { viewport: true } );
		},
	},
	{
		// Scans can't run here either, so starting one is answered with the real progress markup.
		name: 'scan-run',
		capture: async ( ui ) => {
			await ui.open( { nav: 'scans', nav_sub: 'run', step: 'choose' } );
			await ui.shot( 'choose' );
			const { modal_html: runningHtml } = await ui.fixtureApi.renderScanProgressFixture( 'running', 'page' );
			const { modal_html: completedHtml } = await ui.fixtureApi.renderScanProgressFixture( 'completed', 'page' );
			let finished = false;
			await ui.page.route( '**/admin-ajax.php', async ( route ) => {
				const action = new URLSearchParams( route.request().postData() || '' ).get( 'ex' );
				if ( action !== 'scans_start' && action !== 'scans_check' ) {
					return route.fallback();
				}
				const completed = action === 'scans_check' && finished;
				return route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( { success: true, data: {
					page_reload: false,
					message: '',
					html: '',
					scans_running: !completed,
					scan_ids: [ 31, 32, 33 ],
					modal_state: completed ? 'completed' : 'running',
					modal_html: completed ? completedHtml : runningHtml,
				} } ) } );
			} );
			await ui.page.locator( '#StartScans' ).evaluate( ( form ) => form.dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) ) );
			await ui.page.locator( '[data-shield-scan-run="1"][data-scan-step="scanning"]' ).waitFor();
			await ui.settle();
			await ui.shot( 'running' );
			finished = true;
			await ui.page.locator( '[data-shield-scan-run="1"][data-scan-step="results"]' ).waitFor( { timeout: 30_000 } );
			await ui.settle();
			await ui.shot( 'results' );
		},
	},
	{
		name: 'configure',
		capture: async ( ui ) => {
			await ui.open( CONFIGURE );
			await ui.shot();
		},
	},
	{
		name: 'configure-search',
		capture: async ( ui ) => {
			await ui.open( CONFIGURE );
			const input = ui.page.locator( '#ConfigureLandingSearchInput' );
			await input.click();
			await input.pressSequentially( 'email', { delay: 40 } );
			await ui.settle();
			await ui.shot( '', { viewport: true } );
		},
	},
	...CONFIGURE_GOALS.map( ( goal ) => ( {
		name: `configure-goal-${goal}`,
		capture: async ( ui ) => {
			await ui.open( { ...CONFIGURE, zone: goal } );
			await ui.shot();
			const rows = ui.page.locator( '[data-configure-diagnosis="1"] [data-configure-row-key]' );
			const topics = await rows.evaluateAll( ( items ) => items.map( ( item ) => item.dataset.configureRowKey ) );
			for ( const topic of topics ) {
				const row = ui.page.locator( `[data-configure-row-key="${topic}"]` );
				const trigger = row.locator( '[data-shield-expand-trigger="1"]' ).first();
				if ( await trigger.getAttribute( 'aria-expanded' ) !== 'true' ) {
					await ui.click( trigger );
				}
				await ui.shot( topic );
			}
		},
	} ) ),
	{
		name: 'configure-needs-attention',
		capture: async ( ui ) => {
			await ui.open( { ...CONFIGURE, zone: 'accounts' } );
			await ui.click( ui.page.locator( '[data-configure-zone-show="attention"]' ).first() );
			await ui.shot();
		},
	},
	{
		name: 'configure-filter',
		capture: async ( ui ) => {
			await ui.open( { ...CONFIGURE, zone: 'accounts' } );
			await ui.page.locator( '[data-configure-zone-filter="1"]' ).fill( 'email' );
			await ui.settle();
			await ui.shot();
		},
	},
	{
		name: 'configure-save-bar',
		capture: async ( ui ) => {
			await ui.open( { ...CONFIGURE, zone: 'accounts' } );
			await ui.click( ui.page.locator( '[data-configure-row-key] [data-shield-expand-trigger="1"]' ).first() );
			await ui.page.locator( '[data-configure-diagnosis="1"] input[type="checkbox"]:visible' ).first().click( { force: true } );
			await ui.settle();
			await ui.shot( '', { viewport: true } );
		},
	},
	{
		name: 'investigate-lookup',
		capture: async ( ui ) => {
			await ui.open( INVESTIGATE );
			await ui.shot( 'home' );
			const input = ui.page.locator( '[data-lookup-input="1"]' );
			await input.click();
			await input.pressSequentially( 'a', { delay: 40 } );
			await ui.settle();
			await ui.shot( 'results', { viewport: true } );
			await ui.click( ui.page.locator( '.investigate-lookup__row[data-investigate-subject="plugin"]' ).first() );
			await ui.shot( 'plugin' );
			await ui.click( ui.page.locator( '[data-lookup-kind="core"]' ) );
			await ui.shot( 'core' );
		},
	},
	{
		name: 'investigate-tabs',
		capture: async ( ui ) => {
			await ui.open( INVESTIGATE );
			for ( const tab of [ 'activity', 'requests', 'ip_rules', 'sessions', 'live' ] ) {
				await ui.click( ui.page.locator( `[data-investigate-tab="${tab}"]` ) );
				await ui.shot( tab, { viewport: true } );
			}
		},
	},
	{
		name: 'reports',
		capture: async ( ui ) => {
			await ui.open( REPORTS );
			await ui.shot( 'charts' );
			for ( const tab of [ 'list', 'settings' ] ) {
				await ui.click( ui.page.locator( `[data-reports-tab="${tab}"]` ) );
				await ui.shot( tab );
			}
		},
	},
	{
		name: 'tools',
		capture: async ( ui ) => {
			for ( const tool of [ 'blockdown', 'whitelabel', 'loginhide' ] ) {
				await ui.open( { nav: 'tools', nav_sub: tool } );
				await ui.shot( tool );
			}
		},
	},
	{
		name: 'loading-placeholders',
		capture: async ( ui ) => {
			await ui.open( DASHBOARD );
			for ( const mode of [ 'actions', 'investigate', 'configure', 'reports' ] ) {
				await ui.page.evaluate( ( placeholderMode ) => {
					const placeholder = document.querySelector( `[data-shield-nav-loading-placeholder="${placeholderMode}"]` ).cloneNode( true );
					placeholder.classList.remove( 'd-none' );
					document.querySelector( '#PageMainBody_Inner-Apto' ).replaceChildren( placeholder );
				}, mode );
				await ui.shot( mode, { viewport: true } );
			}
		},
	},
];

if ( OUT_DIR === '' ) {
	test.skip( 'ui screenshots only run through composer test:browser:screenshots', () => {} );
}
else {
	test.describe( 'ui screenshots', () => {
		test.describe.configure( { timeout: 180_000 } );

		for ( const screen of SCREENS ) {
			test( screen.name, async ( { page, fixtureApi } ) => {
				fs.mkdirSync( OUT_DIR, { recursive: true } );
				page.setDefaultTimeout( 20_000 );
				await page.setViewportSize( { width: WIDTH, height: WINDOW_HEIGHT } );
				await fixtureApi.withActionsQueueFixture( screen.fixture || DEFAULT_FIXTURE, async ( fixture ) => {
					await screen.capture( screenTools( page, screen.name, fixture, fixtureApi ) );
				} );
			} );
		}
	} );
}
