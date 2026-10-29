const { openShieldRoute, test, expect } = require( './support/shield-test' );
const { expectShieldAjaxSuccess, waitForShieldAjaxAction } = require( './support/security-assertions' );
const { expectNoAxeViolations } = require( './support/accessibility' );

/**
 * Configure topics are laid out as panels of purpose-built controls. These check that each kind of
 * control changes, saves and reloads its option like any other form field.
 */

const diagnosis = ( page ) => page.locator( '[data-configure-diagnosis="1"]' );
const saveBar = ( page ) => diagnosis( page ).locator( '[data-configure-savebar="1"]' );

async function openGoal( page, goal ) {
	await openShieldRoute( page, { nav: 'zones', nav_sub: 'overview', zone: goal } );
	await expect( diagnosis( page ) ).toHaveAttribute( 'data-configure-zone', goal );
	const expandLayout = diagnosis( page ).locator( '[data-configure-zone-layout="expand"]' );
	if ( await expandLayout.getAttribute( 'aria-pressed' ) !== 'true' ) {
		const saved = waitForShieldAjaxAction( page, 'set_ui_preference' );
		await expandLayout.click();
		await expectShieldAjaxSuccess( await saved );
	}
}

async function openTopic( page, topic ) {
	const trigger = diagnosis( page ).locator( `[data-configure-row-key="${topic}"] [data-shield-expand-trigger="1"]` );
	if ( await trigger.getAttribute( 'aria-expanded' ) !== 'true' ) {
		await trigger.click();
	}
	const form = diagnosis( page ).locator( `[data-configure-row-key="${topic}"] form.options_form_for--panel` );
	await expect( form ).toBeVisible();
	return form;
}

async function save( page ) {
	const saved = waitForShieldAjaxAction( page, 'mod_options_save' );
	await saveBar( page ).locator( '[data-configure-savebar-save="1"]' ).click();
	await expectShieldAjaxSuccess( await saved );
	await expect( saveBar( page ) ).toBeHidden( { timeout: 20_000 } );
}

test( 'configure home lists the protection goals', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'zones', nav_sub: 'overview' } );
	const goals = page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' );
	await expect( goals.first() ).toBeVisible();
	await expect( page.locator( '[data-configure-landing="1"] [data-drill-zone-selection*="\\"key\\":\\"accounts\\""]' ) ).toBeVisible();
	await expect( page.locator( '[data-configure-landing="1"] [data-drill-zone-selection*="\\"key\\":\\"secadmin\\""]' ) ).toHaveCount( 0 );
} );

test( 'a legacy zone link opens the goal that now holds its settings', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'zones', nav_sub: 'overview', zone: 'users' } );
	await expect( diagnosis( page ) ).toHaveAttribute( 'data-configure-zone', 'accounts' );
} );

test( 'a panel slider and switch save and reload like any other setting', async ( { page } ) => {
	await openGoal( page, 'accounts' );
	let form = await openTopic( page, 'sessions' );

	const lifetime = form.locator( '[data-option-key="session_timeout_interval"]' );
	const originalLifetime = await lifetime.locator( 'input[type="radio"]:checked' ).getAttribute( 'value' );
	const nextLifetime = originalLifetime === '7' ? '14' : '7';
	await lifetime.locator( `label:has(input[value="${nextLifetime}"])` ).click();
	await expect( lifetime.locator( '[data-slider-value="1"]' ) ).toContainText( nextLifetime );
	await expect( lifetime.locator( '[data-option-unsaved="1"]' ) ).toBeVisible();

	const notify = form.locator( 'input[name="enable_user_login_email_notification"]' );
	const notifyWasOn = await notify.isChecked();
	await form.locator( '[data-option-key="enable_user_login_email_notification"] .form-switch' ).click();

	await save( page );
	form = await openTopic( page, 'sessions' );
	await expect( form.locator( `[data-option-key="session_timeout_interval"] input[value="${nextLifetime}"]` ) ).toBeChecked();
	await expect( form.locator( 'input[name="enable_user_login_email_notification"]' ) ).toBeChecked( { checked: !notifyWasOn } );

	// Put things back.
	await form.locator( `[data-option-key="session_timeout_interval"] label:has(input[value="${originalLifetime}"])` ).click();
	await form.locator( '[data-option-key="enable_user_login_email_notification"] .form-switch' ).click();
	await save( page );
} );

test( 'role chips save the ticked roles, including none', async ( { page } ) => {
	await openGoal( page, 'accounts' );
	let form = await openTopic( page, 'two_factor' );
	const roles = form.locator( '[data-option-key="two_factor_auth_user_roles"]' );
	const original = await roles.locator( 'input[type="checkbox"]:checked' ).evaluateAll( ( inputs ) => inputs.map( ( i ) => i.value ) );

	// Tick only Administrator.
	await roles.locator( 'input[type="checkbox"]' ).evaluateAll( ( inputs ) => inputs.forEach( ( input ) => {
		if ( input.checked !== ( input.value === 'administrator' ) ) {
			input.click();
		}
	} ) );
	await save( page );

	form = await openTopic( page, 'two_factor' );
	await expect.poll( async () => form.locator( '[data-option-key="two_factor_auth_user_roles"] input[type="checkbox"]:checked' )
		.evaluateAll( ( inputs ) => inputs.map( ( i ) => i.value ) ) ).toEqual( [ 'administrator' ] );

	// No roles at all is a choice too.
	await form.locator( '[data-option-key="two_factor_auth_user_roles"] input[type="checkbox"]:checked' ).evaluateAll( ( inputs ) => inputs.forEach( ( input ) => input.click() ) );
	await save( page );
	form = await openTopic( page, 'two_factor' );
	await expect( form.locator( '[data-option-key="two_factor_auth_user_roles"] input[type="checkbox"]:checked' ) ).toHaveCount( 0 );

	// Put things back.
	await form.locator( '[data-option-key="two_factor_auth_user_roles"] input[type="checkbox"]' ).evaluateAll( ( inputs, keep ) => inputs.forEach( ( input ) => {
		if ( input.checked !== keep.includes( input.value ) ) {
			input.click();
		}
	} ), original );
	await save( page );
} );

test( 'a two-choice select shows as a switch and saves either way', async ( { page } ) => {
	await openGoal( page, 'alerts' );
	let form = await openTopic( page, 'instant_alerts' );
	const toggle = form.locator( 'input[type="checkbox"][name="instant_alert_admin_login"]' );
	const wasOn = await toggle.isChecked();

	await form.locator( '[data-option-key="instant_alert_admin_login"] .form-switch' ).click();
	await save( page );
	form = await openTopic( page, 'instant_alerts' );
	await expect( form.locator( 'input[type="checkbox"][name="instant_alert_admin_login"]' ) ).toBeChecked( { checked: !wasOn } );

	await form.locator( '[data-option-key="instant_alert_admin_login"] .form-switch' ).click();
	await save( page );
	form = await openTopic( page, 'instant_alerts' );
	await expect( form.locator( 'input[type="checkbox"][name="instant_alert_admin_login"]' ) ).toBeChecked( { checked: wasOn } );
} );

test( 'a link to a setting under More options opens that section', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'zones', nav_sub: 'overview', zone: 'spam', row_key: 'comment_spam', config_item: 'trusted_commenter_minimum' } );
	const form = diagnosis( page ).locator( '[data-configure-row-key="comment_spam"] form.options_form_for--panel' );
	await expect( form.locator( '[data-option-key="trusted_commenter_minimum"]' ) ).toBeVisible( { timeout: 20_000 } );
	await expect( form.locator( '[data-panel-more="1"]' ) ).toHaveAttribute( 'aria-expanded', 'true' );
} );

test( 'open panels pass the accessibility checks', async ( { page } ) => {
	await openGoal( page, 'accounts' );
	for ( const topic of [ 'two_factor', 'passwords', 'sessions', 'suspensions' ] ) {
		await openTopic( page, topic );
	}
	await diagnosis( page ).locator( '[data-configure-row-key="two_factor"] [data-panel-more="1"]' ).click();
	await expectNoAxeViolations( page, '[data-configure-diagnosis="1"]' );
} );

test( 'more options stay tucked away until opened', async ( { page } ) => {
	await openGoal( page, 'spam' );
	const form = await openTopic( page, 'comment_spam' );
	const more = form.locator( '[data-panel-more="1"]' );
	const trusted = form.locator( '[data-option-key="trusted_commenter_minimum"]' );
	await expect( trusted ).toBeHidden();
	await more.click();
	await expect( more ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( trusted ).toBeVisible();
} );
