const { openShieldRoute, test, expect } = require( './support/shield-test' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );
const { expectNamedDialog, expectFocusWithin } = require( './support/modal-accessibility' );
const { expectNoAxeViolationsInDialog, parseShieldAjaxJson } = require( './support/security-assertions' );
const groups = '[data-drill-layer-key="groups"][aria-hidden="false"]';
const detail = '[data-drill-layer-key="detail"][aria-hidden="false"]';

async function openQueue( page, fixture ) {
	const queue = new ActionsQueuePage( page );
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'overview' } );
	await ( await queue.waitForBucket( fixture.bucket_key ) ).click();
	return queue;
}
async function openProtection( page, tile ) {
	const config = JSON.parse( await tile.getAttribute( 'data-enable-protection' ) );
	await tile.focus(); await page.keyboard.press( 'Enter' );
	const dialog = page.locator( '#ShieldMainAccessibleDialog' );
	await expectNamedDialog( page, dialog ); await expectFocusWithin( dialog );
	return { dialog, config, toggle: dialog.getByRole( 'switch' ),
		save: dialog.getByRole( 'button', { name: config.save_label, exact: true } ),
		cancel: dialog.getByRole( 'button', { name: config.cancel_label, exact: true } ) };
}
function isEnableRequest( request ) {
	const p = new URLSearchParams( request.postData() || '' );
	return request.method() === 'POST' && p.get( 'action' ) === 'shield_action' && p.get( 'ex' ) === 'scans_enable';
}
function failureResponse() {
	return { status: 200, contentType: 'application/json', body: JSON.stringify( { success: false, data: { page_reload: false } } ) };
}

test( 'premium protection tiles enable individual scans and files with accessible completion', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'scan_enablement', async fixture => {
		const queue = await openQueue( page, fixture );
		for ( const key of [ 'malware', 'plugins', 'themes', 'wordpress', 'vulnerabilities', 'abandoned' ] ) {
			const tile = await queue.waitForGroupOuter( key );
			let c = await openProtection( page, tile );
			await expect( c.save ).toBeDisabled();
			if ( key === 'malware' ) {
				await expectNoAxeViolationsInDialog( page, { dialog: 'ShieldMainAccessibleDialog' } );
				await page.keyboard.press( 'Escape' ); await expect( tile ).toBeFocused();
				c = await openProtection( page, tile ); await c.cancel.click(); await expect( tile ).toBeFocused();
				c = await openProtection( page, tile );
			}
			await c.toggle.check(); await c.save.click(); await expect( c.dialog ).not.toBeVisible();
			await expect( page.locator( '[data-actions-queue-group-key="' + key + '"][data-enable-protection]' ) ).toHaveCount( 0 );
			await expect( page.locator( groups ) ).toBeFocused();
		}
		await ( await queue.waitForGroupOuter( 'file_locker' ) ).click();
		const files = page.locator( '[data-actions-queue-asset-cards="1"]' );
		for ( let index = 0; index < 2; index++ ) {
			const c = await openProtection( page, files.locator( '[data-enable-protection]' ).first() );
			await c.toggle.check(); await c.save.click(); await expect( c.dialog ).not.toBeVisible();
			await expect.poll( () => files.locator( '[data-enable-protection]' ).evaluateAll(
				( tiles, key ) => tiles.some( el => JSON.parse( el.dataset.enableProtection ).action.file_key === key ), c.config.action.file_key
			) ).toBe( false );
			await expect( page.locator( detail ) ).toBeFocused();
		}
	} );
} );

for ( const failure of [ 'rejected', 'network' ] ) {
	test( 'protection save retains busy/error focus and permits retry: ' + failure, async ( { page, fixtureApi } ) => {
		await fixtureApi.withActionsQueueFixture( 'scan_enablement', async fixture => {
			const queue = await openQueue( page, fixture );
			const c = await openProtection( page, await queue.waitForGroupOuter( 'malware' ) );
			let release;
			const held = new Promise( resolve => { release = resolve; } );
			let requests = 0;
			let pending = Promise.resolve();
			const handler = async route => {
				if ( isEnableRequest( route.request() ) && ++requests === 1 ) {
					pending = ( async () => {
						await held;
						if ( failure === 'network' ) await route.abort( 'failed' );
						else await route.fulfill( failureResponse() );
					} )();
					await pending;
				} else await route.continue();
			};
			await page.route( '**/admin-ajax.php*', handler );
			try {
				await c.toggle.check(); await c.save.focus(); await page.keyboard.press( 'Enter' );
				await expect.poll( () => requests ).toBe( 1 );
				await expect( c.dialog ).toHaveAttribute( 'aria-busy', 'true' );
				const saving = c.dialog.getByRole( 'button', { name: c.config.saving_label, exact: true } );
				for ( const el of [ saving, c.cancel, c.toggle ] ) await expect( el ).toBeDisabled();
				await expectFocusWithin( c.dialog );
				await page.keyboard.press( 'Escape' ); await expect( c.dialog ).toBeVisible();
				release();
				await expect( c.dialog.locator( '[role="alert"]:visible' ) ).toHaveText( /\S/ );
				for ( const el of [ c.save, c.cancel, c.toggle ] ) await expect( el ).toBeEnabled();
				await expect( c.save ).toBeFocused();
				await c.save.click(); await expect( c.dialog ).not.toBeVisible();
				await expect( page.locator( groups ) ).toBeFocused();
				await expect.poll( () => requests ).toBe( 2 );
			} finally { release(); await pending; await page.unroute( '**/admin-ajax.php*', handler ); }
		} );
	} );
}

for ( const outcome of [ 'removed', 'refresh_failure', 'focus_moved' ] ) {
	test( 'protection refresh preserves a connected focus destination: ' + outcome, async ( { page, fixtureApi } ) => {
		await fixtureApi.withActionsQueueFixture( 'scan_enablement', async fixture => {
			const queue = await openQueue( page, fixture );
			const c = await openProtection( page, await queue.waitForGroupOuter( 'malware' ) );
			let release;
			const held = new Promise( resolve => { release = resolve; } );
			let started = false;
			let finished = false;
			let pending = Promise.resolve();
			const handler = async route => {
				const params = new URLSearchParams( route.request().postData() || '' );
				if ( !started && params.get( 'render_slug' ) === 'actions_queue_drill_down_groups' ) {
					started = true;
					pending = ( async () => {
					await held;
					if ( outcome === 'refresh_failure' ) await route.fulfill( failureResponse() );
					else {
						const response = await route.fetch(); const body = parseShieldAjaxJson( await response.text() );
						if ( outcome === 'removed' ) body.data.landing_refresh.has_drilldown_content = false;
						await route.fulfill( { response, json: body } );
					}
					finished = true;
					} )();
					await pending;
				} else await route.continue();
			};
			await page.route( '**/admin-ajax.php*', handler );
			try {
				await c.toggle.check(); await c.save.click(); await expect( c.dialog ).not.toBeVisible();
				await expect.poll( () => started ).toBe( true );
				const other = page.locator( '[data-dashboard-task-guide-launch="1"]' ).first();
				if ( outcome === 'focus_moved' ) await other.focus();
				release(); await expect.poll( () => finished ).toBe( true );
				if ( outcome === 'removed' ) await expect( page.locator( '#PageContainer-Apto' ) ).toBeFocused();
				else {
					await expect( page.locator( groups ) ).toHaveAttribute( 'aria-busy', 'false' );
					if ( outcome === 'focus_moved' ) await expect( other ).toBeFocused();
					else await expect( page.locator( groups ) ).toBeFocused();
				}
			} finally { release(); await pending; await page.unroute( '**/admin-ajax.php*', handler ); }
		} );
	} );
}
