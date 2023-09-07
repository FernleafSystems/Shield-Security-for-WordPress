<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Options;
use FernleafSystems\Wordpress\Services\Services;

class ConflictAkismet extends Base {

	public function check() :?array {
		$mod = self::con()->getModule_Comments();
		/** @var Options $opts */
		$opts = $mod->opts();

		$issue = null;

		if ( $mod->isModuleEnabled() && $opts->isEnabledHumanCheck() ) {

			$WPP = Services::WpPlugins();
			$file = $WPP->findPluginFileFromDirName( 'akismet' );
			if ( !empty( $file ) && $WPP->isActive( $file ) ) {
				$issue = [
					'id'        => 'conflict_akismet',
					'type'      => 'warning',
					'text'      => [
						sprintf(
							'%s %s',
							__( "You may get unreliable results while Akismet is operating alongside Shield's Human SPAM protection.", 'wp-simple-firewall' ),
							sprintf( '<a href="%s" class="">%s</a>',
								$WPP->getUrl_Deactivate( $WPP->findPluginFileFromDirName( 'akismet' ) ),
								__( 'Deactivate Akismet', 'wp-simple-firewall' )
							)
						)
					],
					'locations' => [
						'shield_admin_top_page',
					],
					'flags'     => [
						'conflict',
					]
				];
			}
		}
		return $issue;
	}
}