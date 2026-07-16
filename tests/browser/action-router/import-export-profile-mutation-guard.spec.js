const { test, expect, openShieldRoute } = require( './support/shield-test' );

const PROFILE_MUTATION_SLUG = Object.freeze( {
	save: 'importexport_profile_options_save',
	option: 'importexport_profile_option_include_toggle',
	group: 'importexport_profile_options_include_toggle',
	copy: 'importexport_profile_copy_from_master',
} );
const PROFILE_MUTATION_SLUGS = new Set( Object.values( PROFILE_MUTATION_SLUG ) );
const PROFILE_CLIENT_FIXTURE_ARGS = [ 'profile-client' ];
const ACTIVE_DIALOG_SELECTOR = '[data-shield-accessible-dialog="1"][aria-modal="true"]:not([aria-hidden="true"])';
const DIALOG_CONFIRM_SELECTOR = '.shield-accessible-dialog__confirm';

const ORIGIN_CASES = [
	{
		name: 'profile Save owns the shared mutation guard',
		origin: 'save',
		expectedSlug: PROFILE_MUTATION_SLUG.save,
		secondary: 'option',
	},
	{
		name: 'single-option toggle owns the shared mutation guard',
		origin: 'option',
		expectedSlug: PROFILE_MUTATION_SLUG.option,
		secondary: 'group',
	},
	{
		name: 'group toggle owns the shared mutation guard',
		origin: 'group',
		expectedSlug: PROFILE_MUTATION_SLUG.group,
		secondary: 'save',
	},
	{
		name: 'profile Copy owns the shared mutation guard',
		origin: 'copy',
		expectedSlug: PROFILE_MUTATION_SLUG.copy,
		secondary: 'save',
	},
];

function profileMutationSlug( request ) {
	if ( request.method() !== 'POST' || !request.url().includes( '/admin-ajax.php' ) ) {
		return null;
	}

	const params = new URLSearchParams( request.postData() || '' );
	const slug = params.get( 'action' ) === 'shield_action' ? params.get( 'ex' ) : null;
	return PROFILE_MUTATION_SLUGS.has( slug ) ? slug : null;
}

function mutationResponse( success ) {
	return {
		success,
		data: {
			message: success ? 'Profile mutation completed.' : 'Profile mutation rejected.',
			page_reload: false,
			show_toast: false,
		},
	};
}

async function installProfileMutationGate( page, expectedSlug, success = true ) {
	let releaseResolve;
	let startedResolve;
	let completedResolve;
	let released = false;
	let startedSettled = false;
	let completedSettled = false;
	let expectedRequestStarted = false;
	const seen = [];
	const inFlight = new Set();
	const releaseGate = new Promise( ( resolve ) => {
		releaseResolve = resolve;
	} );
	const started = new Promise( ( resolve ) => {
		startedResolve = resolve;
	} );
	const completed = new Promise( ( resolve ) => {
		completedResolve = resolve;
	} );
	const settleStarted = () => {
		if ( !startedSettled ) {
			startedSettled = true;
			startedResolve();
		}
	};
	const settleCompleted = ( error ) => {
		if ( !completedSettled ) {
			completedSettled = true;
			completedResolve( error );
		}
	};

	const handleRoute = async ( route ) => {
		const slug = profileMutationSlug( route.request() );
		if ( slug === null ) {
			await route.continue();
			return;
		}

		seen.push( slug );
		const isGatedRequest = slug === expectedSlug && !expectedRequestStarted;
		if ( isGatedRequest ) {
			expectedRequestStarted = true;
			settleStarted();
			await releaseGate;
		}

		try {
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( mutationResponse( success ) ),
			} );
			if ( isGatedRequest ) {
				settleCompleted( null );
			}
		}
		catch ( error ) {
			if ( isGatedRequest ) {
				settleCompleted( error );
			}
			throw error;
		}
	};

	const handler = ( route ) => {
		const task = handleRoute( route );
		inFlight.add( task );
		task.then(
			() => inFlight.delete( task ),
			() => inFlight.delete( task )
		);
		return task;
	};

	const release = () => {
		if ( !released ) {
			released = true;
			releaseResolve();
		}
	};

	await page.route( '**/admin-ajax.php*', handler );

	return {
		seen,
		started,
		release,
		async waitForCompletion() {
			const error = await completed;
			if ( error !== null ) {
				throw error;
			}
		},
		async dispose() {
			release();
			while ( inFlight.size > 0 ) {
				await Promise.allSettled( Array.from( inFlight ) );
			}
			settleStarted();
			settleCompleted( null );
			await page.unroute( '**/admin-ajax.php*', handler );
		},
	};
}

async function openProfileSurface( page ) {
	await openShieldRoute( page, {
		nav: 'tools',
		nav_sub: 'importexport',
	} );

	await page.locator( '[data-import-export-task="profile"]' ).click();
	const pane = page.locator( '[data-import-export-pane="profile"]' );
	const form = pane.locator( 'form.import-export-profile-options-form' );
	const save = form.locator( 'button[type="submit"]' );
	const copy = pane.locator( '[data-import-export-profile-copy-from-master]' );
	const options = form.locator( '[data-import-export-profile-sync-toggle]' );
	const groups = form.locator( '[data-import-export-profile-group-sync-toggle]' );
	const firstGroup = form.locator( '[data-import-export-profile-group="1"]' ).first();
	const firstGroupDisclosure = firstGroup.locator( '[data-bs-toggle="collapse"][aria-controls]' ).first();
	const firstOption = firstGroup.locator( '[data-import-export-profile-sync-toggle="1"]' ).first();

	await expect( pane ).toBeVisible();
	await expect( form ).toBeVisible();
	await expect( save ).toBeVisible();
	await expect( copy ).toBeVisible();
	expect( await options.count() ).toBeGreaterThan( 0 );
	expect( await groups.count() ).toBeGreaterThan( 0 );

	return { page, pane, form, save, copy, options, groups, firstGroupDisclosure, firstOption };
}

async function revealFirstOption( surface ) {
	if ( !( await surface.firstOption.isVisible() ) ) {
		await surface.firstGroupDisclosure.click();
		await expect( surface.firstGroupDisclosure ).toHaveAttribute( 'aria-expanded', 'true' );
	}
	await expect( surface.firstOption ).toBeVisible();
}

async function captureControlState( surface ) {
	return {
		save: await surface.save.evaluate( ( button ) => button.disabled ),
		copy: await surface.copy.evaluate( ( button ) => button.disabled ),
		options: await surface.options.evaluateAll( ( buttons ) => buttons.map( ( button ) => ( {
			key: button.dataset.key,
			disabled: button.disabled,
		} ) ) ),
		groups: await surface.groups.evaluateAll( ( buttons ) => buttons.map( ( button ) => ( {
			keys: button.dataset.keys,
			disabled: button.disabled,
		} ) ) ),
	};
}

function expectAllControlsEnabled( state ) {
	expect( state.save ).toBe( false );
	expect( state.copy ).toBe( false );
	expect( state.options.every( ( option ) => (
		typeof option.key === 'string' && option.key.length > 0 && !option.disabled
	) ) ).toBe( true );
	expect( state.groups.every( ( group ) => (
		typeof group.keys === 'string' && group.keys.length > 0 && !group.disabled
	) ) ).toBe( true );
}

async function expectMutationBusy( surface ) {
	await expect( surface.form ).toHaveAttribute( 'aria-busy', 'true' );
	await expect( surface.save ).toBeDisabled();
	await expect( surface.copy ).toBeDisabled();
	expect( await surface.options.evaluateAll( ( buttons ) => buttons.every( ( button ) => button.disabled ) ) ).toBe( true );
	expect( await surface.groups.evaluateAll( ( buttons ) => buttons.every( ( button ) => button.disabled ) ) ).toBe( true );
}

async function expectMutationRestored( surface, before ) {
	await expect.poll( async () => surface.form.getAttribute( 'aria-busy' ) ).toBeNull();
	expect( await captureControlState( surface ) ).toEqual( before );
	await expect( surface.page.locator( ACTIVE_DIALOG_SELECTOR ) ).toHaveCount( 0 );
}

async function triggerOrigin( surface, origin ) {
	switch ( origin ) {
		case 'save':
			await surface.save.click();
			break;
		case 'option':
			await revealFirstOption( surface );
			await surface.firstOption.click();
			break;
		case 'group':
			await surface.groups.first().click();
			break;
		case 'copy': {
			await surface.copy.click();
			const dialog = surface.page.locator( ACTIVE_DIALOG_SELECTOR );
			await expect( dialog ).toBeVisible();
			await dialog.locator( DIALOG_CONFIRM_SELECTOR ).click();
			break;
		}
		default:
			throw new Error( `Unknown profile mutation origin: ${ origin }` );
	}
}

async function dispatchSecondaryMutation( surface, secondary ) {
	switch ( secondary ) {
		case 'save':
			await surface.form.dispatchEvent( 'submit' );
			break;
		case 'option':
			await surface.firstOption.dispatchEvent( 'click' );
			break;
		case 'group':
			await surface.groups.first().dispatchEvent( 'click' );
			break;
		default:
			throw new Error( `Unknown secondary profile mutation: ${ secondary }` );
	}
}

async function expectNoAdditionalMutation( page, gate, attempt ) {
	const beforeCount = gate.seen.length;
	const unexpectedRequest = page.waitForRequest(
		( request ) => profileMutationSlug( request ) !== null,
		{ timeout: 500 }
	)
	.then( () => true )
	.catch( () => false );

	await attempt();
	expect( await unexpectedRequest ).toBe( false );
	expect( gate.seen ).toHaveLength( beforeCount );
}

for ( const originCase of ORIGIN_CASES ) {
	test( originCase.name, async ( { page, fixtureApi } ) => {
		await fixtureApi.withImportExportNetworkFixture( async () => {
			const surface = await openProfileSurface( page );
			const before = await captureControlState( surface );
			expectAllControlsEnabled( before );
			const gate = await installProfileMutationGate( page, originCase.expectedSlug );

			try {
				await triggerOrigin( surface, originCase.origin );
				await gate.started;
				expect( gate.seen ).toEqual( [ originCase.expectedSlug ] );
				await expectMutationBusy( surface );
				await expectNoAdditionalMutation(
					page,
					gate,
					() => dispatchSecondaryMutation( surface, originCase.secondary )
				);

				gate.release();
				await gate.waitForCompletion();
				await expectMutationRestored( surface, before );
			}
			finally {
				await gate.dispose();
			}
		}, PROFILE_CLIENT_FIXTURE_ARGS );
	} );
}

test( 'application failure restores exact profile mutation control state', async ( { page, fixtureApi } ) => {
	await fixtureApi.withImportExportNetworkFixture( async () => {
		const surface = await openProfileSurface( page );
		await surface.copy.evaluate( ( button ) => {
			button.disabled = true;
		} );
		const before = await captureControlState( surface );
		expect( before.copy ).toBe( true );
		const gate = await installProfileMutationGate(
			page,
			PROFILE_MUTATION_SLUG.option,
			false
		);

		try {
			await revealFirstOption( surface );
			await surface.firstOption.click();
			await gate.started;
			expect( gate.seen ).toEqual( [ PROFILE_MUTATION_SLUG.option ] );
			await expectMutationBusy( surface );

			gate.release();
			await gate.waitForCompletion();
			await expectMutationRestored( surface, before );
		}
		finally {
			await gate.dispose();
		}
	}, PROFILE_CLIENT_FIXTURE_ARGS );
} );

test( 'Copy confirmation cannot acquire a profile mutation guard already held by another action', async ( { page, fixtureApi } ) => {
	await fixtureApi.withImportExportNetworkFixture( async () => {
		const surface = await openProfileSurface( page );
		const before = await captureControlState( surface );
		expectAllControlsEnabled( before );

		await surface.copy.click();
		const dialog = page.locator( ACTIVE_DIALOG_SELECTOR );
		await expect( dialog ).toBeVisible();

		const gate = await installProfileMutationGate(
			page,
			PROFILE_MUTATION_SLUG.option
		);
		try {
			// Keep the Copy modal open while establishing the competing mutation state behind its overlay.
			await surface.firstOption.dispatchEvent( 'click' );
			await gate.started;
			expect( gate.seen ).toEqual( [ PROFILE_MUTATION_SLUG.option ] );
			await expectMutationBusy( surface );
			await expectNoAdditionalMutation(
				page,
				gate,
				() => dialog.locator( DIALOG_CONFIRM_SELECTOR ).click()
			);

			gate.release();
			await gate.waitForCompletion();
			await expectMutationRestored( surface, before );
		}
		finally {
			await gate.dispose();
		}
	}, PROFILE_CLIENT_FIXTURE_ARGS );
} );
