<?php

class Geodir_REST_Listings_Controller extends WP_REST_Posts_Controller {
    protected $post_type;

    public function __construct( $post_type ) {
        $this->post_type    = $post_type;
        $this->namespace    = GEODIR_REST_SLUG . '/v' . GEODIR_REST_API_VERSION;
        $obj                = get_post_type_object( $post_type );
        $this->rest_base    = ! empty( $obj->rest_base ) ? $obj->rest_base : $obj->name;
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'            => $this->get_collection_params(),
            ),
            array(
                'methods'         => WP_REST_Server::CREATABLE,
                'callback'        => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
                'args'            => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
                'args'            => array(
                    'context'          => $this->get_context_param( array( 'default' => 'view' ) ),
                ),
            ),
            array(
                'methods'         => WP_REST_Server::EDITABLE,
                'callback'        => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'            => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
            ),
            array(
                'methods'  => WP_REST_Server::DELETABLE,
                'callback' => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
                'args'     => array(
                    'force'    => array(
                        'default'      => false,
                        'description'  => __( 'Whether to bypass trash and force deletion.' ),
                    ),
                ),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );
    }
    
    /**
     * Get the query params for collections of attachments.
     *
     * @return array
     */
    public function get_collection_params() {
        $params = parent::get_collection_params();
                
        $sort_options = geodir_rest_get_listing_sorting( $this->post_type );
 
        $orderby            = array_keys( $sort_options['sorting'] );
        $orderby_rendered  = $sort_options['sorting'];
        $default_orderby    = $sort_options['default_sortby'];
        $default_order      = $sort_options['default_sort'];

        $params['context']['default'] = 'view';

        $params['after'] = array(
            'description'        => __( 'Limit response to resources published after a given ISO8601 compliant date.' ),
            'type'               => 'string',
            'format'             => 'date-time',
            'validate_callback'  => 'rest_validate_request_arg',
        );
        if ( post_type_supports( $this->post_type, 'author' ) ) {
            $params['author'] = array(
                'description'         => __( 'Limit result set to posts assigned to specific authors.' ),
                'type'                => 'array',
                'default'             => array(),
                'sanitize_callback'   => 'wp_parse_id_list',
                'validate_callback'   => 'rest_validate_request_arg',
            );
            $params['author_exclude'] = array(
                'description'         => __( 'Ensure result set excludes posts assigned to specific authors.' ),
                'type'                => 'array',
                'default'             => array(),
                'sanitize_callback'   => 'wp_parse_id_list',
                'validate_callback'   => 'rest_validate_request_arg',
            );
        }
        $params['before'] = array(
            'description'        => __( 'Limit response to resources published before a given ISO8601 compliant date.' ),
            'type'               => 'string',
            'format'             => 'date-time',
            'validate_callback'  => 'rest_validate_request_arg',
        );
        $params['exclude'] = array(
            'description'        => __( 'Ensure result set excludes specific ids.' ),
            'type'               => 'array',
            'default'            => array(),
            'sanitize_callback'  => 'wp_parse_id_list',
        );
        $params['include'] = array(
            'description'        => __( 'Limit result set to specific ids.' ),
            'type'               => 'array',
            'default'            => array(),
            'sanitize_callback'  => 'wp_parse_id_list',
        );
        if ( 'page' === $this->post_type || post_type_supports( $this->post_type, 'page-attributes' ) ) {
            $params['menu_order'] = array(
                'description'        => __( 'Limit result set to resources with a specific menu_order value.' ),
                'type'               => 'integer',
                'sanitize_callback'  => 'absint',
                'validate_callback'  => 'rest_validate_request_arg',
            );
        }
        $params['offset'] = array(
            'description'        => __( 'Offset the result set by a specific number of items.' ),
            'type'               => 'integer',
            'sanitize_callback'  => 'absint',
            'validate_callback'  => 'rest_validate_request_arg',
        );
        $params['order'] = array(
            'description'        => __( 'Order sort attribute ascending or descending.' ),
            'type'               => 'string',
            'default'            => $default_order,
            'enum'               => array( 'asc', 'desc' ),
            'validate_callback'  => 'rest_validate_request_arg',
        );
        $params['orderby'] = array(
            'description'        => __( 'Sort collection by object attribute.' ),
            'type'               => 'string',
            'default'            => $default_orderby,
            'enum'               => $orderby,
            'validate_callback'  => 'rest_validate_request_arg'
        );
        $params['orderby_rendered']    = array(
            'description'           => __( 'All sorting options for listings.' ),
            'type'                  => 'array',
            'enum'                  => $orderby_rendered
        );

        $post_type_obj = get_post_type_object( $this->post_type );
        if ( $post_type_obj->hierarchical || 'attachment' === $this->post_type ) {
            $params['parent'] = array(
                'description'       => __( 'Limit result set to those of particular parent ids.' ),
                'type'              => 'array',
                'sanitize_callback' => 'wp_parse_id_list',
                'default'           => array(),
            );
            $params['parent_exclude'] = array(
                'description'       => __( 'Limit result set to all items except those of a particular parent id.' ),
                'type'              => 'array',
                'sanitize_callback' => 'wp_parse_id_list',
                'default'           => array(),
            );
        }

        $params['slug'] = array(
            'description'       => __( 'Limit result set to posts with a specific slug.' ),
            'type'              => 'string',
            'validate_callback' => 'rest_validate_request_arg',
        );
        $params['status'] = array(
            'default'           => 'publish',
            'description'       => __( 'Limit result set to posts assigned a specific status.' ),
            'sanitize_callback' => 'sanitize_key',
            'type'              => 'string',
            'validate_callback' => array( $this, 'validate_user_can_query_private_statuses' ),
        );
        $params['filter'] = array(
            'description'       => __( 'Use WP Query arguments to modify the response; private query vars require appropriate authorization.' ),
        );

        $taxonomies = wp_list_filter( get_object_taxonomies( $this->post_type, 'objects' ), array( 'show_in_rest' => true ) );
        foreach ( $taxonomies as $taxonomy ) {
            $base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;

            $params[ $base ] = array(
                'description'       => sprintf( __( 'Limit result set to all items that have the specified term assigned in the %s taxonomy.' ), $base ),
                'type'              => 'array',
                'sanitize_callback' => 'wp_parse_id_list',
                'default'           => array(),
            );
        }
        return $params;
    }
}
