const { test, expect } = require( './support/shield-test' );
const { openShieldRoute } = require( './support/shield-browser' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );

async function expectVisibleFocusIndicator( focusTarget, styleTarget = focusTarget ) {
	const baselineStyle = await styleTarget.evaluate( readFocusStyle );
	await focusTarget.focus();
	await expect( focusTarget ).toBeFocused();

	const focusStyle = await styleTarget.evaluate( readFocusStyle );

	const outlineWidth = Number.parseFloat( focusStyle.outlineWidth || '0' );
	const hasOutline = focusStyle.outlineStyle !== 'none' && outlineWidth > 0;
	const hasChangedShadow = focusStyle.boxShadow !== 'none'
		&& focusStyle.boxShadow !== baselineStyle.boxShadow;

	expect( hasOutline || hasChangedShadow, JSON.stringify( focusStyle ) ).toBe( true );
}

function readFocusStyle( node ) {
	const style = window.getComputedStyle( node );
	return {
		boxShadow: style.boxShadow,
		outlineStyle: style.outlineStyle,
		outlineWidth: style.outlineWidth,
	};
}

function readActionVisualState( node ) {
	const style = window.getComputedStyle( node );
	return {
		backgroundColor: style.backgroundColor,
		borderColor: style.borderColor,
		color: style.color,
	};
}

async function waitForActionBackgroundTransition( action ) {
	return action.evaluate( ( node ) => new Promise( ( resolve ) => {
		let timeoutId;
		const onTransitionEnd = ( event ) => {
			if ( event.target === node && event.propertyName === 'background-color' ) {
				finish();
			}
		};
		const finish = () => {
			window.clearTimeout( timeoutId );
			node.removeEventListener( 'transitionend', onTransitionEnd );
			resolve();
		};

		node.addEventListener( 'transitionend', onTransitionEnd );
		timeoutId = window.setTimeout( finish, 250 );
	} ) );
}

async function waitForDataTableReady( table ) {
	await expect( table ).toBeVisible();
	await expect.poll( async () => table.evaluate( ( element ) => {
		return Boolean( globalThis.jQuery?.fn?.dataTable?.isDataTable?.( element ) );
	} ), { timeout: 20_000 } ).toBe( true );
}

test( 'admin and operator controls expose the shared focus indicator', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
	} );

	await expectVisibleFocusIndicator(
		page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]:visible' ).first()
	);

	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const investigateCard = page.locator( '[data-investigate-landing="1"] [data-drill-target="panel"]:visible' ).first();
	await expectVisibleFocusIndicator(
		investigateCard.locator( '[data-investigate-primary-action="1"]' ).first(),
		investigateCard
	);
} );

test( 'IP secondary action takes priority over the default action on hover', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const ipCard = page.locator( '[data-investigate-subject="ip"]' ).first();
	const primaryAction = ipCard.locator( '[data-investigate-primary-action="1"]' ).first();
	const secondaryAction = ipCard.locator( '[data-investigate-manage-ip-rules="1"]' ).first();

	const primaryHoverTransition = waitForActionBackgroundTransition( primaryAction );
	await primaryAction.hover();
	await primaryHoverTransition;
	const primaryHoverState = await primaryAction.evaluate( readActionVisualState );

	const secondaryHoverTransitions = Promise.all( [
		waitForActionBackgroundTransition( primaryAction ),
		waitForActionBackgroundTransition( secondaryAction ),
	] );
	await secondaryAction.hover();
	await secondaryHoverTransitions;
	const primaryDuringSecondaryHover = await primaryAction.evaluate( readActionVisualState );
	const secondaryHoverState = await secondaryAction.evaluate( readActionVisualState );

	expect( primaryDuringSecondaryHover ).not.toEqual( primaryHoverState );
	expect( secondaryHoverState ).toEqual( primaryHoverState );
} );

test( 'datatable controls expose the shared focus indicator', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );

		const table = page.locator( '[data-actions-queue-detail="1"] [data-scan-results-table="1"]' ).first();
		await waitForDataTableReady( table );

		const dataTableContainer = table.locator( 'xpath=ancestor::div[contains(@class,"dt-container")]' ).first();
		await expectVisibleFocusIndicator(
			dataTableContainer.locator( '.dt-search input' ).first()
		);
	} );
} );

test( 'user-profile MFA controls expose the shared focus indicator', async ( { page, fixtureApi } ) => {
	await fixtureApi.withMfaProfileFixture( async ( fixture ) => {
		await page.goto( fixture.profile_path, { waitUntil: 'load' } );
		await expect( page.locator( '#ShieldUserProfileMFA' ) ).toBeVisible( { timeout: 20_000 } );

		await expectVisibleFocusIndicator(
			page.locator( '#ShieldUserProfileMFA .shield-gen-backup-login-code' ).first()
		);
	} );
} );
