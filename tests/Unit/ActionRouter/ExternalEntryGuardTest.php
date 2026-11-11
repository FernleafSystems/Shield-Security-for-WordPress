<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions,
	Actions\BaseAction,
	Constants,
	Utility\ExternalEntryGuard
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ExternalEntryGuardTest extends BaseUnitTest {

	/**
	 * Every action that may run on an entry point without the guard verifying an action nonce. Each one is
	 * public by design or verifies its own credential inside the action. Adding to this list is a security
	 * review decision: give the reason.
	 */
	private const REVIEWED_NO_NONCE = [
		ExternalEntryGuard::ENTRY_ORDINARY => [
			Actions\MfaEmailAutoLogin::class                           => 'Email login link; verifies its own login nonce for the user.',
			Actions\MfaCanEmailSendVerify::class                       => 'Email link to the report address, which may not be the sending admin; verifies its own per-send token.',
			Actions\FullPageDisplay\DisplayBlockPage::class            => 'Read-only block page; target and render data restricted by ExternalActionTransportPolicy.',
			Actions\FullPageDisplay\DisplayReport::class               => 'Public redirect to the admin report view; no state change.',
			Actions\FullPageDisplay\DisplayReportAdmin::class          => 'Read-only admin report view; capability checked by the action, admin context by the policy.',
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

	public function test_undeclared_action_is_not_admitted() :void {
		$guard = new ExternalEntryGuard();
		foreach ( ExternalEntryGuard::ENTRIES as $entry ) {
			$this->assertFalse( $guard->isAdmitted( Actions\PluginSetOpt::class, $entry ) );
			$this->assertFalse( $guard->isAdmitted( Actions\PluginSetOpt::SLUG, $entry ) );
		}
	}

	public function test_unknown_slug_is_not_admitted() :void {
		$this->assertFalse( ( new ExternalEntryGuard() )->isAdmitted( 'no_such_action_slug', ExternalEntryGuard::ENTRY_ORDINARY ) );
	}

	public function test_declared_entry_is_admitted_with_its_proof_and_other_entries_are_not() :void {
		$guard = new ExternalEntryGuard();
		$this->assertSame( ExternalEntryGuard::PROOF_NONCE, $guard->proofFor( GuardTestDeclaredAction::class, ExternalEntryGuard::ENTRY_ORDINARY ) );
		$this->assertTrue( $guard->isAdmitted( GuardTestDeclaredAction::class, ExternalEntryGuard::ENTRY_ORDINARY ) );
		$this->assertFalse( $guard->isAdmitted( GuardTestDeclaredAction::class, ExternalEntryGuard::ENTRY_AJAX ) );
	}

	public function test_declaration_is_not_inherited() :void {
		$this->assertSame( [], ExternalEntryGuard::DeclaredAccess( GuardTestUndeclaredSubclassAction::class ) );
		$this->assertFalse( ( new ExternalEntryGuard() )->isAdmitted( GuardTestUndeclaredSubclassAction::class, ExternalEntryGuard::ENTRY_ORDINARY ) );
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
			$this->assertFalse( $guard->isAdmitted( $action, ExternalEntryGuard::ENTRY_ORDINARY ), $action );
		}
	}

	public function test_generic_actions_are_not_reachable_from_any_entry() :void {
		$guard = new ExternalEntryGuard();
		foreach (
			[
				Actions\PluginSetOpt::class,
				Actions\Render::class,
				Actions\FullPageDisplay\FullPageDisplayDynamic::class,
				Actions\FullPageDisplay\FullPageDisplayNonTerminating::class,
			] as $action
		) {
			foreach ( ExternalEntryGuard::ENTRIES as $entry ) {
				$this->assertFalse( $guard->isAdmitted( $action, $entry ), $action.' on '.$entry );
			}
		}
	}

	public function test_mainwp_entry_never_declares_a_nonce() :void {
		foreach ( $this->registeredActions() as $action ) {
			$this->assertNotSame(
				ExternalEntryGuard::PROOF_NONCE,
				ExternalEntryGuard::DeclaredAccess( $action )[ ExternalEntryGuard::ENTRY_MAINWP ] ?? '',
				$action.': MainWP authenticates its requests and sends no Shield nonce.'
			);
		}
	}

	/**
	 * @return list<class-string<BaseAction>>
	 */
	private function registeredActions() :array {
		return \array_values( \array_filter(
			\array_unique( Constants::ACTIONS ),
			static fn( string $action ) :bool => \class_exists( $action ) && !( new \ReflectionClass( $action ) )->isAbstract()
		) );
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
