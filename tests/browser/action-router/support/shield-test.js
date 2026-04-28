const base = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );

function laneMap() {
	const rawMap = process.env.SHIELD_BROWSER_LANE_MAP || '';
	if ( !rawMap ) {
		throw new Error( 'SHIELD_BROWSER_LANE_MAP must be set. Use composer test:browser or php bin/shield test:browser.' );
	}

	const parsed = JSON.parse( rawMap );
	if ( !parsed || typeof parsed !== 'object' || Array.isArray( parsed ) ) {
		throw new Error( 'SHIELD_BROWSER_LANE_MAP must decode to an object.' );
	}

	return parsed;
}

function laneForParallelIndex( parallelIndex ) {
	const lane = laneMap()[ String( parallelIndex ) ];
	if ( !lane || typeof lane !== 'object' ) {
		throw new Error( `No browser lane configured for parallel index ${parallelIndex}.` );
	}

	for ( const key of [ 'laneIndex', 'baseUrl', 'fixtureToken', 'authStatePath', 'outputDir' ] ) {
		if ( lane[ key ] === undefined || lane[ key ] === null || String( lane[ key ] ).trim() === '' ) {
			throw new Error( `Browser lane ${parallelIndex} is missing ${key}.` );
		}
	}

	return {
		laneIndex: Number( lane.laneIndex ),
		baseUrl: String( lane.baseUrl ),
		fixtureToken: String( lane.fixtureToken ),
		authStatePath: String( lane.authStatePath ),
		outputDir: String( lane.outputDir ),
	};
}

async function loginAndWriteStorageState( browser, lane ) {
	fs.mkdirSync( path.dirname( lane.authStatePath ), { recursive: true } );
	const context = await browser.newContext( { baseURL: lane.baseUrl } );
	const page = await context.newPage();

	await page.goto( '/wp-admin/', { waitUntil: 'load' } );
	const loginForm = page.locator( '#loginform' );
	if ( await loginForm.count() ) {
		await page.locator( '#user_login' ).fill( 'admin' );
		await page.locator( '#user_pass' ).fill( 'password' );
		await Promise.all( [
			page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
			page.locator( '#wp-submit' ).click(),
		] );
	}

	await context.storageState( { path: lane.authStatePath } );
	await context.close();
}

async function createFixtureApi( playwright, lane ) {
	const request = await playwright.request.newContext( {
		baseURL: lane.baseUrl,
		extraHTTPHeaders: {
			'X-Shield-Browser-Fixture-Token': lane.fixtureToken,
		},
	} );

	async function runFixture( fixture, action, args = [] ) {
		const response = await request.post( '/wp-json/shield-browser-test/v1/fixture', {
			data: {
				fixture,
				action,
				args,
			},
		} );
		const payload = await response.json();
		if ( !response.ok() || payload?.ok !== true ) {
			const code = payload?.error?.code || `http_${response.status()}`;
			const message = payload?.error?.message || 'Browser fixture request failed.';
			throw new Error( `${code}: ${message}` );
		}

		return payload.data;
	}

	return {
		cleanupAll: () => runFixture( '__all__', 'cleanup' ),
		dispose: () => request.dispose(),
		fixtureApi: {
			async withActionsQueueFixture( scenario, runScenario ) {
				let seeded = false;
				try {
					const contract = await runFixture( 'actions-queue', 'seed', [ scenario ] );
					seeded = true;
					return await runScenario( contract );
				}
				finally {
					if ( seeded ) {
						await runFixture( 'actions-queue', 'cleanup' );
					}
				}
			},
			async withIpAnalysisActivityMetaFixture( runScenario ) {
				let seeded = false;
				try {
					const contract = await runFixture( 'ip-analysis-activity-meta', 'seed' );
					seeded = true;
					return await runScenario( contract );
				}
				finally {
					if ( seeded ) {
						await runFixture( 'ip-analysis-activity-meta', 'cleanup' );
					}
				}
			},
		},
	};
}

const test = base.test.extend( {
	lane: [
		async ( {}, use, testInfo ) => {
			await use( laneForParallelIndex( testInfo.parallelIndex ) );
		},
		{ scope: 'worker' },
	],
	authStatePath: [
		async ( { browser, lane }, use ) => {
			await loginAndWriteStorageState( browser, lane );
			await use( lane.authStatePath );
		},
		{ scope: 'worker' },
	],
	fixtureApi: [
		async ( { playwright, lane }, use ) => {
			const api = await createFixtureApi( playwright, lane );
			await api.cleanupAll();
			try {
				await use( api.fixtureApi );
			}
			finally {
				await api.cleanupAll();
				await api.dispose();
			}
		},
		{ scope: 'worker' },
	],
	context: async ( { browser, lane, authStatePath }, use ) => {
		const context = await browser.newContext( {
			baseURL: lane.baseUrl,
			storageState: authStatePath,
		} );
		await use( context );
		await context.close();
	},
} );

module.exports = {
	expect: base.expect,
	test,
};
