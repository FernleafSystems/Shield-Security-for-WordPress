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
				   Levels::CURRENT => [
					   'title'    => __( 'Current', 'wp-simple-firewall' ),
					   'subtitle' => __( 'Your current security posture', 'wp-simple-firewall' ),
				   ],
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
							'tooltip'      => sprintf( '%s%s',
								self::con()->opts->optHasAccess( $opt[ 'opt_key' ] ) ? '' : '(Upgrade Required) ',
								$opt[ 'tooltip' ]
							),
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
				'title' => 'Auto IP Blocking',
				'opts'  => [
					[
						'item_key' => 'transgression_limit',
						'opt_key'  => 'transgression_limit',
						'value'    => null,
						'title'    => __( 'Offenses Limit', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Number of offenses permitted before IP is automatically blocked', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'auto_expire',
						'opt_key'  => 'auto_expire',
						'value'    => null,
						'title'    => __( 'Block duration', 'wp-simple-firewall' ),
						'tooltip'  => __( 'How long an automatically blocked IP will remain blocked', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'cs_block',
						'opt_key'  => 'cs_block',
						'value'    => null,
						'title'    => __( 'CrowdSec', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Participate in crowd-sourcing IP blocklists from CrowdSec', 'wp-simple-firewall' ),
					],
				],
			],
			'silentcaptcha' => [
				'title' => 'silentCAPTCHA',
				'opts'  => [
					[
						'item_key' => 'antibot_minimum',
						'opt_key'  => 'antibot_minimum',
						'value'    => 0,
						'title'    => __( 'Bot threshold', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Minimum silentCAPTCHA score required to indicate visitor is human', 'wp-simple-firewall' ),
					]
				],
			],
			'user_forms'    => [
				'title' => __( 'User forms', 'wp-simple-firewall' ),
				'opts'  => [
					[
						'item_key' => 'login_limit_interval',
						'opt_key'  => 'login_limit_interval',
						'value'    => 0,
						'title'    => __( 'Cooldown', 'wp-simple-firewall' ),
						'tooltip'  => __( '1 Login attempt permitted per interval (seconds)', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'register',
						'opt_key'  => 'bot_protection_locations',
						'value'    => false,
						'title'    => __( 'Registration', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Protect user registration forms against bots', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'login',
						'opt_key'  => 'bot_protection_locations',
						'value'    => false,
						'title'    => __( 'Login', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Protect user login forms against bots', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'password',
						'opt_key'  => 'bot_protection_locations',
						'value'    => false,
						'title'    => __( 'Password Reset', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Protect password reset forms against bots', 'wp-simple-firewall' ),
					],
				],
			],
			'session_lock'  => [
				'title' => __( 'Session lock', 'wp-simple-firewall' ),
				'opts'  => [
					[
						'item_key' => 'useragent',
						'opt_key'  => 'session_lock',
						'value'    => false,
						'title'    => __( 'Browser', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Lock user sessions to browser detected at login', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'ip',
						'opt_key'  => 'session_lock',
						'value'    => false,
						'title'    => __( 'IP Address', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Lock user sessions to IP address detected at login', 'wp-simple-firewall' ),
					],
				],
			],
			'firewall'      => [
				'title' => __( 'Firewall', 'wp-simple-firewall' ),
				'opts'  => [
					[
						'item_key' => 'disable_file_editing',
						'opt_key'  => 'disable_file_editing',
						'value'    => 'N',
						'title'    => __( 'WP File Editing', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Restrict file editing within WP admin dashboard', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'block_author_discovery',
						'opt_key'  => 'block_author_discovery',
						'value'    => 'N',
						'title'    => __( 'Username fishing', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Block username fishing/enumeration requests', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'disable_xmlrpc',
						'opt_key'  => 'disable_xmlrpc',
						'value'    => 'N',
						'title'    => __( 'Disable XML-RPC', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Disable processing of requests sent to XML-RPC endpoint', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'disable_anonymous_restapi',
						'opt_key'  => 'disable_anonymous_restapi',
						'value'    => 'N',
						'title'    => __( 'Block Anon REST API', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Disable anonymous requests to REST API endpoint', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'block_dir_traversal',
						'opt_key'  => 'block_dir_traversal',
						'value'    => 'N',
						'title'    => sprintf( '%s: %s', 'WAF', __( 'Dir Traversal', 'wp-simple-firewall' ) ),
						'tooltip'  => sprintf( '%s: %s', __( 'WAF Rule', 'wp-simple-firewall' ), __( 'Block directory traversal requests', 'wp-simple-firewall' ) ),
					],
					[
						'item_key' => 'block_sql_queries',
						'opt_key'  => 'block_sql_queries',
						'value'    => 'N',
						'title'    => sprintf( '%s: %s', 'WAF', __( 'SQL Queries', 'wp-simple-firewall' ) ),
						'tooltip'  => sprintf( '%s: %s', __( 'WAF Rule', 'wp-simple-firewall' ), __( 'Block sql queries in requests', 'wp-simple-firewall' ) ),
					],
					[
						'item_key' => 'block_php_code',
						'opt_key'  => 'block_php_code',
						'value'    => 'N',
						'title'    => sprintf( '%s: %s', 'WAF', __( 'PHP Code', 'wp-simple-firewall' ) ),
						'tooltip'  => sprintf( '%s: %s', __( 'WAF Rule', 'wp-simple-firewall' ), __( 'Block PHP code in requests', 'wp-simple-firewall' ) ),
					],
					[
						'item_key' => 'block_aggressive',
						'opt_key'  => 'block_aggressive',
						'value'    => 'N',
						'title'    => sprintf( '%s: %s', 'WAF', __( 'Aggressive Rules', 'wp-simple-firewall' ) ),
						'tooltip'  => sprintf( '%s: %s', __( 'WAF Rule', 'wp-simple-firewall' ), __( 'Aggressive ruleset to block malicious requests', 'wp-simple-firewall' ) ),
					],
				],
			],
			'spam'          => [
				'title' => __( 'SPAM blocking', 'wp-simple-firewall' ),
				'opts'  => [
					[
						'item_key' => 'enable_antibot_comments',
						'opt_key'  => 'enable_antibot_comments',
						'value'    => 'N',
						'title'    => __( 'Bot SPAM', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Block WP comments posted by bots', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'enable_comments_human_spam_filter',
						'opt_key'  => 'enable_comments_human_spam_filter',
						'value'    => 'N',
						'title'    => __( 'Human SPAM', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Block WP comments posted by humans that appear to be SPAM', 'wp-simple-firewall' ),
					],
					[
						'item_key' => 'comments_cooldown',
						'opt_key'  => 'comments_cooldown',
						'value'    => 0,
						'title'    => __( 'Comments Cooldown', 'wp-simple-firewall' ),
						'tooltip'  => __( '1 WP comment post permitted per interval (seconds)', 'wp-simple-firewall' ),
					],
				],
			],
			'integrations'  => [
				'title' => __( 'Integrations', 'wp-simple-firewall' ),
				'opts'  => [
					[
						'item_key' => 'enable_auto_integrations',
						'opt_key'  => 'enable_auto_integrations',
						'value'    => 'N',
						'title'    => __( 'Auto-Integrations', 'wp-simple-firewall' ),
						'tooltip'  => __( 'Automatically detect and enable 3rd party integrations as they become available', 'wp-simple-firewall' ),
					],
				],
			],
		];
	}
}