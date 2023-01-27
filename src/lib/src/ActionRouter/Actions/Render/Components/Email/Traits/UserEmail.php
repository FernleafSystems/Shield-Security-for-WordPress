<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\Traits;

trait UserEmail {

	protected function getEmailFlags() :array {
		$flags = parent::getEmailFlags();
		$flags[ 'is_admin_email' ] = false;
		return $flags;
	}
}