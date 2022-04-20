<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Blocks\RenderBlockPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\QueryRemainingOffenses;
use FernleafSystems\Wordpress\Services\Services;

class RenderBlockAuthorFishing extends BaseBlockPage {

	protected function getPageSpecificData() :array {
		$con = $this->getCon();
		return [
			'strings' => [
				'page_title' => sprintf( '%s | %s', __( 'Block Username Fishing', 'wp-simple-firewall' ), $con->getHumanName() ),
				'title'      => __( 'Username Fishing Blocked', 'wp-simple-firewall' ),
				'subtitle'   => __( 'Username/Author Fishing is disabled on this site.', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getRestrictionDetailsBlurb() :array {
		$additional = [
			sprintf(
				__( 'The %s query parameter has been blocked to protect against username / author fishing.', 'wp-simple-firewall' ),
				'<code>author</code>'
			)
		];

		if ( !$this->getCon()->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() ) {
			$additional[] = sprintf( '<a href="%s" target="_blank">%s</a>',
				'https://shsec.io/7l',
				__( 'Learn More', 'wp-simple-firewall' )
			);
		}

		return array_merge( $additional, parent::getRestrictionDetailsBlurb() );
	}

	protected function getTemplateStub() :string {
		return 'block_page_standard';
	}
}