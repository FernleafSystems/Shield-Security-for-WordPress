<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

use FernleafSystems\Wordpress\Services\Services;

trait InvestigationContextLinks {

	protected function getUserHref( int $uid ) :string {
		$user = $this->resolveUser( $uid );
		return empty( $user )
			? \sprintf( 'Unavailable (ID:%s)', $uid )
			: \sprintf(
				'<a href="%s">%s</a>',
				self::con()->plugin_urls->investigateByUser( (string)$uid ),
				esc_html( $user->user_login )
			);
	}

	protected function getIpAnalysisLink( string $ip ) :string {
		$content = parent::getIpAnalysisLink( $ip );
		if ( Services::IP()->isValidIp( $ip ) ) {
			$content .= \sprintf(
				'<a href="%s" class="ms-1 investigate-ip-deeplink" title="%s"><i class="%s" aria-hidden="true"></i></a>',
				self::con()->plugin_urls->investigateByIp( $ip ),
				esc_attr__( 'Investigate IP', 'wp-simple-firewall' ),
				self::con()->svgs->iconClass( 'box-arrow-up-right' )
			);
		}
		return $content;
	}
}
