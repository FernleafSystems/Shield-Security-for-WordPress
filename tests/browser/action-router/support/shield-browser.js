const {
	buildShieldUrl,
	dismissBlockingDialogs,
	expect,
	openShieldRoute,
	waitForShieldPage,
} = require( './shield-test' );

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

async function fetchShieldRenderedHtml( page, renderSlug, extraData = {} ) {
	return page.evaluate( async ( { currentRenderSlug, currentExtraData } ) => {
		const findAjaxRenderPayload = ( value ) => {
			if ( value && typeof value === 'object' ) {
				if ( value.ex === 'ajax_render' && typeof value.ajaxurl === 'string' ) {
					return value;
				}

				for ( const child of Object.values( value ) ) {
					const found = findAjaxRenderPayload( child );
					if ( found ) {
						return found;
					}
				}
			}

			return null;
		};

		const renderRequest = findAjaxRenderPayload( window.shield_vars_main?.comps ?? null );
		if ( !renderRequest ) {
			throw new Error( 'Missing browser-authenticated ajax_render payload for Shield render fetch.' );
		}

		const requestData = {
			...renderRequest,
			render_slug: currentRenderSlug,
			...currentExtraData,
		};
		delete requestData.limit;

		const response = await fetch( requestData.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: new URLSearchParams( requestData ),
		} );
		const payload = await response.json();

		if ( !response.ok || !payload?.data?.html ) {
			throw new Error( `Failed to load Shield render HTML for slug: ${currentRenderSlug}` );
		}

		return payload.data.html;
	}, {
		currentRenderSlug: renderSlug,
		currentExtraData: extraData,
	} );
}

module.exports = {
	buildShieldUrl,
	dismissBlockingDialogs,
	fetchShieldRenderedHtml,
	openShieldRoute,
	selectSelect2Option,
	waitForShieldPage,
};
