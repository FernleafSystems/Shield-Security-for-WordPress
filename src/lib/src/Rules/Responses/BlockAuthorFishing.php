<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Services\Services;

class BlockAuthorFishing extends Base {

	const SLUG = 'block_author_fishing';

	protected function execResponse() :bool {
		Services::WpGeneral()->wpDie( sprintf(
			__( 'The "author" query parameter has been blocked by %s to protect against user login name fishing.', 'wp-simple-firewall' )
			.sprintf( '<br /><a href="%s" target="_blank">%s</a>',
				'https://shsec.io/7l',
				__( 'Learn More.', 'wp-simple-firewall' )
			),
			$this->getCon()->getHumanName()
		) );
		return true;
	}
}