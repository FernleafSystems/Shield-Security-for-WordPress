<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts;

class ChartOptions {

	public const PERIOD_7_DAYS = '7_days';
	public const PERIOD_8_WEEKS = '8_weeks';
	public const PERIOD_6_MONTHS = '6_months';
	public const PERIOD_12_MONTHS = '12_months';
	public const PERIOD_YEARS = 'years';

	/**
	 * @return array<string,array{
	 *   label:string,
	 *   description:string
	 * }>
	 */
	public static function eventDefinitions() :array {
		return [
			'conn_kill'               => [
				'label'       => __( 'Connections Killed', 'wp-simple-firewall' ),
				'description' => __( 'Connections terminated from blocked IP addresses.', 'wp-simple-firewall' ),
			],
			'request_policy_block'    => [
				'label'       => __( 'Request Policy Blocks', 'wp-simple-firewall' ),
				'description' => __( 'Requests blocked by adaptive request policy.', 'wp-simple-firewall' ),
			],
			'ip_blocked'              => [
				'label'       => __( 'IP Blocks', 'wp-simple-firewall' ),
				'description' => __( 'IP addresses added to block list after exceeding limits.', 'wp-simple-firewall' ),
			],
			'ip_offense'              => [
				'label'       => __( 'IP Offences', 'wp-simple-firewall' ),
				'description' => __( 'Offenses commited by all visitors.', 'wp-simple-firewall' ),
			],
			'login_block'             => [
				'label'       => __( 'Login Blocks', 'wp-simple-firewall' ),
				'description' => __( 'Blocked login attempts.', 'wp-simple-firewall' ),
			],
			'block_register'          => [
				'label'       => __( 'Registration Blocks', 'wp-simple-firewall' ),
				'description' => __( 'Blocked user registration attempts.', 'wp-simple-firewall' ),
			],
			'block_xml'               => [
				'label'       => __( 'XML-RPC Blocks', 'wp-simple-firewall' ),
				'description' => __( 'Blocked XML-RPC requests.', 'wp-simple-firewall' ),
			],
			'bottrack_fakewebcrawler' => [
				'label'       => __( 'Fake Web Crawlers', 'wp-simple-firewall' ),
				'description' => __( 'Requests identified as fake web crawlers.', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return array<string,array{
	 *   label:string,
	 *   interval:'daily'|'weekly'|'monthly'|'yearly',
	 *   ticks:int
	 * }>
	 */
	public static function periodDefinitions() :array {
		return [
			self::PERIOD_7_DAYS    => [
				'label'    => __( '7 days', 'wp-simple-firewall' ),
				'interval' => 'daily',
				'ticks'    => 7,
			],
			self::PERIOD_8_WEEKS   => [
				'label'    => __( '8 weeks', 'wp-simple-firewall' ),
				'interval' => 'weekly',
				'ticks'    => 8,
			],
			self::PERIOD_6_MONTHS  => [
				'label'    => __( '6 months', 'wp-simple-firewall' ),
				'interval' => 'monthly',
				'ticks'    => 6,
			],
			self::PERIOD_12_MONTHS => [
				'label'    => __( '12 months', 'wp-simple-firewall' ),
				'interval' => 'monthly',
				'ticks'    => 12,
			],
			self::PERIOD_YEARS     => [
				'label'    => __( 'Years', 'wp-simple-firewall' ),
				'interval' => 'yearly',
				'ticks'    => 0,
			],
		];
	}

	public static function defaultPeriodKey() :string {
		return self::PERIOD_8_WEEKS;
	}

	/**
	 * @return list<string>
	 */
	public static function normalizeEventKeys( array $eventKeys ) :array {
		$selected = [];
		$input = \array_map( 'sanitize_key', $eventKeys );
		foreach ( \array_keys( self::eventDefinitions() ) as $eventKey ) {
			if ( \in_array( $eventKey, $input, true ) ) {
				$selected[] = $eventKey;
			}
		}
		return $selected;
	}

	public static function normalizePeriodKey( string $periodKey ) :string {
		$periodKey = sanitize_key( $periodKey );
		return \array_key_exists( $periodKey, self::periodDefinitions() )
			? $periodKey
			: self::defaultPeriodKey();
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   is_default:bool
	 * }>
	 */
	public static function buildSelectableEvents() :array {
		return \array_map(
			static fn( string $key, array $definition ) :array => [
				'key'         => $key,
				'label'       => $definition[ 'label' ],
				'description' => $definition[ 'description' ],
				'is_default'  => false,
			],
			\array_keys( self::eventDefinitions() ),
			self::eventDefinitions()
		);
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   label:string,
	 *   is_default:bool
	 * }>
	 */
	public static function buildSelectablePeriods() :array {
		$defaultPeriod = self::defaultPeriodKey();
		return \array_map(
			static fn( string $key, array $definition ) :array => [
				'key'        => $key,
				'label'      => $definition[ 'label' ],
				'is_default' => $key === $defaultPeriod,
			],
			\array_keys( self::periodDefinitions() ),
			self::periodDefinitions()
		);
	}
}
