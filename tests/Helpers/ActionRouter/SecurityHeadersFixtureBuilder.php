<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * @phpstan-type FixtureState array{
 *   options_snapshot:array<string,mixed>,
 *   force_restrictions_option_present:bool,
 *   force_restrictions_option_value:mixed
 * }
 * @phpstan-type FixtureContract array{
 *   path:string,
 *   expected_headers:array<string,string>,
 *   options:array<string,string>
 * }
 */
class SecurityHeadersFixtureBuilder {

	private const FORCE_RESTRICTIONS_OPTION = 'shield_browser_fixture_force_restrictions';
	private const OPTION_MISSING_SENTINEL = '__shield_browser_fixture_missing__';

	private const OPTION_KEYS = [
		'global_enable_plugin_features',
		'x_frame',
		'x_xss_protect',
		'x_content_type',
		'x_referrer_policy',
	];

	/**
	 * @return array{contract:FixtureContract,state:FixtureState}
	 */
	public function seed() :array {
		$forceRestrictionsOption = \get_option( self::FORCE_RESTRICTIONS_OPTION, self::OPTION_MISSING_SENTINEL );
		$state = [
			'options_snapshot'                   => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
			'force_restrictions_option_present'  => $forceRestrictionsOption !== self::OPTION_MISSING_SENTINEL,
			'force_restrictions_option_value'    => $forceRestrictionsOption !== self::OPTION_MISSING_SENTINEL
				? $forceRestrictionsOption
				: null,
		];

		RuntimeTestState::restoreOptions( $this->options() );
		\update_option( self::FORCE_RESTRICTIONS_OPTION, 'Y', false );

		return [
			'contract' => [
				'path'             => '/',
				'expected_headers' => $this->expectedHeaders(),
				'options'          => $this->options(),
			],
			'state'    => $state,
		];
	}

	/**
	 * @param array<string,mixed> $state
	 */
	public function cleanup( array $state ) :void {
		$snapshot = \is_array( $state[ 'options_snapshot' ] ?? null ) ? $state[ 'options_snapshot' ] : [];
		if ( $snapshot !== [] ) {
			RuntimeTestState::restoreOptions( $snapshot );
		}
		if ( (bool)( $state[ 'force_restrictions_option_present' ] ?? false ) ) {
			\update_option( self::FORCE_RESTRICTIONS_OPTION, $state[ 'force_restrictions_option_value' ] ?? '', false );
		}
		else {
			\delete_option( self::FORCE_RESTRICTIONS_OPTION );
		}
	}

	/**
	 * @return array<string,string>
	 */
	private function options() :array {
		return [
			'global_enable_plugin_features' => 'Y',
			'x_frame'                       => 'on_deny',
			'x_xss_protect'                 => 'Y',
			'x_content_type'                => 'Y',
			'x_referrer_policy'             => 'no-referrer',
		];
	}

	/**
	 * @return array<string,string>
	 */
	private function expectedHeaders() :array {
		return [
			'referrer-policy'          => 'no-referrer',
			'x-content-type-options'  => 'nosniff',
			'x-frame-options'         => 'DENY',
			'x-xss-protection'        => '1; mode=block',
		];
	}
}
