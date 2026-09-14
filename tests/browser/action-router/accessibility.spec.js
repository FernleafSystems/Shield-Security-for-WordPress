const { test, expect } = require( './support/shield-test' );
const { expectNoAxeViolations } = require( './support/accessibility' );
const { expectConnectedNonEmptyReference } = require( './support/modal-accessibility' );
const { openShieldRoute } = require( './support/shield-browser' );

async function expectNonEmptyAriaLabel( locator ) {
	expect(
		await locator.evaluate( ( node ) => ( node.getAttribute( 'aria-label' ) || '' ).trim().length > 0 )
	).toBe( true );
}

function isReportsChartRequest( request ) {
	const params = new URLSearchParams( request.postData() || '' );
	return request.method() === 'POST'
		&& request.url().includes( '/admin-ajax.php' )
		&& params.get( 'ex' ) === 'render_chart_trends';
}

async function exerciseReportsChart( page ) {
	const form = page.locator( '[data-reports-trends-form="1"]' );
	const event = form.locator( 'input[name="event_keys[]"]' ).first();
	const submit = form.locator( '[data-reports-chart-submit="1"]' );

	await expect( event ).toBeVisible();
	await event.focus();
	await event.press( 'Space' );
	await expect( event ).toBeChecked();
	await expect( submit ).toBeEnabled();

	const response = page.waitForResponse(
		( candidate ) => isReportsChartRequest( candidate.request() ),
		{ timeout: 20_000 }
	);
	await submit.click();
	await response;
	await expect( page.locator( '[data-reports-chart-results="1"]' ) ).toBeVisible( { timeout: 20_000 } );
}

const routeAccessibilitySmokeScenarios = [
	{
		id: 'actions-overview',
		route: { nav: 'scans', nav_sub: 'overview' },
		rootSelector: '[data-actions-landing="1"]',
	},
	{
		id: 'configure-overview',
		route: { nav: 'zones', nav_sub: 'overview' },
		rootSelector: '[data-configure-landing="1"]',
	},
	{
		id: 'investigate-overview',
		route: { nav: 'activity', nav_sub: 'overview' },
		rootSelector: '[data-investigate-landing="1"]',
	},
	{
		id: 'reports-overview',
		route: { nav: 'reports', nav_sub: 'overview' },
		rootSelector: '[data-reports-landing="1"]',
	},
	{
		id: 'reports-list',
		route: { nav: 'reports', nav_sub: 'overview', workspace: 'list' },
		rootSelector: '[data-reports-workspace="list"]',
	},
	{
		id: 'reports-charts',
		route: { nav: 'reports', nav_sub: 'overview', workspace: 'charts' },
		rootSelector: '[data-reports-workspace="charts"]',
		exercise: exerciseReportsChart,
	},
	{
		id: 'activity-log',
		route: { nav: 'activity', nav_sub: 'logs' },
		rootSelector: '#SectionAuditTable',
	},
	{
		id: 'web-request-log',
		route: { nav: 'traffic', nav_sub: 'logs' },
		rootSelector: '#ShieldTable-TrafficViewer',
	},
	{
		id: 'sessions',
		route: { nav: 'activity', nav_sub: 'sessions' },
		rootSelector: '#ShieldTable-SessionsViewer',
	},
	{
		id: 'debug',
		route: { nav: 'tools', nav_sub: 'debug' },
		rootSelector: '[data-operator-step-tabs="1"]',
	},
	{
		id: 'manual-scan',
		route: { nav: 'scans', nav_sub: 'run' },
		rootSelector: '#StartScans',
	},
	{
		id: 'traffic-live',
		route: { nav: 'activity', nav_sub: 'overview', subject: 'live_traffic' },
		rootSelector: '#SectionTrafficLiveLogs',
	},
	{
		id: 'ip-rules',
		route: { nav: 'ips', nav_sub: 'rules' },
		rootSelector: '#SectionIpRulesTable',
	},
];

test( 'dashboard overview passes axe smoke', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'dashboard',
		nav_sub: 'overview',
	} );

	await expect( page.locator( '#PageContainer-Apto' ) ).toBeVisible();
	const recentEvents = page.locator( '.dashboard-recent-events' );
	const recentEventItems = recentEvents.locator( '.dashboard-recent-events__item' );
	await expect( recentEvents ).toHaveRole( 'list' );
	expect( await recentEventItems.count() ).toBeGreaterThan( 0 );
	await expect( recentEventItems.first() ).toHaveRole( 'listitem' );
	await expect( recentEventItems.first() ).toHaveAccessibleName( /\S/ );

	const firstEvent = recentEventItems.first();
	const eventLabel = firstEvent.locator( '.dashboard-recent-events__label' );
	const eventTime = firstEvent.locator( '.dashboard-recent-events__time' );
	await expect( eventLabel ).toBeVisible();
	await expect( eventTime ).toBeHidden();
	await firstEvent.scrollIntoViewIfNeeded();
	const bounds = await firstEvent.boundingBox();
	await firstEvent.hover();
	await expect( eventLabel ).toBeHidden();
	await expect( eventTime ).toBeVisible();
	expect( await firstEvent.boundingBox() ).toEqual( bounds );
	await page.mouse.move( 0, 0 );
	await expect( eventLabel ).toBeVisible();
	await firstEvent.focus();
	await expect( firstEvent ).toBeFocused();
	await expect( eventLabel ).toBeHidden();
	await expect( eventTime ).toBeVisible();
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
	await expect( shellTitle ).toHaveAccessibleName( /\S/ );
	await expect( shell ).toHaveAccessibleName( /\S/ );

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

for ( const scenario of routeAccessibilitySmokeScenarios ) {
	test( `${scenario.id} route preserves admin shell and scoped axe contract`, async ( { page } ) => {
		await openShieldRoute( page, scenario.route );

		await expect( page.locator( scenario.rootSelector ) ).toBeVisible();
		if ( scenario.exercise ) {
			await scenario.exercise( page );
		}
		await expectAccessibleAdminShell( page );
		await expectNoAxeViolations( page, '#PageContainer-Apto' );
	} );
}

test( 'plugin investigate page preserves accessible admin shell for a loaded subject state', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'by_plugin',
		plugin_slug: 'wp-simple-firewall/icwp-wpsf.php',
	} );

	await expect( page.locator( '[data-investigate-subject-header="1"]' ) ).toBeVisible();
	await expectAccessibleAdminShell( page );
	await expectNoAxeViolations( page, '#PageContainer-Apto' );
} );
