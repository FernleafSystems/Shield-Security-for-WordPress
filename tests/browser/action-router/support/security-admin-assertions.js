const { expect } = require( './shield-test' );
const { parseShieldAjaxJson } = require( './security-assertions' );

function assertSecurityAdminContract( contract, scenario ) {
	expect( contract ).toEqual( expect.objectContaining( {
		scenario,
		scenarios: expect.arrayContaining( [ scenario ] ),
		routes: expect.any( Object ),
		configure_focus: expect.any( Object ),
		action_slugs: expect.any( Object ),
		boundary_action_slugs: expect.any( Object ),
		boundary_actions: expect.any( Object ),
		render_slugs: expect.any( Object ),
		option_keys: expect.any( Array ),
		options: expect.any( Object ),
		pins: expect.any( Object ),
		timeout: expect.any( Object ),
		selectors: expect.any( Object ),
		expected: expect.any( Object ),
	} ) );
	expect( contract.action_slugs.sec_admin_login ).toBe( 'sec_admin_login' );
	expect( contract.action_slugs.sec_admin_check ).toBe( 'sec_admin_check' );
	expect( contract.action_slugs.sec_admin_auth_clear ).toBe( 'sec_admin_auth_clear' );
	expect( contract.action_slugs.module_options_save ).toBe( 'mod_options_save' );
	expect( contract.boundary_action_slugs.sec_admin_login ).toBe( 'sec_admin_login' );
	expect( contract.boundary_action_slugs.sec_admin_check ).toBe( 'sec_admin_check' );
	expect( contract.boundary_action_slugs.sec_admin_auth_clear ).toBe( 'sec_admin_auth_clear' );
	expect( contract.boundary_action_slugs.secadmin_remove_confirm ).toBe( 'secadmin_remove_confirm' );
	expect( contract.boundary_action_slugs.req_email_remove ).toBe( 'req_email_remove' );
	return contract;
}

async function expectSecurityAdminLocalisation( page, contract ) {
	const component = await page.evaluate( () => window.shield_vars_main?.comps?.sec_admin ?? null );
	expect( component ).toEqual( expect.objectContaining( {
		ajax: expect.any( Object ),
		flags: expect.any( Object ),
		vars: expect.any( Object ),
	} ) );
	expect( component.ajax.sec_admin_login.ex ).toBe( contract.boundary_action_slugs.sec_admin_login );
	expect( component.ajax.sec_admin_check.ex ).toBe( contract.boundary_action_slugs.sec_admin_check );
	expect( component.ajax.req_email_remove.ex ).toBe( contract.boundary_action_slugs.req_email_remove );
	expect( typeof component.flags.restrict_options ).toBe( 'boolean' );
	expect( typeof component.flags.run_checks ).toBe( 'boolean' );
	expect( typeof component.vars.time_remaining ).toBe( 'number' );
	expect( await page.evaluate( () => Boolean( window.shieldAppMain?.components?.sec_admin ) ) ).toBe( true );
	return component;
}

async function sendSecurityAdminAjax( page, ajaxKey, extraData = {} ) {
	const response = await page.evaluate( async ( { currentAjaxKey, currentExtraData } ) => {
		const requestData = window.shield_vars_main?.comps?.sec_admin?.ajax?.[ currentAjaxKey ];
		if ( !requestData || typeof requestData.ajaxurl !== 'string' ) {
			throw new Error( `Missing Security Admin AJAX request data for ${currentAjaxKey}.` );
		}
		if ( !new URL( requestData.ajaxurl, window.location.href ).pathname.endsWith( '/admin-ajax.php' ) ) {
			throw new Error( `Security Admin AJAX request data for ${currentAjaxKey} does not target admin-ajax.php.` );
		}

		const raw = await fetch( requestData.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest',
			},
			body: new URLSearchParams( {
				...requestData,
				...currentExtraData,
				apto_wrap_response: '1',
			} ),
		} );

		return {
			ok: raw.ok,
			body: await raw.text(),
		};
	}, {
		currentAjaxKey: ajaxKey,
		currentExtraData: extraData,
	} );

	expect( response.ok ).toBe( true );
	return parseShieldAjaxJson( response.body );
}

module.exports = {
	assertSecurityAdminContract,
	expectSecurityAdminLocalisation,
	sendSecurityAdminAjax,
};
