<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions,
	Actions\BaseAction,
	Utility\ActionsMap,
	Utility\ExternalEntryGuard
};
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\{
	ListsRegisteredActions,
	RegistersTestActions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ExternalEntryGuardTest extends BaseUnitTest {

	use ListsRegisteredActions;
	use RegistersTestActions;

	protected function tearDown() :void {
		$this->unregisterTestActions();
		parent::tearDown();
	}

	/**
	 * Every action that may run on an entry point without the guard verifying an action nonce. Each one is
	 * public by design or verifies its own credential inside the action. Adding to this list is a security
	 * review decision: give the reason.
	 */
	private const REVIEWED_NO_NONCE = [
		ExternalEntryGuard::ENTRY_ORDINARY => [
			Actions\MfaEmailAutoLogin::class                           => 'Email login link; verifies its own login nonce for the user.',
			Actions\MfaCanEmailSendVerify::class                       => 'Email link to the report address, which may not be the sending admin; verifies its own per-send token.',
			Actions\FullPageDisplay\DisplayReport::class               => 'Public redirect to the admin report view; no state change.',
			Actions\FullPageDisplay\DisplayReportAdmin::class          => 'Read-only admin report view; the action checks capability and admin context.',
			Actions\LicenseHandshakeVerifyKeyless::class               => 'Licence server probe; answers whether its own handshake nonce is valid.',
			Actions\ShieldNetHandshakeVerify::class                    => 'ShieldNET server probe; answers whether its own handshake nonce is valid.',
			Actions\LicenseScheduleCheck::class                        => 'Licence server trigger; only schedules a licence check.',
			Actions\PluginImportExport_HandshakeConfirm::class         => 'Cross-site probe; answers whether the export handshake window is open.',
			Actions\PluginImportExport_Export::class                   => 'Cross-site export; verifies the site secret inside the export.',
			Actions\PluginImportExport_UpdateNotified::class           => 'Cross-site notification from the trusted master; schedules a pull from it.',
			Actions\PluginImportExport_NetworkInviteRequest::class     => 'Cross-site invitation; stored as pending until an admin accepts it.',
		],
		ExternalEntryGuard::ENTRY_AJAX     => [
			Actions\CaptureNotBot::class => 'Public silentCAPTCHA registration from possibly cached pages; records bot signals only.',
		],
		ExternalEntryGuard::ENTRY_MAINWP   => [
			Actions\LicenseLookup::class                          => 'MainWP authenticates the dashboard before Shield runs it.',
			Actions\MainWP\ClientActions\MainwpEnable::class      => 'MainWP authenticates the dashboard before Shield runs it.',
		],
	];

	/**
	 * Every action whose nonce works in any login session of its user, not only the session it was issued in.
	 * Each one issues its nonce into something that leaves the page, so the session may have changed by the
	 * time it comes back. Adding to this list is a security review decision: give the reason.
	 */
	private const REVIEWED_ANY_SESSION_NONCE = [
		Actions\IpAutoUnblockShieldUserLinkVerify::class => 'Emailed unblock link; the user may have logged in again before opening it.',
		Actions\SecurityAdminRemove::class               => 'Emailed confirmation link; the admin may have logged in again before opening it.',
	];

	public function test_action_data_carries_the_decoded_encoded_form_from_its_own_transport() :void {
		$form = [
			'title' => 'Weekly',
			'scans' => [ 'malware' ],
		];
		$transport = [
			'ex'          => 'report_create_custom',
			'form_params' => \base64_encode( (string)\json_encode( $form ) ),
			'form_enc'    => [ FormParams::ENC_BASE64, FormParams::ENC_JSON ],
		];

		$actionData = ExternalEntryGuard::ActionDataFromTransport( $transport );

		$this->assertSame( $form, $actionData[ ActionData::FIELD_FORM ] );
	}

	public function test_action_data_form_falls_back_to_the_plain_transport() :void {
		$transport = [
			'all_opts_keys' => 'enable_mainwp',
			'enable_mainwp' => 'Y',
		];

		$this->assertSame( $transport, ExternalEntryGuard::ActionDataFromTransport( $transport )[ ActionData::FIELD_FORM ] );
	}

	public function test_action_data_form_comes_only_from_the_given_form_source() :void {
		$query = [
			'ex'       => 'ip_auto_unblock_shield_visitor',
			'_confirm' => 'Y',
		];
		$post = [
			'email' => 'filled-honeypot',
		];

		$actionData = ExternalEntryGuard::ActionDataFromTransport( \array_merge( $query, $post ), $post );

		$this->assertSame( $post, $actionData[ ActionData::FIELD_FORM ] );
		$this->assertSame( 'Y', $actionData[ '_confirm' ] );
	}

	public function test_undeclared_action_is_not_admitted() :void {
		$guard = new ExternalEntryGuard();
		$this->assertRequestCanName( Actions\FullPageDisplay\FullPageDisplayDynamic::class );
		foreach ( ExternalEntryGuard::ENTRIES as $entry ) {
			$this->assertFalse( $guard->isAdmitted( Actions\FullPageDisplay\FullPageDisplayDynamic::SLUG, $entry ) );
		}
	}

	/**
	 * A request for an unregistered slug is refused whatever the action declares, so a test that an action is
	 * refused for its declaration first shows that the request reaches it.
	 * @param class-string<BaseAction> $action
	 */
	private function assertRequestCanName( string $action ) :void {
		$this->assertSame( $action, ActionsMap::ActionFromRequest( $action::SLUG ), $action.' is not registered.' );
	}

	public function test_a_request_names_an_action_by_its_slug_and_never_by_its_class() :void {
		$guard = new ExternalEntryGuard();
		$this->assertTrue( $guard->isAdmitted( Actions\LicenseLookup::SLUG, ExternalEntryGuard::ENTRY_AJAX ) );

		foreach (
			[
				Actions\LicenseLookup::class,
				'\\'.Actions\LicenseLookup::class,
				\strtolower( Actions\LicenseLookup::class ),
				\strtoupper( Actions\LicenseLookup::SLUG ),
				' '.Actions\LicenseLookup::SLUG,
				'',
			] as $name
		) {
			$this->assertSame( '', ActionsMap::ActionFromRequest( $name ), $name );
			$this->assertFalse( $guard->isAdmitted( $name, ExternalEntryGuard::ENTRY_AJAX ), $name );
		}
	}

	public function test_a_request_cannot_name_an_action_that_is_not_registered() :void {
		$this->assertSame( '', ActionsMap::ActionFromRequest( GuardTestDeclaredAction::SLUG ) );
		$this->assertFalse( ( new ExternalEntryGuard() )->isAdmitted( GuardTestDeclaredAction::SLUG, ExternalEntryGuard::ENTRY_ORDINARY ) );
	}

	public function test_action_data_leaves_out_the_fields_that_only_routed_the_request() :void {
		$actionData = ExternalEntryGuard::ActionDataFromTransport( [
			ActionData::FIELD_ACTION        => ActionData::FIELD_SHIELD,
			ActionData::FIELD_EXECUTE       => 'some_action',
			ActionData::FIELD_NONCE         => 'nonce',
			ActionData::FIELD_AJAXURL       => 'https://example.com/wp-admin/admin-ajax.php',
			ActionData::FIELD_WRAP_RESPONSE => '1',
			'shield_uniq'                   => '1234',
			'rid'                           => '7',
		] );

		$this->assertSame( [ 'rid' => '7' ], $actionData[ ActionData::FIELD_FORM ] );
		unset( $actionData[ ActionData::FIELD_FORM ] );
		$this->assertSame( [ 'rid' => '7' ], $actionData );
	}

	public function test_unknown_slug_is_not_admitted() :void {
		$this->assertFalse( ( new ExternalEntryGuard() )->isAdmitted( 'no_such_action_slug', ExternalEntryGuard::ENTRY_ORDINARY ) );
	}

	public function test_declared_entry_is_admitted_with_its_proof_and_other_entries_are_not() :void {
		$this->registerTestActions( GuardTestDeclaredAction::class );
		$guard = new ExternalEntryGuard();
		$this->assertSame( ExternalEntryGuard::PROOF_NONCE, $guard->proofFor( GuardTestDeclaredAction::SLUG, ExternalEntryGuard::ENTRY_ORDINARY ) );
		$this->assertTrue( $guard->isAdmitted( GuardTestDeclaredAction::SLUG, ExternalEntryGuard::ENTRY_ORDINARY ) );
		$this->assertFalse( $guard->isAdmitted( GuardTestDeclaredAction::SLUG, ExternalEntryGuard::ENTRY_AJAX ) );
	}

	public function test_declaration_is_not_inherited() :void {
		$this->registerTestActions( GuardTestUndeclaredSubclassAction::class );
		$this->assertSame( [], ExternalEntryGuard::DeclaredAccess( GuardTestUndeclaredSubclassAction::class ) );
		$this->assertFalse( ( new ExternalEntryGuard() )->isAdmitted( GuardTestUndeclaredSubclassAction::SLUG, ExternalEntryGuard::ENTRY_ORDINARY ) );
	}

	public function test_malformed_declarations_fail_closed() :void {
		$this->assertSame(
			[ ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONE ],
			ExternalEntryGuard::DeclaredAccess( GuardTestMalformedAction::class )
		);
	}

	public function test_registered_declarations_are_well_formed() :void {
		foreach ( $this->registeredActions() as $action ) {
			$method = new \ReflectionMethod( $action, 'ExternalAccess' );
			if ( $method->getDeclaringClass()->getName() === $action ) {
				$this->assertNotSame( [], $action::ExternalAccess(), $action.' declares empty external access.' );
				$this->assertSame(
					$action::ExternalAccess(),
					ExternalEntryGuard::DeclaredAccess( $action ),
					$action.' has an invalid entry or proof in its external access declaration.'
				);
			}
		}
	}

	public function test_every_entry_without_nonce_is_reviewed() :void {
		foreach ( $this->registeredActions() as $action ) {
			foreach ( ExternalEntryGuard::DeclaredAccess( $action ) as $entry => $proof ) {
				if ( $proof === ExternalEntryGuard::PROOF_NONE ) {
					$this->assertArrayHasKey(
						$action,
						self::REVIEWED_NO_NONCE[ $entry ] ?? [],
						\sprintf( '%s is reachable on "%s" without a nonce but is not on the reviewed list.', $action, $entry )
					);
				}
			}
		}

		foreach ( self::REVIEWED_NO_NONCE as $entry => $actions ) {
			foreach ( $actions as $action => $reason ) {
				$this->assertSame(
					ExternalEntryGuard::PROOF_NONE,
					ExternalEntryGuard::DeclaredAccess( $action )[ $entry ] ?? '',
					\sprintf( 'Reviewed entry for %s on "%s" is stale.', $action, $entry )
				);
				$this->assertNotSame( '', \trim( $reason ) );
			}
		}
	}

	public function test_every_nonce_that_works_in_any_session_is_reviewed() :void {
		$declaring = [];
		foreach ( $this->registeredActions() as $action ) {
			if ( \in_array( ExternalEntryGuard::PROOF_NONCE_ANY_SESSION, ExternalEntryGuard::DeclaredAccess( $action ), true ) ) {
				$declaring[] = $action;
			}
		}

		$reviewed = \array_keys( self::REVIEWED_ANY_SESSION_NONCE );
		\sort( $declaring );
		\sort( $reviewed );
		$this->assertSame( $reviewed, $declaring );
		foreach ( self::REVIEWED_ANY_SESSION_NONCE as $reason ) {
			$this->assertNotSame( '', \trim( $reason ) );
		}
	}

	public function test_declared_nonce_proof_is_the_kind_of_nonce_the_action_is_issued() :void {
		$this->assertSame( ExternalEntryGuard::PROOF_NONCE, ExternalEntryGuard::DeclaredNonceProof( GuardTestDeclaredAction::class ) );
		$this->assertSame(
			ExternalEntryGuard::PROOF_NONCE_ANY_SESSION,
			ExternalEntryGuard::DeclaredNonceProof( GuardTestAnySessionAction::class )
		);
		$this->assertSame( '', ExternalEntryGuard::DeclaredNonceProof( GuardTestMalformedAction::class ) );
		$this->assertSame( '', ExternalEntryGuard::DeclaredNonceProof( GuardTestUndeclaredSubclassAction::class ) );
	}

	public function test_declaring_both_kinds_of_nonce_drops_the_entry_that_works_in_any_session() :void {
		$this->assertSame(
			[ ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE ],
			ExternalEntryGuard::DeclaredAccess( GuardTestBothNoncesAction::class )
		);
		$this->assertSame( ExternalEntryGuard::PROOF_NONCE, ExternalEntryGuard::DeclaredNonceProof( GuardTestBothNoncesAction::class ) );
	}

	public function test_internal_building_blocks_are_not_reachable_on_the_ordinary_route() :void {
		$guard = new ExternalEntryGuard();
		foreach (
			[
				Actions\Render::class,
				Actions\AjaxRender::class,
				Actions\AjaxBatchRequests::class,
				Actions\PluginAdmin\PluginAdminPageHandler::class,
				Actions\MainWP\ServerActions\MainwpServerClientActionHandler::class,
			] as $action
		) {
			$this->assertRequestCanName( $action );
			$this->assertFalse( $guard->isAdmitted( $action::SLUG, ExternalEntryGuard::ENTRY_ORDINARY ), $action );
		}
	}

	public function test_generic_actions_are_not_reachable_from_any_entry() :void {
		$guard = new ExternalEntryGuard();
		foreach (
			[
				Actions\Render::class,
				Actions\FullPageDisplay\DisplayBlockPage::class,
				Actions\FullPageDisplay\FullPageDisplayDynamic::class,
				Actions\FullPageDisplay\FullPageDisplayNonTerminating::class,
			] as $action
		) {
			$this->assertRequestCanName( $action );
			foreach ( ExternalEntryGuard::ENTRIES as $entry ) {
				$this->assertFalse( $guard->isAdmitted( $action::SLUG, $entry ), $action.' on '.$entry );
			}
		}
	}

	public function test_render_actions_are_not_directly_reachable() :void {
		foreach ( $this->registeredActions() as $action ) {
			if ( \is_a( $action, Actions\Render\BaseRender::class, true ) ) {
				$this->assertSame( [], ExternalEntryGuard::DeclaredAccess( $action ), $action.' is reached only through AjaxRender or internally.' );
			}
		}
	}

	public function test_mainwp_entry_never_declares_a_nonce() :void {
		foreach ( $this->registeredActions() as $action ) {
			$this->assertContains(
				ExternalEntryGuard::DeclaredAccess( $action )[ ExternalEntryGuard::ENTRY_MAINWP ] ?? '',
				[ '', ExternalEntryGuard::PROOF_NONE ],
				$action.': MainWP authenticates its requests and sends no Shield nonce.'
			);
		}
	}
}

class GuardTestDeclaredAction extends BaseAction {

	public const SLUG = 'guard_test_declared_action';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
	}
}

class GuardTestAnySessionAction extends BaseAction {

	public const SLUG = 'guard_test_any_session_action';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONCE_ANY_SESSION,
		];
	}

	protected function exec() {
	}
}

class GuardTestBothNoncesAction extends BaseAction {

	public const SLUG = 'guard_test_both_nonces_action';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONCE_ANY_SESSION,
			ExternalEntryGuard::ENTRY_AJAX     => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
	}
}

class GuardTestUndeclaredSubclassAction extends GuardTestDeclaredAction {

	public const SLUG = 'guard_test_undeclared_subclass_action';
}

class GuardTestMalformedAction extends BaseAction {

	public const SLUG = 'guard_test_malformed_action';

	public static function ExternalAccess() :array {
		return [
			'not_an_entry'                     => ExternalEntryGuard::PROOF_NONCE,
			ExternalEntryGuard::ENTRY_ORDINARY => 'not_a_proof',
			ExternalEntryGuard::ENTRY_AJAX     => ExternalEntryGuard::PROOF_NONE,
		];
	}

	protected function exec() {
	}
}
