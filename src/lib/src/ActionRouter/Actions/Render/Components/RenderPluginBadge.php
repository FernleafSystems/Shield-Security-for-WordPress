<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	PluginBadgeClose,
	Traits};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class RenderPluginBadge extends BasePlugin {

	use Traits\AuthNotRequired;

	public const SLUG = 'render_plugin_badge';
	public const TEMPLATE = '/snippets/plugin_badge_widget.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		$wlCon = $con->getModule_SecAdmin()->getWhiteLabelController();

		if ( $wlCon->isEnabled() && $wlCon->isReplacePluginBadge() ) {
			/** @var Options $secAdminOpts */
			$secAdminOpts = $con->getModule_SecAdmin()->getOptions();
			$badgeUrl = $secAdminOpts->getOpt( 'wl_homeurl' );
			$name = $secAdminOpts->getOpt( 'wl_pluginnamemain' );
			$logo = $secAdminOpts->getOpt( 'wl_dashboardlogourl' );
		}
		else {
			$badgeUrl = 'https://shsec.io/wpsecurityfirewall';
			$name = $con->getHumanName();
			$logo = $con->urls->forImage( 'shield/shield-security-logo-colour-32px.png' );

			$lic = $con->getModule_License()
					   ->getLicenseHandler()
					   ->getLicense();
			if ( !empty( $lic->aff_ref ) ) {
				$badgeUrl = URL::Build( $badgeUrl, [ 'ref' => $lic->aff_ref ] );
			}
		}

		$badgeAttrs = [
			'name'         => $name,
			'url'          => $badgeUrl,
			'logo'         => $logo,
			'protected_by' => apply_filters( 'icwp_shield_plugin_badge_text',
				sprintf( __( 'This Site Is Protected By %s', 'wp-simple-firewall' ),
					'<br/><span class="plugin-badge-name">'.$name.'</span>' )
			),
			'custom_css'   => '',
		];
		if ( $con->isPremiumActive() ) {
			$badgeAttrs = apply_filters( 'icwp_shield_plugin_badge_attributes', $badgeAttrs, $this->action_data[ 'is_floating' ] );
		}

		return [
			'ajax'    => [
				'plugin_badge_close' => ActionData::BuildJson( PluginBadgeClose::SLUG ),
			],
			'content' => [
				'custom_css' => esc_js( $badgeAttrs[ 'custom_css' ] ),
			],
			'flags'   => [
				'nofollow'    => apply_filters( 'icwp_shield_badge_relnofollow', false ),
				'is_floating' => $this->action_data[ 'is_floating' ]
			],
			'hrefs'   => [
				'badge' => $badgeAttrs[ 'url' ],
				'logo'  => $badgeAttrs[ 'logo' ],
			],
			'strings' => [
				'protected' => $badgeAttrs[ 'protected_by' ],
				'name'      => $badgeAttrs[ 'name' ],
			],
		];
	}
}