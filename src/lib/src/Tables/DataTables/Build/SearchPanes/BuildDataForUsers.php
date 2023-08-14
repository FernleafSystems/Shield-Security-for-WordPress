<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\SearchPanes;

class BuildDataForUsers {

	public function build( array $userIDs, bool $includeNoUserEntry = true ) :array {
		$users = \array_values( \array_map(
			function ( $result ) {
				return [
					'label' => $result->user_login,
					'value' => (int)$result->ID,
				];
			},
			( new \WP_User_Query( [
				'fields'  => [ 'ID', 'user_login' ],
				'include' => \array_filter( $userIDs )
			] ) )->get_results()
		) );

		if ( $includeNoUserEntry ) {
			\array_unshift( $users, [
				'label' => '- No User Authenticated -',
				'value' => 0,
			] );
		}
		return $users;
	}
}