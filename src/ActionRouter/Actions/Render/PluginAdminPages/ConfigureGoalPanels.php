<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * Configure is organised by what people want to protect: a few goals, each made of topics, and each
 * topic laid out as its own panel of settings. Every visible option belongs to exactly one panel, or to
 * one of the pages that manage their options themselves; a unit test holds that to the plugin spec so a
 * new option can't go missing.
 *
 * Each panel item names its option and how it is shown:
 *   toggle    a switch (checkbox options)
 *   switch    a switch for a select option with just two choices, one of them 'disabled'
 *   slider    a scale of stops (select options in their spec order, or integer options with 'stops')
 *   segmented a short single choice shown side by side (select options)
 *   radios    a single choice shown as a list (select options with longer choice names)
 *   chips     several choices (multiple_select options)
 *   roles     several WordPress roles (array options holding role slugs)
 *   field     the option's standard control (text, email, password, select, array, read-only text)
 * Items may have 'children': settings that only apply when the item above them is on.
 *
 * @phpstan-type PanelItem array{
 *   key:string,
 *   control:'toggle'|'switch'|'slider'|'segmented'|'radios'|'chips'|'roles'|'field',
 *   stops?:list<int>,
 *   children?:list<array<string,mixed>>
 * }
 * @phpstan-type PanelBlock array{heading?:string, items:list<PanelItem>}
 * @phpstan-type PanelTopic array{icon:string, blocks:list<PanelBlock>, more?:list<PanelItem>}
 * @phpstan-type PanelGoal array{icon:string, topics:array<string,PanelTopic>}
 */
class ConfigureGoalPanels {

	/**
	 * @var array<string,PanelGoal>|null
	 */
	private static ?array $goals = null;

	/**
	 * Option keys managed on their own pages rather than in a Configure panel.
	 * @return array<string,list<string>>
	 */
	public static function ownPageOptions() :array {
		return [
			'login_hide'    => [ 'rename_wplogin_path', 'rename_wplogin_redirect' ],
			'whitelabel'    => [
				'whitelabel_enable',
				'wl_pluginnamemain',
				'wl_namemenu',
				'wl_companyname',
				'wl_description',
				'wl_homeurl',
				'wl_menuiconurl',
				'wl_dashboardlogourl',
				'wl_login2fa_logourl',
			],
			'import_export' => [ 'importexport_enable', 'importexport_masterurl', 'importexport_secretkey' ],
		];
	}

	/**
	 * The layout of every goal, topic and panel: option keys and how each is shown, with no display text.
	 * Settings few sites need to change are listed under "more" in the layout; each goal gathers them into
	 * one Advanced options topic at its end, under the name of the topic each belongs to, where they're
	 * easy to find rather than tucked away inside every topic.
	 * @return array<string,PanelGoal>
	 */
	public static function goals() :array {
		if ( self::$goals === null ) {
			$goals = self::layout();
			foreach ( $goals as &$goal ) {
				$advanced = [];
				foreach ( $goal[ 'topics' ] as $topicKey => &$topic ) {
					if ( !empty( $topic[ 'more' ] ) ) {
						$advanced[] = [ 'heading' => $topicKey, 'items' => $topic[ 'more' ] ];
					}
					unset( $topic[ 'more' ] );
				}
				unset( $topic );
				if ( !empty( $advanced ) ) {
					$goal[ 'topics' ][ 'advanced' ] = [ 'icon' => 'gear', 'blocks' => $advanced ];
				}
			}
			unset( $goal );
			self::$goals = $goals;
		}
		return self::$goals;
	}

	/**
	 * @return array<string,PanelGoal>
	 */
	private static function layout() :array {
		return [
			'control'  => [
				'icon'   => 'shield-lock',
				'topics' => [
					'security_admin' => [
						'icon'   => 'key',
						'blocks' => [
							[ 'items' => [ [ 'key' => 'admin_access_key', 'control' => 'field' ] ] ],
							[ 'items' => [ [ 'key' => 'admin_access_timeout', 'control' => 'slider', 'stops' => [ 5, 15, 30, 60, 120 ] ] ] ],
							[ 'items' => [ [ 'key' => 'allow_email_override', 'control' => 'toggle' ] ] ],
							[ 'items' => [ [ 'key' => 'sec_admin_users', 'control' => 'field' ] ] ],
						],
					],
					'admin_actions'  => [
						'icon'   => 'shield-check',
						'blocks' => [
							[
								'items' => [
									[ 'key' => 'admin_access_restrict_options', 'control' => 'toggle' ],
									[ 'key' => 'admin_access_restrict_admin_users', 'control' => 'toggle' ],
								],
							],
							[
								'items' => [
									[ 'key' => 'admin_access_restrict_plugins', 'control' => 'chips' ],
									[ 'key' => 'admin_access_restrict_themes', 'control' => 'chips' ],
									[ 'key' => 'admin_access_restrict_posts', 'control' => 'chips' ],
								],
							],
						],
					],
				],
			],
			'accounts' => [
				'icon'   => 'people',
				'topics' => [
					'two_factor'  => [
						'icon'   => 'key',
						'blocks' => [
							[
								'heading' => 'verify_methods',
								'items'   => [
									[
										'key'      => 'enable_email_authentication',
										'control'  => 'toggle',
										'children' => [
											[ 'key' => 'two_factor_auth_user_roles', 'control' => 'roles' ],
											[ 'key' => 'email_any_user_set', 'control' => 'toggle' ],
											[ 'key' => 'enable_email_auto_login', 'control' => 'toggle' ],
											[ 'key' => 'suresend_emails', 'control' => 'chips' ],
										],
									],
									[ 'key' => 'enable_google_authenticator', 'control' => 'toggle' ],
									[ 'key' => 'enable_passkeys', 'control' => 'toggle' ],
									[
										'key'      => 'enable_yubikey',
										'control'  => 'toggle',
										'children' => [
											[ 'key' => 'yubikey_app_id', 'control' => 'field' ],
											[ 'key' => 'yubikey_api_key', 'control' => 'field' ],
										],
									],
								],
							],
						],
						'more'   => [
							[ 'key' => 'mfa_skip', 'control' => 'slider', 'stops' => [ 0, 1, 7, 14, 30, 90 ] ],
							[ 'key' => 'allow_backupcodes', 'control' => 'toggle' ],
							[ 'key' => 'mfa_verify_page', 'control' => 'segmented' ],
							[ 'key' => 'mfa_user_setup_pages', 'control' => 'chips' ],
						],
					],
					'passwords'   => [
						'icon'   => 'lock',
						'blocks' => [
							[
								'items' => [
									[
										'key'      => 'enable_password_policies',
										'control'  => 'toggle',
										'children' => [
											[ 'key' => 'pass_min_strength', 'control' => 'slider' ],
											[ 'key' => 'pass_force_existing', 'control' => 'toggle' ],
										],
									],
								],
							],
							[ 'items' => [ [ 'key' => 'pass_prevent_pwned', 'control' => 'toggle' ] ] ],
							[
								'items' => [
									[
										'key'      => 'pass_expire',
										'control'  => 'slider',
										'stops'    => [ 0, 30, 60, 90, 180, 365 ],
										'children' => [
											[ 'key' => 'auto_password', 'control' => 'toggle' ],
										],
									],
								],
							],
						],
					],
					'sessions'    => [
						'icon'   => 'clock-history',
						'blocks' => [
							[
								'items' => [
									[ 'key' => 'session_timeout_interval', 'control' => 'slider', 'stops' => [ 1, 2, 3, 7, 14, 30 ] ],
									[ 'key' => 'session_idle_timeout_interval', 'control' => 'slider', 'stops' => [ 0, 1, 4, 12, 24, 48 ] ],
								],
							],
							[ 'items' => [ [ 'key' => 'session_lock', 'control' => 'chips' ] ] ],
							[ 'items' => [ [ 'key' => 'enable_user_login_email_notification', 'control' => 'toggle' ] ] ],
						],
					],
					'suspensions' => [
						'icon'   => 'person-slash',
						'blocks' => [
							[
								'items' => [
									[
										'key'      => 'auto_idle_days',
										'control'  => 'slider',
										'stops'    => [ 0, 30, 60, 90, 180, 365 ],
										'children' => [
											[ 'key' => 'auto_idle_roles', 'control' => 'roles' ],
										],
									],
								],
							],
							[ 'items' => [ [ 'key' => 'manual_suspend', 'control' => 'toggle' ] ] ],
						],
					],
				],
			],
			'login'    => [
				'icon'   => 'person-lock',
				'topics' => [
					'login_forms'  => [
						'icon'   => 'shield-check',
						'blocks' => [
							[ 'items' => [ [ 'key' => 'bot_protection_locations', 'control' => 'chips' ] ] ],
							[ 'items' => [ [ 'key' => 'login_limit_interval', 'control' => 'slider', 'stops' => [ 0, 2, 5, 10, 30, 60 ] ] ] ],
							[ 'items' => [ [ 'key' => 'user_form_providers', 'control' => 'chips' ] ] ],
						],
					],
					'login_guards' => [
						'icon'   => 'incognito',
						'blocks' => [
							[
								'items' => [
									[ 'key' => 'track_loginfailed', 'control' => 'slider' ],
									[ 'key' => 'track_logininvalid', 'control' => 'slider' ],
								],
							],
							[ 'items' => [ [ 'key' => 'block_author_discovery', 'control' => 'toggle' ] ] ],
						],
					],
					'registration' => [
						'icon'   => 'envelope-check',
						'blocks' => [
							[
								'items' => [
									[
										'key'      => 'reg_email_validate',
										'control'  => 'slider',
										'children' => [
											[ 'key' => 'email_checks', 'control' => 'chips' ],
										],
									],
								],
							],
						],
					],
				],
			],
			'attacks'  => [
				'icon'   => 'shield-shaded',
				'topics' => [
					'firewall'      => [
						'icon'   => 'fire',
						'blocks' => [
							[
								'items' => [
									[ 'key' => 'block_dir_traversal', 'control' => 'toggle' ],
									[ 'key' => 'block_sql_queries', 'control' => 'toggle' ],
									[ 'key' => 'block_field_truncation', 'control' => 'toggle' ],
									[ 'key' => 'block_php_code', 'control' => 'toggle' ],
									[ 'key' => 'block_aggressive', 'control' => 'toggle' ],
								],
							],
						],
						'more'   => [
							[ 'key' => 'page_params_whitelist', 'control' => 'field' ],
						],
					],
					'ip_blocking'   => [
						'icon'   => 'slash-circle',
						'blocks' => [
							[
								'items' => [
									[ 'key' => 'transgression_limit', 'control' => 'slider', 'stops' => [ 3, 5, 7, 10, 15, 20, 30 ] ],
									[ 'key' => 'auto_expire', 'control' => 'slider' ],
								],
							],
							[ 'items' => [ [ 'key' => 'user_auto_recover', 'control' => 'chips' ] ] ],
						],
						'more'   => [
							[ 'key' => 'request_whitelist', 'control' => 'field' ],
							[ 'key' => 'antibot_high_reputation_minimum', 'control' => 'slider', 'stops' => [ 0, 100, 250, 500, 1000 ] ],
						],
					],
					'bot_signals'   => [
						'icon'   => 'robot',
						'blocks' => [
							[
								'items' => [
									[ 'key' => 'track_xmlrpc', 'control' => 'slider' ],
									[ 'key' => 'track_404', 'control' => 'slider' ],
									[ 'key' => 'track_fakewebcrawler', 'control' => 'slider' ],
									[ 'key' => 'track_linkcheese', 'control' => 'slider' ],
									[ 'key' => 'track_invalidscript', 'control' => 'slider' ],
									[ 'key' => 'track_useragent', 'control' => 'slider' ],
								],
							],
						],
					],
					'silentcaptcha' => [
						'icon'   => 'shield-check',
						'blocks' => [
							[
								'items' => [
									[ 'key' => 'silentcaptcha_complexity', 'control' => 'slider' ],
									[ 'key' => 'antibot_minimum', 'control' => 'slider', 'stops' => [ 25, 35, 45, 55, 65, 75 ] ],
								],
							],
							[ 'items' => [ [ 'key' => 'silentcaptcha_cookie_free', 'control' => 'toggle' ] ] ],
						],
					],
					'crowdsec'      => [
						'icon'   => 'people',
						'blocks' => [
							[ 'items' => [ [ 'key' => 'cs_block', 'control' => 'radios' ] ] ],
						],
						'more'   => [
							[ 'key' => 'cs_enroll_id', 'control' => 'field' ],
						],
					],
					'rate_limiting' => [
						'icon'   => 'speedometer2',
						'blocks' => [
							[
								'items' => [
									[
										'key'      => 'enable_limiter',
										'control'  => 'toggle',
										'children' => [
											[ 'key' => 'limit_requests', 'control' => 'field' ],
											[ 'key' => 'limit_time_span', 'control' => 'field' ],
										],
									],
								],
							],
						],
					],
					'apis'          => [
						'icon'   => 'plug',
						'blocks' => [
							[ 'items' => [ [ 'key' => 'disable_xmlrpc', 'control' => 'toggle' ] ] ],
							[
								'items' => [
									[
										'key'      => 'disable_anonymous_restapi',
										'control'  => 'toggle',
										'children' => [
											[ 'key' => 'api_namespace_exclusions', 'control' => 'field' ],
										],
									],
								],
							],
						],
					],
				],
			],
			'code'     => [
				'icon'   => 'bug',
				'topics' => [
					'file_scanning'   => [
						'icon'   => 'file-earmark-code',
						'blocks' => [
							[
								'items' => [
									[
										'key'      => 'enable_core_file_integrity_scan',
										'control'  => 'toggle',
										'children' => [
											[ 'key' => 'file_scan_areas', 'control' => 'chips' ],
											[ 'key' => 'file_repair_areas', 'control' => 'chips' ],
										],
									],
								],
							],
						],
						'more'   => [
							[ 'key' => 'scan_path_exclusions', 'control' => 'field' ],
						],
					],
					'scan_schedule'   => [
						'icon'   => 'calendar-check',
						'blocks' => [
							[ 'items' => [ [ 'key' => 'scan_frequency', 'control' => 'slider' ] ] ],
						],
					],
					'vulnerabilities' => [
						'icon'   => 'exclamation-triangle',
						'blocks' => [
							[
								'items' => [
									[
										'key'      => 'enable_wpvuln_scan',
										'control'  => 'toggle',
										'children' => [
											[ 'key' => 'wpvuln_scan_autoupdate', 'control' => 'toggle' ],
										],
									],
									[ 'key' => 'update_delay', 'control' => 'slider', 'stops' => [ 0, 1, 2, 3, 5, 7 ] ],
								],
							],
							[ 'items' => [ [ 'key' => 'enabled_scan_apc', 'control' => 'toggle' ] ] ],
						],
					],
					'file_locker'     => [
						'icon'   => 'file-earmark-lock',
						'blocks' => [
							[ 'items' => [ [ 'key' => 'file_locker', 'control' => 'chips' ] ] ],
						],
					],
				],
			],
			'spam'     => [
				'icon'   => 'chat-dots',
				'topics' => [
					'comment_spam'  => [
						'icon'   => 'chat-left-text',
						'blocks' => [
							[
								'items' => [
									[
										'key'      => 'enable_antibot_comments',
										'control'  => 'toggle',
										'children' => [
											[ 'key' => 'comments_default_action_spam_bot', 'control' => 'radios' ],
										],
									],
								],
							],
							[
								'items' => [
									[
										'key'      => 'enable_comments_human_spam_filter',
										'control'  => 'toggle',
										'children' => [
											[ 'key' => 'comments_default_action_human_spam', 'control' => 'radios' ],
										],
									],
								],
							],
							[ 'items' => [ [ 'key' => 'comments_cooldown', 'control' => 'slider', 'stops' => [ 0, 5, 10, 30, 60 ] ] ] ],
						],
						'more'   => [
							[ 'key' => 'trusted_commenter_minimum', 'control' => 'slider', 'stops' => [ 1, 2, 3, 5, 10 ] ],
							[ 'key' => 'trusted_user_roles', 'control' => 'roles' ],
						],
					],
					'contact_forms' => [
						'icon'   => 'ui-checks',
						'blocks' => [
							[ 'items' => [ [ 'key' => 'form_spam_providers', 'control' => 'chips' ] ] ],
						],
					],
				],
			],
			'harden'   => [
				'icon'   => 'wrench-adjustable',
				'topics' => [
					'file_editing' => [
						'icon'   => 'pencil-square',
						'blocks' => [
							[ 'items' => [ [ 'key' => 'disable_file_editing', 'control' => 'toggle' ] ] ],
							[ 'items' => [ [ 'key' => 'clean_wp_rubbish', 'control' => 'toggle' ] ] ],
						],
					],
					'headers'      => [
						'icon'   => 'file-earmark-lock',
						'blocks' => [
							[ 'items' => [ [ 'key' => 'x_frame', 'control' => 'radios' ] ] ],
							[
								'items' => [
									[ 'key' => 'x_xss_protect', 'control' => 'toggle' ],
									[ 'key' => 'x_content_type', 'control' => 'toggle' ],
								],
							],
							[ 'items' => [ [ 'key' => 'x_referrer_policy', 'control' => 'field' ] ] ],
						],
					],
					'csp'          => [
						'icon'   => 'code-square',
						'blocks' => [
							[
								'items' => [
									[
										'key'      => 'enable_x_content_security_policy',
										'control'  => 'toggle',
										'children' => [
											[ 'key' => 'xcsp_custom', 'control' => 'field' ],
										],
									],
								],
							],
						],
					],
				],
			],
			'shield'   => [
				'icon'   => 'sliders',
				'topics' => [
					'general'      => [
						'icon'   => 'sliders',
						'blocks' => [
							[ 'items' => [ [ 'key' => 'global_enable_plugin_features', 'control' => 'toggle' ] ] ],
							[ 'heading' => 'visitor_ip', 'items' => [ [ 'key' => 'visitor_address_source', 'control' => 'field' ] ] ],
							[ 'heading' => 'updates', 'items' => [ [ 'key' => 'autoupdate_plugin_self', 'control' => 'segmented' ] ] ],
							[ 'heading' => 'on_your_site', 'items' => [ [ 'key' => 'display_plugin_badge', 'control' => 'segmented' ] ] ],
							[
								'heading' => 'in_your_dashboard',
								'items'   => [
									[ 'key' => 'enable_upgrade_admin_notice', 'control' => 'toggle' ],
									[ 'key' => 'enable_tracking', 'control' => 'toggle' ],
								],
							],
						],
						'more'   => [
							[ 'key' => 'enable_mu', 'control' => 'toggle' ],
							[ 'key' => 'delete_on_deactivate', 'control' => 'toggle' ],
							[ 'key' => 'preferred_temp_dir', 'control' => 'field' ],
							[ 'key' => 'language_override', 'control' => 'field' ],
							[ 'key' => 'enable_beta', 'control' => 'toggle' ],
						],
					],
				],
			],
			'integrations' => [
				'icon'   => 'puzzle',
				'topics' => [
					'integrations' => [
						'icon'   => 'puzzle',
						'blocks' => [
							[
								'items' => [
									[ 'key' => 'enable_mainwp', 'control' => 'toggle' ],
									[ 'key' => 'enable_auto_integrations', 'control' => 'toggle' ],
								],
							],
						],
					],
				],
			],
			'alerts'   => [
				'icon'   => 'bell',
				'topics' => [
					'reports'        => [
						'icon'   => 'envelope',
						'blocks' => [
							[ 'items' => [ [ 'key' => 'block_send_email_address', 'control' => 'field' ] ] ],
							[
								'items' => [
									[ 'key' => 'frequency_alert', 'control' => 'segmented' ],
									[ 'key' => 'frequency_info', 'control' => 'segmented' ],
								],
							],
						],
					],
					'instant_alerts' => [
						'icon'   => 'bell',
						'blocks' => [
							[
								'heading' => 'alert_me_when',
								'items'   => [
									[ 'key' => 'instant_alert_shield_deactivated', 'control' => 'switch' ],
									[ 'key' => 'instant_alert_hidden_plugins', 'control' => 'switch' ],
									[ 'key' => 'instant_alert_admin_login', 'control' => 'switch' ],
									[ 'key' => 'instant_alert_admins', 'control' => 'switch' ],
									[ 'key' => 'instant_alert_firewall_block', 'control' => 'switch' ],
									[ 'key' => 'instant_alert_vulnerabilities', 'control' => 'switch' ],
									[ 'key' => 'instant_alert_filelocker', 'control' => 'switch' ],
								],
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Names and one-line summaries for goals and topics, and the headings used inside panels. Options
	 * themselves keep the plugin's own names and summaries.
	 * @return array{
	 *   goals:array<string,array{label:string,tab:string,summary:string}>,
	 *   topics:array<string,array{label:string,summary:string}>,
	 *   headings:array<string,string>
	 * }
	 */
	public static function labels() :array {
		return [
			'goals'    => [
				'control'  => [
					'label'   => __( 'Control who changes your site', 'wp-simple-firewall' ),
					'tab'     => __( 'Security Admins', 'wp-simple-firewall' ),
					'summary' => __( 'Stop a compromised admin account changing Shield or other admins.', 'wp-simple-firewall' ),
				],
				'accounts' => [
					'label'   => __( 'Protect user accounts', 'wp-simple-firewall' ),
					'tab'     => __( 'Users', 'wp-simple-firewall' ),
					'summary' => __( 'Keep the people who log in safe, even when a password leaks.', 'wp-simple-firewall' ),
				],
				'login'    => [
					'label'   => __( 'Stop login attacks', 'wp-simple-firewall' ),
					'tab'     => __( 'Login', 'wp-simple-firewall' ),
					'summary' => __( 'Stop bots guessing passwords and making fake accounts.', 'wp-simple-firewall' ),
				],
				'attacks'  => [
					'label'   => __( 'Block hackers and bad bots', 'wp-simple-firewall' ),
					'tab'     => __( 'Attacks', 'wp-simple-firewall' ),
					'summary' => __( 'Keep malicious requests and bad bots away from your site.', 'wp-simple-firewall' ),
				],
				'code'     => [
					'label'   => __( 'Find hacked and risky code', 'wp-simple-firewall' ),
					'tab'     => __( 'Scans', 'wp-simple-firewall' ),
					'summary' => __( 'Spot changed, malicious or vulnerable code quickly.', 'wp-simple-firewall' ),
				],
				'spam'     => [
					'label'   => __( 'Keep out spam', 'wp-simple-firewall' ),
					'tab'     => __( 'Spam', 'wp-simple-firewall' ),
					'summary' => __( 'Block spam comments and form submissions.', 'wp-simple-firewall' ),
				],
				'harden'   => [
					'label'   => __( 'Harden WordPress', 'wp-simple-firewall' ),
					'tab'     => __( 'Hardening', 'wp-simple-firewall' ),
					'summary' => __( 'Close doors WordPress leaves open by default.', 'wp-simple-firewall' ),
				],
				'shield'   => [
					'label'   => __( 'Shield settings', 'wp-simple-firewall' ),
					'tab'     => __( 'Shield', 'wp-simple-firewall' ),
					'summary' => __( 'How Shield itself runs on this site.', 'wp-simple-firewall' ),
				],
				'integrations' => [
					'label'   => __( 'Integrations', 'wp-simple-firewall' ),
					'tab'     => __( 'Integrations', 'wp-simple-firewall' ),
					'summary' => __( 'Connect Shield with MainWP and the plugins on your site.', 'wp-simple-firewall' ),
				],
				'alerts'   => [
					'label'   => __( 'Reports and alerts', 'wp-simple-firewall' ),
					'tab'     => __( 'Reports', 'wp-simple-firewall' ),
					'summary' => __( 'Who hears about what, and how often.', 'wp-simple-firewall' ),
				],
			],
			'topics'   => [
				'two_factor'      => [ 'label' => __( 'Two-factor authentication', 'wp-simple-firewall' ), 'summary' => __( 'Ways people verify who they are after their password.', 'wp-simple-firewall' ) ],
				'passwords'       => [ 'label' => __( 'Passwords', 'wp-simple-firewall' ), 'summary' => __( 'Password strength, breached passwords and expiry.', 'wp-simple-firewall' ) ],
				'sessions'        => [ 'label' => __( 'Sessions', 'wp-simple-firewall' ), 'summary' => __( 'How long logins last, and what they are locked to.', 'wp-simple-firewall' ) ],
				'suspensions'     => [ 'label' => __( 'Suspended accounts', 'wp-simple-firewall' ), 'summary' => __( 'Suspend accounts that are no longer used.', 'wp-simple-firewall' ) ],
				'login_forms'     => [ 'label' => __( 'Bot protection on forms', 'wp-simple-firewall' ), 'summary' => __( 'Which login and sign-up forms are checked for bots.', 'wp-simple-firewall' ) ],
				'login_guards'    => [ 'label' => __( 'Password guessing', 'wp-simple-firewall' ), 'summary' => __( 'How Shield reacts to failed logins and username probing.', 'wp-simple-firewall' ) ],
				'registration'    => [ 'label' => __( 'Fake sign-ups', 'wp-simple-firewall' ), 'summary' => __( 'Checks on the email address of new registrations.', 'wp-simple-firewall' ) ],
				'firewall'        => [ 'label' => __( 'Firewall rules', 'wp-simple-firewall' ), 'summary' => __( 'Which kinds of malicious request are blocked.', 'wp-simple-firewall' ) ],
				'ip_blocking'     => [ 'label' => __( 'Automatic IP blocking', 'wp-simple-firewall' ), 'summary' => __( 'When an IP address is blocked, and for how long.', 'wp-simple-firewall' ) ],
				'bot_signals'     => [ 'label' => __( 'Bot signals', 'wp-simple-firewall' ), 'summary' => __( 'How hard Shield reacts to each sign of a bot.', 'wp-simple-firewall' ) ],
				'silentcaptcha'   => [ 'label' => __( 'silentCAPTCHA', 'wp-simple-firewall' ), 'summary' => __( 'How strictly visitors are checked for bots.', 'wp-simple-firewall' ) ],
				'crowdsec'        => [ 'label' => __( 'CrowdSec', 'wp-simple-firewall' ), 'summary' => __( 'The community blocklist of known bad IP addresses.', 'wp-simple-firewall' ) ],
				'rate_limiting'   => [ 'label' => __( 'Rate limiting', 'wp-simple-firewall' ), 'summary' => __( 'How many requests one visitor can make.', 'wp-simple-firewall' ) ],
				'apis'            => [ 'label' => __( 'XML-RPC and REST API', 'wp-simple-firewall' ), 'summary' => __( 'Access to the XML-RPC endpoint and the anonymous REST API.', 'wp-simple-firewall' ) ],
				'file_scanning'   => [ 'label' => __( 'File scanning', 'wp-simple-firewall' ), 'summary' => __( 'Which files are scanned and repaired.', 'wp-simple-firewall' ) ],
				'scan_schedule'   => [ 'label' => __( 'Scan schedule', 'wp-simple-firewall' ), 'summary' => __( 'How often scans run on their own.', 'wp-simple-firewall' ) ],
				'vulnerabilities' => [ 'label' => __( 'Vulnerable and abandoned plugins', 'wp-simple-firewall' ), 'summary' => __( 'Known vulnerabilities, automatic updates and abandoned plugins.', 'wp-simple-firewall' ) ],
				'file_locker'     => [ 'label' => __( 'FileLocker', 'wp-simple-firewall' ), 'summary' => __( 'Critical files locked against changes.', 'wp-simple-firewall' ) ],
				'comment_spam'    => [ 'label' => __( 'Comment spam', 'wp-simple-firewall' ), 'summary' => __( 'Spam from bots and people, and who is trusted.', 'wp-simple-firewall' ) ],
				'contact_forms'   => [ 'label' => __( 'Contact forms', 'wp-simple-firewall' ), 'summary' => __( 'Which form plugins are checked for spam.', 'wp-simple-firewall' ) ],
				'file_editing'    => [ 'label' => __( 'Admin file editing', 'wp-simple-firewall' ), 'summary' => __( 'Editing code from the dashboard, and leftover WordPress files.', 'wp-simple-firewall' ) ],
				'headers'         => [ 'label' => __( 'Browser security headers', 'wp-simple-firewall' ), 'summary' => __( 'Headers that tell browsers how to protect your visitors.', 'wp-simple-firewall' ) ],
				'csp'             => [ 'label' => __( 'Content Security Policy', 'wp-simple-firewall' ), 'summary' => __( 'Which sources your pages may load.', 'wp-simple-firewall' ) ],
				'security_admin'  => [ 'label' => __( 'Security Admin', 'wp-simple-firewall' ), 'summary' => __( 'The PIN that protects Shield and key settings.', 'wp-simple-firewall' ) ],
				'admin_actions'   => [ 'label' => __( 'What the PIN protects', 'wp-simple-firewall' ), 'summary' => __( 'WordPress settings and actions that need the Security Admin PIN.', 'wp-simple-firewall' ) ],
				'reports'         => [ 'label' => __( 'Reports', 'wp-simple-firewall' ), 'summary' => __( 'Where reports go, and how often.', 'wp-simple-firewall' ) ],
				'instant_alerts'  => [ 'label' => __( 'Instant alerts', 'wp-simple-firewall' ), 'summary' => __( 'Alerts sent as soon as something happens.', 'wp-simple-firewall' ) ],
				'general'         => [ 'label' => __( 'General', 'wp-simple-firewall' ), 'summary' => __( 'How Shield runs on this site.', 'wp-simple-firewall' ) ],
				'integrations'    => [ 'label' => __( 'Integrations', 'wp-simple-firewall' ), 'summary' => __( 'MainWP and automatic integrations.', 'wp-simple-firewall' ) ],
				'advanced'        => [ 'label' => __( 'Advanced options', 'wp-simple-firewall' ), 'summary' => __( 'Settings few sites need to change.', 'wp-simple-firewall' ) ],
			],
			'headings' => [
				'verify_methods'    => __( 'Ways people can verify', 'wp-simple-firewall' ),
				'alert_me_when'     => __( 'Alert me straight away when', 'wp-simple-firewall' ),
				'visitor_ip'        => __( 'Visitor IP addresses', 'wp-simple-firewall' ),
				'updates'           => __( 'Updates', 'wp-simple-firewall' ),
				'on_your_site'      => __( 'On your site', 'wp-simple-firewall' ),
				'in_your_dashboard' => __( 'In your dashboard', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * Every option key a topic's panel shows, in panel order.
	 * @return list<string>
	 */
	public static function topicOptionKeys( string $goalKey, string $topicKey ) :array {
		$topic = self::goals()[ $goalKey ][ 'topics' ][ $topicKey ] ?? null;
		if ( !\is_array( $topic ) ) {
			return [];
		}
		$items = [];
		foreach ( $topic[ 'blocks' ] as $block ) {
			$items = \array_merge( $items, $block[ 'items' ] );
		}
		return self::flattenItemKeys( $items );
	}

	/**
	 * @return array{goal:string,topic:string}|null
	 */
	public static function topicForOption( string $optionKey ) :?array {
		foreach ( self::goals() as $goalKey => $goal ) {
			foreach ( \array_keys( $goal[ 'topics' ] ) as $topicKey ) {
				if ( \in_array( $optionKey, self::topicOptionKeys( $goalKey, $topicKey ), true ) ) {
					return [ 'goal' => $goalKey, 'topic' => $topicKey ];
				}
			}
		}
		return null;
	}

	/**
	 * @param list<array<string,mixed>> $items
	 * @return list<string>
	 */
	public static function flattenItemKeys( array $items ) :array {
		$keys = [];
		foreach ( $items as $item ) {
			$keys[] = (string)$item[ 'key' ];
			$keys = \array_merge( $keys, self::flattenItemKeys( $item[ 'children' ] ?? [] ) );
		}
		return $keys;
	}
}
