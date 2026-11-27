<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\{
	AdminTwoFactorDetails,
	MaintenanceItemDetails
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\UserMeta\MetaRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\AdminTwoFactor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * The Actions Queue has a to-do for as long as any administrator has no two-factor authentication. It counts
 * administrators only, is ignored like any other to-do, and opens a drawer of its own that lists who isn't covered.
 */
class AdminTwoFactorIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'mfa' );
		$this->loginAsSecurityAdmin();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'enable_email_authentication',
			'email_can_send_verified_at',
			'email_can_send_verification_sent_at',
			'enable_google_authenticator',
			'two_factor_auth_user_roles',
			MaintenanceIssueStateProvider::OPT_KEY,
		] );
		// Email is the one method a role can be required to use, so requiring it is how these tests cover people.
		RuntimeTestState::restoreOptions( [
			'enable_email_authentication'         => 'Y',
			'email_can_send_verified_at'          => \time(),
			'email_can_send_verification_sent_at' => 0,
			'enable_google_authenticator'         => 'N',
			MaintenanceIssueStateProvider::OPT_KEY => [],
		], true );
		$this->requireEmailFor( [] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionSnapshot );
		RuntimeTestState::resetMfaProviderCache();
		parent::tear_down();
	}

	public function test_it_is_a_todo_until_every_administrator_is_covered() :void {
		$this->assertSame( [ 1, 0 ], $this->counts() );
		$this->assertContains( AdminTwoFactor::SLUG, $this->attentionKeys() );

		$this->requireEmailFor( [ 'administrator' ] );

		$this->assertSame( [ 0, 0 ], $this->counts() );
		$this->assertSame( 'good', $this->state()[ 'severity' ] );
		$this->assertNotContains( AdminTwoFactor::SLUG, $this->attentionKeys() );
	}

	public function test_a_required_method_that_login_no_longer_offers_covers_nobody() :void {
		$this->requireEmailFor( [ 'administrator' ] );
		$this->assertSame( [ 0, 0 ], $this->counts() );

		$withoutEmail = static fn( array $providers ) :array => \array_values( \array_diff( $providers, [ Email::class ] ) );
		\add_filter( 'shield/2fa_providers', $withoutEmail );
		RuntimeTestState::resetMfaProviderCache();
		try {
			// Login no longer asks the administrator for email, and the to-do and its drawer say the same.
			$this->assertSame( [], $this->requireController()->comps->mfa->getProvidersActiveForUser( \wp_get_current_user() ) );
			$this->assertSame( [ 1, 0 ], $this->counts() );

			$drawer = $this->requireController()->action_router->render( AdminTwoFactorDetails::class );
			$this->assertStringContainsString( 'actions-queue-row--warning', $drawer );
		}
		finally {
			\remove_filter( 'shield/2fa_providers', $withoutEmail );
			RuntimeTestState::resetMfaProviderCache();
		}
	}

	public function test_another_method_with_the_same_slug_does_not_take_out_shields_own() :void {
		$this->requireEmailFor( [ 'administrator' ] );

		// Shield's own class is still recognised when the filter writes it with a leading backslash.
		$withLookalike = static fn( array $providers ) :array => \array_merge(
			\array_diff( $providers, [ Email::class ] ),
			[ '\\'.Email::class, AdminTwoFactorEmailLookalike::class ]
		);
		\add_filter( 'shield/2fa_providers', $withLookalike );
		RuntimeTestState::resetMfaProviderCache();
		try {
			$mfa = $this->requireController()->comps->mfa;
			$this->assertTrue( $mfa->isProviderOffered( Email::class ) );
			$this->assertFalse( $mfa->isProviderOffered( AdminTwoFactorEmailLookalike::class ) );
			$this->assertSame( [ 0, 0 ], $this->counts() );
		}
		finally {
			\remove_filter( 'shield/2fa_providers', $withLookalike );
			RuntimeTestState::resetMfaProviderCache();
		}
	}

	public function test_only_administrators_are_counted() :void {
		$this->requireEmailFor( [ 'administrator' ] );
		self::factory()->user->create( [ 'role' => 'editor' ] );
		RuntimeTestState::resetMfaProviderCache();

		$this->assertSame( [ 0, 0 ], $this->counts() );
	}

	public function test_it_is_ignored_like_any_other_todo() :void {
		$this->requireController()->opts->optSet( MaintenanceIssueStateProvider::OPT_KEY, [
			AdminTwoFactor::SLUG => [ MaintenanceIssueStateProvider::SINGLETON_TOKEN ],
		] )->store();

		$this->assertTrue( ( new MaintenanceIssueStateProvider() )->canBeIgnored( AdminTwoFactor::SLUG ) );
		$this->assertSame( [ 0, 1 ], $this->counts() );
		$this->assertNotContains( AdminTwoFactor::SLUG, $this->attentionKeys() );
	}

	public function test_its_row_opens_its_own_drawer_which_lists_the_administrators_without() :void {
		$this->assertSame( AdminTwoFactorDetails::class, MaintenanceItemDetails::drawerFor( AdminTwoFactor::SLUG ) );
		$request = \json_decode( MaintenanceItemDetails::requestJsonFor( AdminTwoFactor::SLUG ), true );
		$this->assertSame( AdminTwoFactorDetails::SLUG, $request[ 'render_slug' ] ?? '' );

		// An administrator who is an editor as well is covered by requiring email of editors.
		$this->createAdministratorUser( [ 'user_login' => 'tfa-uncovered-admin', 'display_name' => 'Uncovered' ] );
		$covered = \get_user_by( 'id', $this->createAdministratorUser( [
			'user_login'   => 'tfa-covered-admin',
			'display_name' => 'Covered',
		] ) );
		$covered->add_role( 'editor' );
		$this->requireEmailFor( [ 'editor' ] );

		// One administrator covered and others not is still a to-do.
		$this->assertSame( [ 1, 0 ], $this->counts() );

		$drawer = $this->requireController()->action_router->render( AdminTwoFactorDetails::class );

		$this->assertStringContainsString( 'tfa-uncovered-admin', $drawer );
		$this->assertStringNotContainsString( 'tfa-covered-admin', $drawer );
	}

	public function test_looking_does_not_give_an_administrator_a_shield_record() :void {
		$unseen = $this->createAdministratorUser();
		$this->requireController()->db_con->user_meta->getQueryDeleter()->filterByUser( $unseen )->query();
		RuntimeTestState::forgetLoadedUserRecords();
		$this->assertNull( ( new MetaRecords() )->loadMeta( $unseen, false ) );

		// A method that people set up themselves, so that each of them is looked up.
		$this->enableAuthenticatorApps();
		$this->assertSame( [ 1, 0 ], $this->counts() );
		$this->requireController()->action_router->render( AdminTwoFactorDetails::class );

		$this->assertNull( ( new MetaRecords() )->loadMeta( $unseen, false ) );
	}

	public function test_people_are_looked_up_together_and_only_when_a_method_they_set_up_themselves_is_enabled() :void {
		for ( $i = 0; $i < 4; $i++ ) {
			$this->createAdministratorUser();
		}

		// Nothing enabled that a person sets up, and then everybody covered by their role: no lookups either way.
		$this->assertSame( 0, $this->lookupsOfPeoplesMethods() );
		$this->requireEmailFor( [ 'administrator' ] );
		$this->assertSame( 0, $this->lookupsOfPeoplesMethods() );

		// One query for all of them, however many there are.
		$this->requireEmailFor( [] );
		$this->enableAuthenticatorApps();
		$this->assertSame( 1, $this->lookupsOfPeoplesMethods() );
	}

	private function enableAuthenticatorApps() :void {
		RuntimeTestState::restoreOptions( [ 'enable_google_authenticator' => 'Y' ], true );
		RuntimeTestState::resetMfaProviderCache();
		RuntimeTestState::forgetLoadedUserRecords();
	}

	/**
	 * @return int how many queries working out the to-do makes of the table that holds people's two-factor methods
	 */
	private function lookupsOfPeoplesMethods() :int {
		$table = $this->requireController()->db_con->mfa->getTable();
		$lookups = 0;
		$count = static function ( $sql ) use ( $table, &$lookups ) {
			$lookups += \strpos( (string)$sql, $table ) === false ? 0 : 1;
			return $sql;
		};
		\add_filter( 'query', $count );
		$this->state();
		\remove_filter( 'query', $count );
		return $lookups;
	}

	/**
	 * @param list<string> $roles
	 */
	private function requireEmailFor( array $roles ) :void {
		RuntimeTestState::restoreOptions( [ 'two_factor_auth_user_roles' => $roles ], true );
		RuntimeTestState::resetMfaProviderCache();
	}

	private function state() :array {
		return ( new MaintenanceIssueStateProvider() )->buildStates()[ AdminTwoFactor::SLUG ];
	}

	/**
	 * @return array{int,int} how many are waiting to be done, and how many are ignored
	 */
	private function counts() :array {
		$state = $this->state();
		return [ $state[ 'count' ], $state[ 'ignored_count' ] ];
	}

	/**
	 * @return list<string>
	 */
	private function attentionKeys() :array {
		return \array_column( ( new BuildAttentionItems() )->build()[ 'items' ], 'key' );
	}
}

/**
 * Another plugin's method that happens to use the slug of Shield's own email method.
 */
class AdminTwoFactorEmailLookalike extends Email {
}
