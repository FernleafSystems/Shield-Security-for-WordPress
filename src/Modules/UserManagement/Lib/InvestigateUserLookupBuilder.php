<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib;

use FernleafSystems\Wordpress\Services\Services;

class InvestigateUserLookupBuilder {

	public function shouldUseStaticSelect( int $threshold = 10 ) :bool {
		return $this->getTotalUsers() <= max( 1, $threshold );
	}

	public function getTotalUsers() :int {
		$counts = \count_users();
		return max( 0, (int)( $counts[ 'total_users' ] ?? 0 ) );
	}

	/**
	 * @return array<int,array{value:string,label:string}>
	 */
	public function buildStaticOptions( int $limit = 10 ) :array {
		return \array_map(
			fn( \WP_User $user ) :array => [
				'value' => (string)$user->ID,
				'label' => $this->formatLabel( $user ),
			],
			$this->loadUsersForStaticSelect( $limit )
		);
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	public function searchResults( string $search, int $limit = 20 ) :array {
		return \array_map(
			fn( \WP_User $user ) :array => [
				'id'   => (string)$user->ID,
				'text' => $this->formatLabel( $user ),
			],
			$this->searchUsers( $search, $limit )
		);
	}

	public function formatLabel( \WP_User $user ) :string {
		$role = $this->formatPrimaryRole( $user );
		$username = trim( (string)( $user->user_login ?? '' ) );
		if ( $username === '' ) {
			$username = trim( (string)( $user->display_name ?? '' ) );
		}

		$label = sprintf( '[ID:%d | %s] %s', (int)$user->ID, $role, $username );
		$email = trim( (string)( $user->user_email ?? '' ) );
		if ( $email !== '' ) {
			$label .= ' | '.$email;
		}
		return $label;
	}

	private function formatPrimaryRole( \WP_User $user ) :string {
		$roles = \is_array( $user->roles ?? null ) ? \array_values( $user->roles ) : [];
		$primaryRole = trim( (string)( $roles[ 0 ] ?? '' ) );
		if ( $primaryRole === '' ) {
			return __( 'Unknown', 'wp-simple-firewall' );
		}
		return trim( \ucwords( str_replace( '_', ' ', $primaryRole ) ) );
	}

	/**
	 * @return \WP_User[]
	 */
	private function loadUsersForStaticSelect( int $limit ) :array {
		$users = ( new \WP_User_Query( [
			'number'   => max( 1, $limit ),
			'orderby'  => 'ID',
			'order'    => 'ASC',
			'fields'   => 'all',
			'count_total' => false,
		] ) )->get_results();
		return \array_values( \array_filter(
			\is_array( $users ) ? $users : [],
			static fn( $user ) :bool => $user instanceof \WP_User
		) );
	}

	/**
	 * @return \WP_User[]
	 */
	private function searchUsers( string $search, int $limit ) :array {
		$search = strtolower( trim( sanitize_text_field( $search ) ) );
		if ( strlen( $search ) < 1 ) {
			return [];
		}

		$users = \ctype_digit( $search )
			? $this->searchUsersByNumericTerm( $search, $limit )
			: $this->searchUsersByTextTerm( $search, $limit );

		return $this->deduplicateUsersById( $users );
	}

	/**
	 * @return \WP_User[]
	 */
	private function searchUsersByNumericTerm( string $search, int $limit ) :array {
		$wpdb = Services::WpDb()->loadWpdb();
		$like = '%'.$wpdb->esc_like( $search ).'%';
		$rows = $wpdb->get_results(
			(string)$wpdb->prepare(
				\sprintf(
					"SELECT `ID`
					FROM `%s`
					WHERE CAST(`ID` AS CHAR) LIKE %%s
						OR `user_login` LIKE %%s
						OR `user_email` LIKE %%s
						OR `display_name` LIKE %%s
					ORDER BY (`ID` = %%d) DESC, `ID` ASC
					LIMIT %%d",
					$wpdb->users
				),
				$like,
				$like,
				$like,
				$like,
				(int)$search,
				max( 1, $limit )
			)
		);

		if ( !\is_array( $rows ) ) {
			return [];
		}

		$users = [];
		foreach ( $rows as $row ) {
			$user = \is_object( $row ) ? Services::WpUsers()->getUserById( (int)( $row->ID ?? 0 ) ) : null;
			if ( $user instanceof \WP_User ) {
				$users[] = $user;
			}
		}
		return $users;
	}

	/**
	 * @return \WP_User[]
	 */
	private function searchUsersByTextTerm( string $search, int $limit ) :array {
		$rows = ( new \WP_User_Query( [
			'number'         => max( 1, $limit ),
			'search'         => '*'.$search.'*',
			'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'all',
			'count_total'    => false,
		] ) )->get_results();

		return \array_values( \array_filter(
			\is_array( $rows ) ? $rows : [],
			static fn( $user ) :bool => $user instanceof \WP_User
		) );
	}

	/**
	 * @param \WP_User[] $users
	 * @return \WP_User[]
	 */
	private function deduplicateUsersById( array $users ) :array {
		$deduplicated = [];
		foreach ( $users as $user ) {
			$userId = (int)$user->ID;
			if ( $userId > 0 && !isset( $deduplicated[ $userId ] ) ) {
				$deduplicated[ $userId ] = $user;
			}
		}
		return \array_values( $deduplicated );
	}
}
