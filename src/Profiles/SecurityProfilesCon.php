<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Profiles;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class SecurityProfilesCon {

	use PluginControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function applyLevel( string $level ) {
		if ( !\in_array( $level, Levels::Enum() ) ) {
			throw new \Exception( 'Not a valid security profile level.' );
		}
		( new ApplyProfile( $this->buildForLevel( $level ) ) )->run();
	}

	public function buildForCurrent() :array {
		return ( new ProfileFromConfig() )->build();
	}

	/**
	 * Each level is build upon the sublevel so that we can create each profile as a diff from the previous.
	 *
	 * We take the specified options for each level and assign them to the structure
	 */
	public function buildForLevel( string $level, bool $filterNullOpts = true ) :array {
		$structure = Levels::Sub( $level ) === null ? $this->getStructure() : $this->buildForLevel( Levels::Sub( $level ), false );
		foreach ( $this->getOptsForLevels() as $topKey => $levels ) {
			if ( !empty( $structure[ $topKey ] ) ) {
				$section = $structure[ $topKey ];
				foreach ( $levels[ $level ] as $itemKey => $value ) {
					foreach ( $section[ 'opts' ] as &$opt ) {
						if ( $opt[ 'item_key' ] === $itemKey ) {
							$opt[ 'value' ] = $value;
						}
					}
				}
				if ( $filterNullOpts ) {
					$section[ 'opts' ] = \array_filter( $section[ 'opts' ], fn( array $opt ) => $opt[ 'value' ] !== null );
				}
				$structure[ $topKey ] = $section;
			}
		}

		return $structure;
	}

	public function meta( string $level ) :array {
		return [
				   Levels::LIGHT   => [
					   'title'    => __( 'Light', 'wp-simple-firewall' ),
					   'subtitle' => __( 'A light-touch security profile', 'wp-simple-firewall' ),
				   ],
				   Levels::MEDIUM  => [
					   'title'    => __( 'Medium', 'wp-simple-firewall' ),
					   'subtitle' => __( 'A robust security posture', 'wp-simple-firewall' ),
				   ],
				   Levels::STRONG  => [
					   'title'    => __( 'Strong', 'wp-simple-firewall' ),
					   'subtitle' => __( 'A powerful, defensive security posture', 'wp-simple-firewall' ),
				   ],
			   ][ $level ] ?? [];
	}

	private function getOptsForLevels() :array {
		return [
			'auto_block'    => [
				Levels::LIGHT  => [
					'transgression_limit' => 10,
					'auto_expire'         => 'hour',
					'cs_block'            => false,
				],
				Levels::MEDIUM => [
					'transgression_limit' => 7,
					'auto_expire'         => 'day',
					'cs_block'            => true,
				],
				Levels::STRONG => [
					'transgression_limit' => 5,
					'auto_expire'         => 'week',
				],
			],
			'silentcaptcha' => [
				Levels::LIGHT  => [
					'antibot_minimum' => 25,
				],
				Levels::MEDIUM => [
					'antibot_minimum' => 45,
				],
				Levels::STRONG => [
					'antibot_minimum' => 65,
				],
			],
			'user_forms'    => [
				Levels::LIGHT  => [
					'login_limit_interval' => 5,
					'register'             => true,
				],
				Levels::MEDIUM => [
					'login_limit_interval' => 10,
					'login'                => true,
					'password'             => true,
				],
				Levels::STRONG => [
					'login_limit_interval' => 15,
				],
			],
			'session_lock'  => [
				Levels::LIGHT  => [
					'useragent' => true,
				],
				Levels::MEDIUM => [
				],
				Levels::STRONG => [
					'ip' => true,
				],
			],
			'firewall'      => [
				Levels::LIGHT  => [
					'disable_file_editing' => 'Y',
					'block_dir_traversal'  => 'Y',
				],
				Levels::MEDIUM => [
					'block_author_discovery' => 'Y',
					'disable_xmlrpc'         => 'Y',
					'block_sql_queries'      => 'Y',
				],
				Levels::STRONG => [
					'block_php_code'            => 'Y',
					'block_aggressive'          => 'Y',
					'disable_anonymous_restapi' => 'Y',
				],
			],
			'spam'          => [
				Levels::LIGHT  => [
					'enable_antibot_comments'           => 'N',
					'enable_comments_human_spam_filter' => 'N',
					'comments_cooldown'                 => 10,
				],
				Levels::MEDIUM => [
					'enable_antibot_comments' => 'Y',
					'comments_cooldown'       => 30,
				],
				Levels::STRONG => [
					'enable_comments_human_spam_filter' => 'Y',
					'comments_cooldown'                 => 60,
				],
			],
			'integrations'  => [
				Levels::LIGHT  => [
				],
				Levels::MEDIUM => [
				],
				Levels::STRONG => [
					'enable_auto_integrations' => 'Y',
				],
			],
		];
	}

	public function getStructure() :array {
		return \array_map(
			static function ( array $section ) {
				$section[ 'opts' ] = \array_map(
					function ( array $opt ) {
						return \array_merge( $opt, [
							'is_available' => self::con()->opts->optHasAccess( $opt[ 'opt_key' ] ),
						] );
					},
					$section[ 'opts' ]
				);
				return $section;
			},
			$this->getRawStructure()
		);
	}

	private function getRawStructure() :array {
		return [
			'auto_block'    => [
				'title' => __( 'Blocking repeat offenders', 'wp-simple-firewall' ),
				'opts'  => [
					[
						'item_key' => 'transgression_limit',
						'opt_key'  => 'transgression_limit',
						'value'    => null,
						'title'    => __( 'Strikes before an IP address is blocked', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'auto_expire',
						'opt_key'  => 'auto_expire',
						'value'    => null,
						'title'    => __( 'How long a blocked IP address stays blocked', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'cs_block',
						'opt_key'  => 'cs_block',
						'value'    => null,
						'title'    => __( 'IP addresses known to attack other sites (CrowdSec)', 'wp-simple-firewall' ),
					],
				],
			],
			'silentcaptcha' => [
				'title' => self::con()->labels->getBrandName( 'silentcaptcha' ),
				'opts'  => [
					[
						'item_key' => 'antibot_minimum',
						'opt_key'  => 'antibot_minimum',
						'value'    => 0,
						'title'    => __( 'How strict bot detection is (0-100)', 'wp-simple-firewall' ),
					]
				],
			],
			'user_forms'    => [
				'title' => __( 'Bot checks on your forms', 'wp-simple-firewall' ),
				'opts'  => [
					[
						'item_key' => 'login_limit_interval',
						'opt_key'  => 'login_limit_interval',
						'value'    => 0,
						'title'    => __( 'Wait between login attempts (seconds)', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'register',
						'opt_key'  => 'bot_protection_locations',
						'value'    => false,
						'title'    => __( 'Check the sign-up form for bots', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'login',
						'opt_key'  => 'bot_protection_locations',
						'value'    => false,
						'title'    => __( 'Check the login form for bots', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'password',
						'opt_key'  => 'bot_protection_locations',
						'value'    => false,
						'title'    => __( 'Check the password reset form for bots', 'wp-simple-firewall' ),
					],
				],
			],
			'session_lock'  => [
				'title' => __( 'Locking logged-in sessions', 'wp-simple-firewall' ),
				'opts'  => [
					[
						'item_key' => 'useragent',
						'opt_key'  => 'session_lock',
						'value'    => false,
						'title'    => __( 'Lock a session to its browser', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'ip',
						'opt_key'  => 'session_lock',
						'value'    => false,
						'title'    => __( 'Lock a session to its IP address', 'wp-simple-firewall' ),
					],
				],
			],
			'firewall'      => [
				'title' => __( 'Firewall and hardening', 'wp-simple-firewall' ),
				'opts'  => [
					[
						'item_key' => 'disable_file_editing',
						'opt_key'  => 'disable_file_editing',
						'value'    => 'N',
						'title'    => __( 'Turn off file editing in the WordPress admin', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'block_author_discovery',
						'opt_key'  => 'block_author_discovery',
						'value'    => 'N',
						'title'    => __( 'Stop visitors discovering usernames', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'disable_xmlrpc',
						'opt_key'  => 'disable_xmlrpc',
						'value'    => 'N',
						'title'    => __( 'Turn off XML-RPC (old remote access)', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'disable_anonymous_restapi',
						'opt_key'  => 'disable_anonymous_restapi',
						'value'    => 'N',
						'title'    => __( 'Block the REST API for logged-out visitors', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'block_dir_traversal',
						'opt_key'  => 'block_dir_traversal',
						'value'    => 'N',
						'title'    => __( 'Block attempts to reach files outside your site', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'block_sql_queries',
						'opt_key'  => 'block_sql_queries',
						'value'    => 'N',
						'title'    => __( 'Block attempts to tamper with your database', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'block_php_code',
						'opt_key'  => 'block_php_code',
						'value'    => 'N',
						'title'    => __( 'Block attempts to run PHP code', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'block_aggressive',
						'opt_key'  => 'block_aggressive',
						'value'    => 'N',
						'title'    => __( 'Block more kinds of suspicious request (may block genuine ones)', 'wp-simple-firewall' ),
					],
				],
			],
			'spam'          => [
				'title' => __( 'Comment spam', 'wp-simple-firewall' ),
				'opts'  => [
					[
						'item_key' => 'enable_antibot_comments',
						'opt_key'  => 'enable_antibot_comments',
						'value'    => 'N',
						'title'    => __( 'Block comments from bots', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'enable_comments_human_spam_filter',
						'opt_key'  => 'enable_comments_human_spam_filter',
						'value'    => 'N',
						'title'    => __( 'Filter spam comments written by people', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'comments_cooldown',
						'opt_key'  => 'comments_cooldown',
						'value'    => 0,
						'title'    => __( 'Wait between comments (seconds)', 'wp-simple-firewall' ),
					],
				],
			],
			'integrations'  => [
				'title' => __( 'Form plugins', 'wp-simple-firewall' ),
				'opts'  => [
					[
						'item_key' => 'enable_auto_integrations',
						'opt_key'  => 'enable_auto_integrations',
						'value'    => 'N',
						'title'    => __( 'Protect supported form plugins automatically', 'wp-simple-firewall' ),
					],
				],
			],
		];
	}
}