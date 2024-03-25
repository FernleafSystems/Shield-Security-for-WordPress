<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Sessions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BuildSearchPanesData {

	use PluginControllerConsumer;

	public function build() :array {
		return [
			'options' => \array_map( '\array_values', [
				'uid' => $this->buildForUserLogin(),
			] )
		];
	}

	private function buildForUserLogin() :array {
		$DB = Services::WpDb();
		$select = $DB->selectCustom(
			sprintf( "SELECT `u`.`user_login` as `uname`, `um`.`user_id` as `uid`
						FROM `%s` as `u`
						INNER JOIN `%s` as `um` ON `u`.`id`=`um`.`user_id`
						WHERE `um`.`meta_key`='session_tokens'",
				$DB->loadWpdb()->users,
				$DB->loadWpdb()->usermeta
			)
		);
		return \array_filter( \array_map(
			function ( $row ) {
				$data = null;
				if ( !empty( $row[ 'uname' ] ) ) {
					$data = [
						'value' => $row[ 'uid' ],
						'label' => $row[ 'uname' ],
					];
				}
				return $data;
			},
			\is_array( $select ) ? $select : []
		) );
	}
}