<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions,
	Responses
};

class BotTrackInvalidScript extends BuildRuleCoreShieldBase {

	protected function getName() :string {
		return 'Bot-Track Invalid Script';
	}

	protected function getDescription() :string {
		return 'Track probing bots that send requests to invalid scripts.';
	}

	protected function getSlug() :string {
		return 'shield/is_bot_probe_invalidscript';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'rule'         => Shield\Modules\Plugin\Rules\Build\RequestBypassesAllRestrictions::SLUG,
					'invert_match' => true
				],
				[
					'action' => Conditions\IsNotLoggedInNormal::SLUG
				],
				[
					'action'       => Conditions\MatchRequestScriptName::SLUG,
					'invert_match' => true,
					'params'       => [
						'is_match_regex'     => false,
						'match_script_names' => $this->getAllowedScripts(),
					],
				],
			]
		];
	}

	protected function getResponses() :array {
		/** @var Shield\Modules\IPs\Options $opts */
		$opts = $this->getOptions();
		return [
			[
				'action' => Responses\EventFire::SLUG,
				'params' => [
					'event'            => 'bottrack_invalidscript',
					'offense_count'    => $opts->getOffenseCountFor( 'track_invalidscript' ),
					'block'            => $opts->isTrackOptImmediateBlock( 'track_invalidscript' ),
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
		];
	}

	private function getAllowedScripts() :array {
		return [
			'index.php',
			'admin-ajax.php',
			'wp-activate.php',
			'wp-links-opml.php',
			'wp-cron.php',
			'wp-login.php',
			'wp-mail.php',
			'wp-comments-post.php',
			'wp-signup.php',
			'wp-trackback.php',
			'xmlrpc.php',
			'admin.php',
		];
	}
}