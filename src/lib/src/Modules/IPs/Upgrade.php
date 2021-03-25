<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs\Delete;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends Base\Upgrade {

	protected function upgrade_1010() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Delete $del */
		$del = $mod->getDbHandler_IPs()->getQueryDeleter();
		$del->filterByLabel( 'iControlWP' )->query();
	}

	protected function upgrade_905() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$schema = $mod->getDbHandler_IPs()->getTableSchema();
		Services::WpDb()->doSql(
			sprintf( "ALTER TABLE `%s` MODIFY `%s` %s;",
				$schema->table, 'ip', $schema->enumerateColumns()[ 'ip' ] )
		);
	}

	/**
	 * Support larger transgression counts [smallint(1) => int(10)]
	 */
	protected function upgrade_911() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$schema = $mod->getDbHandler_IPs()->getTableSchema();
		Services::WpDb()->doSql(
			sprintf( "ALTER TABLE `%s` MODIFY `%s` %s;",
				$schema->table,
				'transgressions',
				$schema->enumerateColumns()[ 'transgressions' ]
			)
		);
	}

	/**
	 * Support Magic Links for logged-in users.
	 */
	protected function upgrade_920() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$current = $opts->getOpt( 'user_auto_recover' );
		if ( !is_array( $current ) ) {
			$current = ( $current === 'gasp' ) ? [ 'gasp' ] : [];
			$opts->setOpt( 'user_auto_recover', $current );
		}
	}
}