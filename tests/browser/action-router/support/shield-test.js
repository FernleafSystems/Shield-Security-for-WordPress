const base = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const fs = require( 'fs' );
const path = require( 'path' );

const SHIELD_PAGE = 'icwp-wpsf-plugin';

function buildShieldUrl( params = {} ) {
	const search = new URLSearchParams( {
		page: SHIELD_PAGE,
		...params,
	} );

	return `/wp-admin/admin.php?${search.toString()}`;
}

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

async function waitForShieldPage( page ) {
	await dismissBlockingDialogs( page );
	await base.expect( page.locator( '#PageContainer-Apto' ) ).toBeVisible();
	await base.expect( page.locator( '#PageMain-Shield' ) ).toBeVisible();
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

async function createFixtureApi( playwright, lane, authStatePath ) {
	const request = await playwright.request.newContext( {
		baseURL: lane.baseUrl,
		storageState: authStatePath,
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
			async withImportExportFileFixture( runScenario ) {
				let seeded = false;
				try {
					const contract = await runFixture( 'import-export-file', 'seed' );
					seeded = true;
					return await runScenario( contract );
				}
				finally {
					if ( seeded ) {
						await runFixture( 'import-export-file', 'cleanup' );
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
			async inspectIpAnalysisActivityMetaFixture() {
				return runFixture( 'ip-analysis-activity-meta', 'inspect' );
			},
			async withIpRulesTableFixture( runScenario ) {
				let seeded = false;
				try {
					const contract = await runFixture( 'ip-rules-table', 'seed' );
					seeded = true;
					return await runScenario( contract );
				}
				finally {
					if ( seeded ) {
						await runFixture( 'ip-rules-table', 'cleanup' );
					}
				}
			},
			async inspectIpRulesTableFixture() {
				return runFixture( 'ip-rules-table', 'inspect' );
			},
			async withMainwpSitesFixture( runScenario ) {
				let seeded = false;
				try {
					const contract = await runFixture( 'mainwp-sites', 'seed' );
					seeded = true;
					return await runScenario( contract );
				}
				finally {
					if ( seeded ) {
						await runFixture( 'mainwp-sites', 'cleanup' );
					}
				}
			},
			async withMerlinWelcomeFixture( runScenario ) {
				let seeded = false;
				try {
					const contract = await runFixture( 'merlin-welcome', 'seed' );
					seeded = true;
					return await runScenario( contract );
				}
				finally {
					if ( seeded ) {
						await runFixture( 'merlin-welcome', 'cleanup' );
					}
				}
			},
			async withMfaProfileFixture( runScenario ) {
				let seeded = false;
				try {
					const contract = await runFixture( 'mfa-profile', 'seed' );
					seeded = true;
					return await runScenario( contract );
				}
				finally {
					if ( seeded ) {
						await runFixture( 'mfa-profile', 'cleanup' );
					}
				}
			},
			async inspectNotBotAltchaFixture() {
				return runFixture( 'notbot-altcha', 'inspect' );
			},
			async withNotBotAltchaFixture( ipOrRunScenario, maybeRunScenario ) {
				let seeded = false;
				const runScenario = typeof ipOrRunScenario === 'function' ? ipOrRunScenario : maybeRunScenario;
				const args = typeof ipOrRunScenario === 'function' ? [] : [ String( ipOrRunScenario ) ];
				try {
					const contract = await runFixture( 'notbot-altcha', 'seed', args );
					seeded = true;
					return await runScenario( contract );
				}
				finally {
					if ( seeded ) {
						await runFixture( 'notbot-altcha', 'cleanup' );
					}
				}
			},
			async withPublicBlockRecoveryFixture( scenario, runScenario ) {
				let seeded = false;
				try {
					const contract = await runFixture( 'public-block-recovery', 'seed', [ scenario ] );
					seeded = true;
					return await runScenario( contract );
				}
				finally {
					if ( seeded ) {
						await runFixture( 'public-block-recovery', 'cleanup' );
					}
				}
			},
			async withSecurityAdminFixture( runScenario ) {
				let seeded = false;
				try {
					const contract = await runFixture( 'security-admin', 'seed' );
					seeded = true;
					return await runScenario( contract );
				}
				finally {
					if ( seeded ) {
						await runFixture( 'security-admin', 'cleanup' );
					}
				}
			},
			async withSecurityHeadersFixture( runScenario ) {
				let seeded = false;
				try {
					const contract = await runFixture( 'security-headers', 'seed' );
					seeded = true;
					return await runScenario( contract );
				}
				finally {
					if ( seeded ) {
						await runFixture( 'security-headers', 'cleanup' );
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
		async ( { playwright, lane, authStatePath }, use ) => {
			const api = await createFixtureApi( playwright, lane, authStatePath );
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
		if ( !fs.existsSync( authStatePath ) ) {
			await loginAndWriteStorageState( browser, lane );
		}
		const context = await browser.newContext( {
			baseURL: lane.baseUrl,
			storageState: authStatePath,
		} );
		await use( context );
		await context.close();
	},
} );

module.exports = {
	AxeBuilder,
	buildShieldUrl,
	dismissBlockingDialogs,
	expect: base.expect,
	openShieldRoute,
	test,
	waitForShieldPage,
};
