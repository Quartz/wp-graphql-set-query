<?php
/**
 * Plugin Name: WP GraphQL Set Query
 * Plugin URI: https://github.com/Quartz/wp-graphql-set-query
 * Description: Query posts against "sets" defined by custom logic
 * Author: Quartz, Chris Zarate
 * Version: 0.0.1
 * Text Domain: wp-graphql-set-query
 * Requires at least: 4.7.0
 *
 * @package WPGraphQLSetQuery
 * @category Core
 * @author Quartz, Chris Zarate
 * @version 0.0.1
 */

namespace WPGraphQL;

use WPGraphQL\Type\WPEnumType;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Types;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add a `set` query to posts connection queries.
 */
class SetQuery {
	/**
	 * Constructor.
	 */
	public function __construct() {
		/**
		 * Filter the query_args for the PostObjectQueryArgsType.
		 */
		add_filter( 'graphql_queryArgs_fields', array( $this, 'add_input_fields' ), 10, 1 );

		/**
		 * Filter the args for the PostObjectsConnectionResolver to map the `set`
		 * input to WP_Query args.
		 */
		add_filter( 'graphql_map_input_fields_to_wp_query', array( $this, 'map_input_fields' ), 10, 2 );
	}

	/**
	 * Add the set query field.
	 *
	 * @param array $fields Associative array of query fields.
	 *
	 * @return array
	 * @since 0.0.1
	 */
	public function add_input_fields( $fields ) {
		$fields['setQuery'] = Types::list_of(
			new WPInputObjectType(
				array(
					'name' => 'setArray',
					'description' => __( 'Query objects based on user-defined sets', 'wp-graphql-set-query' ),
					'fields' => array(
						'set' => new WPEnumType(
							array(
								'name' => 'set',
								'description' => __( 'User-defined sets', 'wp-graphql-set-query' ),
								'values' => array(), // Values added via graphql_set_values filter.
							)
						),
						'args' => [
							'type' => Types::list_of( Types::string() ),
							'description' => __( 'A list of arguments passed to the user-defined set function', 'wp-graphql-set-query' ),
						],
					),
				)
			)
		);

		return $fields;
	}

	/**
	 * Map input fields to WP_Query args.
	 *
	 * @param array $query_args WP_Query args.
	 * @param array $input_args Input args from where clause.
	 *
	 * @return array
	 */
	public function map_input_fields( $query_args, $input_args ) {
		if ( empty( $input_args['setQuery'] ) ) {
			return $query_args;
		}

		$post_in = array();
		$queries = $this->get_valid_set_queries( $input_args['setQuery'] );

		foreach ( $queries as $query ) {
			$post_in = array_merge( $post_in, $this->get_ids_for_set( $query ) );
		}

		$query_args['post__in'] = array_unique( $post_in );
		$query_args['orderby'] = 'post__in';

		// Remove the default argument added to query args.
		unset( $query_args['setQuery'] );

		return $query_args;
	}

	/**
	 * Get the array of post IDs by calling the user's function. Use a default
	 * return value of array( 0 ) to exclude all posts.
	 *
	 * @param array $query Query input arguments.
	 * @return array
	 */
	private function get_ids_for_set( $query ) {
		$default = array( 0 );

		if ( ! isset( $query['set'] ) || ! is_callable( $query['set'] ) ) {
			return $default;
		}

		$post_ids = call_user_func_array( $query['set'], $query['args'] );

		if ( is_array( $post_ids ) && count( $post_ids ) ) {
			return array_map( 'intval', $post_ids );
		}

		return $default;
	}

	/**
	 * Validate sets and supply default values.
	 *
	 * @param array $queries Array of query input arguments.
	 * @return array
	 */
	private function get_valid_set_queries( $queries ) {
		foreach( $queries as $key => &$query ) {
			if ( ! isset( $query['set'] ) || ! is_callable( $query['set'] ) ) {
				unset( $queries[ $key ] );
				continue;
			}

			if ( ! isset( $query['args'] ) || ! is_array( $query['args'] ) ) {
				$query['args'] = array();
			}

			// Relation is not yet implemented, but it probably will be soon.
			if ( ! isset( $query['relation'] ) ) {
				$query['relation'] = 'OR';
			}
		}

		return $queries;
	}
}

/**
 * Instantiate the SetQuery class on graphql_init.
 *
 * @return SetQuery
 */
function graphql_init_set_query() {
	return new \WPGraphQL\SetQuery();
}

add_action( 'graphql_generate_schema', '\WPGraphql\graphql_init_set_query' );
