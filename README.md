# WPGraphQL Set Query

This is an *experimental* plugin that could be useful as an exposition on how
to extend [WPGraphQL][wp-graphql] to support custom logic. It probably doesn't
make sense to use this directly.

This plugin extends [WPGraphQL][wp-graphql] to allow queries for ordered "sets"
of posts that are defined by custom logic. For example, you may have a list of
post IDs in an option, or you many want to identify posts using data from a
third-party integration.

Note that this plugin, while flexible, should not be used for basic queries that
are already built in to WPGraphQL or provided by another querying plugin (e.g.,
[WPGraphQL Tax Query][wp-graphql-tax-query]). Only use this plugin for truly
custom logic—but first consider reimplementing that logic using custom
taxonomies or another queryable data type such as menus.


## Usage

Install and activate both the [WPGraphQL plugin][wp-graphql] and this plugin.

Define a function that implements your set (returns an array of post IDs). Then
define the set via the `graphql_set_values` filter:

```
/**
 * Return an array of my favorite posts in order, by ID.
 *
 * @return array Array of WP post IDs.
 */
function get_my_favorites() {
  return get_option( 'my_favorite_posts' ); // array( 123, 222, 315 )
}

/**
 * Define the set. Sets are an enumeration type; here we are providing a value.
 * The array key should be a valid name; the value should be a callable
 * reference to your function. The description is optional, but is surfaced in
 * auto-generated documentation.
 *
 * @param array $sets Array of enumeration type values.
 * @return array
 */
function define_sets( $sets ) {
  $sets['MY_FAVORITES'] = array(
    'description' => 'My favorite posts',
    'value' => 'get_my_favorites',
  );

  return $sets;
}
add_filter( 'graphql_set_values', 'define_sets', 10, 1 );
```

You can now query for this set on any post object connection query (posts,
pages, and custom post types). Use the `setQuery` argument:

```
query {
  posts(
    where: {
      setQuery: {
        set: MY_FAVORITES
      ]
    }
  )
  {
    edges {
      node {
        id
        postId
        title
        content
      }
    }
  }
}
```

The same query using WP_Query would look like:

```
new WP_Query(
  array(
    'posts__in' => array( 123, 222, 315 ),
    'orderby' => 'posts__in',
  )
);
```


## Additional arguments

You can pass additional arguments to your filters from the query. These
arguments must be strings. Make sure you request those additional arguments
via `add_filter` and define default values in case they are not supplied by
the query.

```
query {
  posts(
    where: {
      setQuery: {
        name: MY_FAVORITES
        args: [
          "cG9zdDoxMjM="
        ]
      ]
    }
  )
  ...
}
```


[wp-graphql]: https://github.com/wp-graphql/wp-graphql
[wp-graphql-tax-query]: https://github.com/wp-graphql/wp-graphql-tax-query
