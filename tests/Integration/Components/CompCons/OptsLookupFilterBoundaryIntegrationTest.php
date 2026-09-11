<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class OptsLookupFilterBoundaryIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->enablePremiumCapabilities();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'block_send_email_address',
			'two_factor_auth_user_roles',
		] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	public function test_report_email_falls_back_for_invalid_filter_and_preserves_valid_string() :void {
		$con = $this->requireController();
		$con->opts->optSet( 'block_send_email_address', 'configured@example.test' )->store();
		$invalid = static fn() => new \stdClass();
		\add_filter( 'shield/report_email', $invalid, \PHP_INT_MAX );

		try {
			$this->assertSame(
				'configured@example.test',
				$con->comps->opts_lookup->getReportEmail()
			);
		}
		finally {
			\remove_filter( 'shield/report_email', $invalid, \PHP_INT_MAX );
		}

		$valid = static fn() :string => ' filtered@example.test ';
		\add_filter( 'shield/report_email', $valid, \PHP_INT_MAX );
		try {
			$this->assertSame( 'filtered@example.test', $con->comps->opts_lookup->getReportEmail() );
		}
		finally {
			\remove_filter( 'shield/report_email', $valid, \PHP_INT_MAX );
		}
	}

	public function test_enforced_roles_filter_preserves_fallback_and_drops_invalid_members() :void {
		$con = $this->requireController();
		$con->opts->optSet( 'two_factor_auth_user_roles', [ 'administrator' ] )->store();
		$invalid = static fn() => new \stdClass();
		\add_filter( 'shield/2fa_email_enforced_user_roles', $invalid, \PHP_INT_MAX );

		try {
			$this->assertSame(
				\array_values( $con->opts->optDefault( 'two_factor_auth_user_roles' ) ),
				\array_values( $con->comps->opts_lookup->getLoginGuardEmailAuth2FaRoles() )
			);
		}
		finally {
			\remove_filter( 'shield/2fa_email_enforced_user_roles', $invalid, \PHP_INT_MAX );
		}

		$mixed = static fn() :array => [ ' Editor ', 123, [], new \stdClass(), '', 'editor', 'AUTHOR' ];
		\add_filter( 'shield/2fa_email_enforced_user_roles', $mixed, \PHP_INT_MAX );
		try {
			$this->assertSame(
				[ 'editor', 'author' ],
				\array_values( $con->comps->opts_lookup->getLoginGuardEmailAuth2FaRoles() )
			);
		}
		finally {
			\remove_filter( 'shield/2fa_email_enforced_user_roles', $mixed, \PHP_INT_MAX );
		}

		$empty = static fn() :array => [];
		\add_filter( 'shield/2fa_email_enforced_user_roles', $empty, \PHP_INT_MAX );
		try {
			$this->assertSame( [], $con->comps->opts_lookup->getLoginGuardEmailAuth2FaRoles() );
		}
		finally {
			\remove_filter( 'shield/2fa_email_enforced_user_roles', $empty, \PHP_INT_MAX );
		}
	}
}
