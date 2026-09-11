const { test, expect } = require( './support/shield-test' );
const {
	collectRuntimeErrors,
	expectNoRuntimeErrors,
	isAdminAjaxRequest,
	requestActionSlug,
} = require( './support/security-assertions' );

test.setTimeout( 180_000 );

test( 'background IP detect ignores auth-refresh UI and navigation on a non-Shield admin page', async ( {
	page,
	fixtureApi,
} ) => {
	await fixtureApi.withIpDetectBackgroundFixture( async ( contract ) => {
		const runtimeErrors = collectRuntimeErrors( page );
		const forcedResponse = {
			success: true,
			data: {
				auth_refresh_required: true,
				page_reload: true,
				show_toast: true,
				message: 'Background response must remain silent.',
				ip_source: 'REMOTE_ADDR',
			},
		};
		let releaseWorkerResponse;
		const workerResponseGate = new Promise( ( resolve ) => {
			releaseWorkerResponse = resolve;
		} );
		let dialogCount = 0;
		let navigationAttempts = 0;

		page.on( 'dialog', async ( dialog ) => {
			dialogCount++;
			await dialog.dismiss();
		} );

		const workerUrl = /https:\/\/ip-detect\.workers\.aptoweb\.com\/?$/;
		const workerHandler = async ( route ) => {
			await workerResponseGate;
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				headers: {
					'Access-Control-Allow-Origin': '*',
				},
				body: JSON.stringify( {
					ip: contract.detected_ip,
				} ),
			} );
		};
		const ajaxHandler = async ( route ) => {
			const request = route.request();
			if ( !isAdminAjaxRequest( request ) || requestActionSlug( request ) !== contract.action_slug ) {
				await route.continue();
				return;
			}

			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( forcedResponse ),
			} );
		};
		const navigationHandler = async ( route ) => {
			const request = route.request();
			if ( request.isNavigationRequest() && request.frame() === page.mainFrame() ) {
				await route.abort( 'aborted' );
				return;
			}
			await route.continue();
		};
		const pagesDocumentUrl = ( url ) => url.pathname.endsWith( '/wp-admin/edit.php' )
			&& url.searchParams.get( 'post_type' ) === 'page';

		await page.route( workerUrl, workerHandler );
		const workerRequest = page.waitForRequest( workerUrl );

		try {
			await page.clock.install();
			await page.goto( contract.routes.pages_admin, { waitUntil: 'load' } );
			await expect( page.locator( 'body.edit-php' ) ).toBeVisible( { timeout: 30_000 } );
			await workerRequest;
			await expect( page.locator( '#PageContainer-Apto' ) ).toHaveCount( 0 );

			const ipDetectState = await page.evaluate( () => {
				const comp = window.shield_vars_wpadmin?.comps?.ip_detect;
				return {
					has_wpadmin_localization: !!window.shield_vars_wpadmin,
					has_component: !!comp,
					is_check_required: comp?.flags?.is_check_required ?? false,
					action_slug: comp?.ajax?.ex ?? '',
				};
			} );
			expect( ipDetectState ).toEqual( {
				has_wpadmin_localization: true,
				has_component: true,
				is_check_required: true,
				action_slug: contract.action_slug,
			} );

			const probeState = await page.evaluate( () => {
				const component = window.shieldAppMain?.getComponent?.( 'ip_detect' );
				const componentData = component?.retrieveBaseData?.();
				const localizedData = window.shield_vars_wpadmin?.comps?.ip_detect;
				const quiet = componentData?.flags?.quiet;
				const state = {
					has_overlay_api: typeof window.JsLoadingOverlay?.show === 'function',
					has_component: !!component,
					uses_localized_data: componentData === localizedData,
					quiet,
				};
				if ( !state.has_overlay_api || !state.has_component || !state.uses_localized_data || quiet !== true ) {
					return state;
				}

				window.__shieldBackgroundIpDetectProbe = {
					overlayCalls: 0,
					callerContinuationObserved: false,
				};
				const originalShow = window.JsLoadingOverlay.show;
				window.JsLoadingOverlay.show = function ( ...args ) {
					window.__shieldBackgroundIpDetectProbe.overlayCalls++;
					return originalShow.apply( this, args );
				};
				Object.defineProperty( componentData.flags, 'quiet', {
					configurable: true,
					enumerable: true,
					get() {
						window.__shieldBackgroundIpDetectProbe.callerContinuationObserved = true;
						return quiet;
					},
				} );
				return state;
			} );
			expect( probeState ).toEqual( {
				has_overlay_api: true,
				has_component: true,
				uses_localized_data: true,
				quiet: true,
			} );

			page.on( 'request', ( request ) => {
				if ( request.isNavigationRequest() && request.frame() === page.mainFrame() ) {
					navigationAttempts++;
				}
			} );
			await page.route( pagesDocumentUrl, navigationHandler );
			await page.route( '**/admin-ajax.php**', ajaxHandler );

			const pagesUrl = page.url();
			const backgroundResponse = page.waitForResponse( ( response ) => {
				const request = response.request();
				return isAdminAjaxRequest( request )
					&& requestActionSlug( request ) === contract.action_slug;
			} );
			releaseWorkerResponse();

			const response = await backgroundResponse;
			expect( await response.finished() ).toBeNull();
			expect( await response.json() ).toEqual( forcedResponse );
			expect( response.request().headers()[ 'x-shield-auth-refresh' ] || '' ).toBe( '' );
			await expect.poll( () => page.evaluate(
				() => window.__shieldBackgroundIpDetectProbe?.callerContinuationObserved ?? false
			) ).toBe( true );

			// Drive any ordinary response-driven reload timer without wall-clock waiting.
			await page.clock.runFor( 2100 );
			await page.evaluate( () => Promise.resolve() );

			expect( await page.evaluate(
				() => window.__shieldBackgroundIpDetectProbe?.overlayCalls ?? -1
			) ).toBe( 0 );
			expect( navigationAttempts ).toBe( 0 );
			expect( page.url() ).toBe( pagesUrl );
			expect( dialogCount ).toBe( 0 );
			await expect( page.locator( contract.selectors.overlay ) ).toHaveCount( 0 );
			await expect( page.locator( contract.selectors.spinner ) ).toHaveCount( 0 );
			await expect( page.locator( contract.selectors.toast ) ).toHaveCount( 0 );
			await expect( page.locator( '#ShieldWpAdminAccessibleDialog[open]' ) ).toHaveCount( 0 );
			await expectNoRuntimeErrors( runtimeErrors, 'ip-detect background wp-admin response' );
		}
		finally {
			releaseWorkerResponse();
			await page.unroute( workerUrl, workerHandler ).catch( () => null );
			await page.unroute( '**/admin-ajax.php**', ajaxHandler ).catch( () => null );
			await page.unroute( pagesDocumentUrl, navigationHandler ).catch( () => null );
		}
	} );
} );
