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

async function moveSlider( slider, value ) {
	const stops = JSON.parse( await slider.getAttribute( 'data-stops' ) || '[]' );
	const index = stops.findIndex( ( stop ) => stop.value === String( value ) );
	expect( index, `slider has a step for ${value}` ).toBeGreaterThan( -1 );
	await slider.fill( String( index ) );
}

test( 'configure home lists the protection goals', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'zones', nav_sub: 'overview' } );
	const goals = page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' );
	await expect( goals.first() ).toBeVisible();
	await expect( page.locator( '[data-configure-tab="accounts"]' ) ).toBeVisible();
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
	const originalLifetime = await lifetime.locator( 'input[type="hidden"][name="session_timeout_interval"]' ).inputValue();
	const nextLifetime = originalLifetime === '7' ? '14' : '7';
	await moveSlider( lifetime.locator( 'input.shield-range__input' ), nextLifetime );
	await expect( lifetime.locator( 'input[type="hidden"][name="session_timeout_interval"]' ) ).toHaveValue( nextLifetime );
	await expect( lifetime.locator( '[data-range-value="1"]' ) ).toContainText( nextLifetime );
	await expect( lifetime.locator( '[data-option-unsaved="1"]' ) ).toBeVisible();

	// Clicking a setting's name works its control.
	const notifyLabel = form.locator( 'label[for="Opt-enable_user_login_email_notification"]' );
	await expect( notifyLabel ).toBeVisible();

	const notify = form.locator( 'input[name="enable_user_login_email_notification"]' );
	const notifyWasOn = await notify.isChecked();
	await notifyLabel.click();
	await expect( notify ).toBeChecked( { checked: !notifyWasOn } );

	await save( page );
	form = await openTopic( page, 'sessions' );
	await expect( form.locator( 'input[type="hidden"][name="session_timeout_interval"]' ) ).toHaveValue( nextLifetime );
	await expect( form.locator( 'input[name="enable_user_login_email_notification"]' ) ).toBeChecked( { checked: !notifyWasOn } );

	// Put things back.
	await moveSlider( form.locator( '[data-option-key="session_timeout_interval"] input.shield-range__input' ), originalLifetime );
	await form.locator( '[data-option-key="enable_user_login_email_notification"] .form-switch' ).click();
	await save( page );
} );

test( 'role chips save the ticked roles, including none', async ( { page } ) => {
	await openGoal( page, 'accounts' );
	let form = await openTopic( page, 'two_factor' );
	const roles = form.locator( '[data-option-key="two_factor_auth_user_roles"]' );
	const original = await roles.locator( 'input[type="checkbox"]:checked' ).evaluateAll( ( inputs ) => inputs.map( ( i ) => i.value ) );

	// Change to a set that differs from whatever is saved now, so there's always something to save.
	const ticked = original.length === 1 && original[ 0 ] === 'administrator' ? [ 'administrator', 'editor' ] : [ 'administrator' ];
	await roles.locator( 'input[type="checkbox"]' ).evaluateAll( ( inputs, keep ) => inputs.forEach( ( input ) => {
		if ( input.checked !== keep.includes( input.value ) ) {
			input.click();
		}
	} ), ticked );
	await save( page );

	form = await openTopic( page, 'two_factor' );
	await expect.poll( async () => form.locator( '[data-option-key="two_factor_auth_user_roles"] input[type="checkbox"]:checked' )
		.evaluateAll( ( inputs ) => inputs.map( ( i ) => i.value ) ) ).toEqual( ticked );

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

test( 'a link to an advanced setting opens Advanced options, even when it names the topic it came from', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'zones', nav_sub: 'overview', zone: 'spam', row_key: 'comment_spam', config_item: 'trusted_commenter_minimum' } );
	const form = diagnosis( page ).locator( '[data-configure-row-key="advanced"] form.options_form_for--panel' );
	await expect( form.locator( '[data-option-key="trusted_commenter_minimum"]' ) ).toBeVisible( { timeout: 20_000 } );
} );

test( 'open panels pass the accessibility checks', async ( { page } ) => {
	await openGoal( page, 'accounts' );
	for ( const topic of [ 'two_factor', 'passwords', 'sessions', 'suspensions', 'advanced' ] ) {
		await openTopic( page, topic );
	}
	await expectNoAxeViolations( page, '[data-configure-diagnosis="1"]' );
} );

test( 'needs attention leaves only the settings that need attention, each saying why', async ( { page } ) => {
	await openGoal( page, 'accounts' );
	await diagnosis( page ).locator( '[data-configure-zone-show="attention"]' ).click();
	const form = await openTopic( page, 'sessions' );

	const flagged = form.locator( '.shield-panel-item.has-panel-issue' );
	await expect( flagged.first() ).toBeVisible();
	await expect( flagged.first().locator( '.shield-panel-issue' ).first() ).not.toBeEmpty();
	await expect( form.locator( '.shield-panel-item:not(.has-panel-issue)' ).first() ).toBeHidden();

	await diagnosis( page ).locator( '[data-configure-zone-show="all"]' ).click();
	await expect( form.locator( '.shield-panel-item:not(.has-panel-issue)' ).first() ).toBeVisible();
} );

test( 'settings few sites change are gathered under Advanced options, headed by their topic', async ( { page } ) => {
	await openGoal( page, 'spam' );
	const topic = await openTopic( page, 'comment_spam' );
	await expect( topic.locator( '[data-option-key="trusted_commenter_minimum"]' ) ).toHaveCount( 0 );

	const advanced = await openTopic( page, 'advanced' );
	const trusted = advanced.locator( '[data-option-key="trusted_commenter_minimum"]' );
	await expect( trusted ).toBeVisible();
	await expect( advanced.locator( '.shield-panel-block', { has: page.locator( '[data-option-key="trusted_commenter_minimum"]' ) } ) ).toContainText( 'Comment spam' );
} );

test( 'a setting with more to say opens its description from the chevron beside its help mark', async ( { page } ) => {
	await openGoal( page, 'shield' );
	const advanced = await openTopic( page, 'advanced' );
	const toggle = advanced.locator( '[data-option-key="preferred_temp_dir"] .shield-panel-details-toggle' );
	const details = advanced.locator( '#Details-preferred_temp_dir' );

	await expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );
	await expect( toggle ).toHaveAttribute( 'data-bs-title', /\S/ );
	await expect( details ).toBeHidden();

	await toggle.click();
	await expect( toggle ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( details ).toBeVisible();
	await expect( details.locator( 'p' ).first() ).toHaveText( /\S/ );

	await toggle.press( 'Enter' );
	await expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );
	await expect( details ).toBeHidden();
} );
