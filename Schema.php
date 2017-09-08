<?php
namespace Mohiohio\GraphQLWP;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQLRelay\Relay;

use Mohiohio\GraphQLWP\Type\Definition\WPQuery;
use Mohiohio\GraphQLWP\Type\Definition\WPPost;
use Mohiohio\GraphQLWP\Type\Definition\WPTerm;

use Mohiohio\GraphQLWP\Type\Definition\Post;
use Mohiohio\GraphQLWP\Type\Definition\Page;
use Mohiohio\GraphQLWP\Type\Definition\Attachment;
use Mohiohio\GraphQLWP\Type\Definition\Product;
use Mohiohio\GraphQLWP\Type\Definition\Order;
use Mohiohio\GraphQLWP\Type\Definition\Category;
use Mohiohio\GraphQLWP\Type\Definition\Tag;
use Mohiohio\GraphQLWP\Type\Definition\PostFormat;
use Mohiohio\GraphQLWP\Type\Definition\PostInput;

class Schema
{
    static protected $query = null;
    static protected $mutation = null;
    static protected $nodeDefinition = null;

    static function build() {
        // Add WooCommerce Schema if required
        add_filter('graphql-wp/schema-types',function($types) {
            if(self::withWooCommerce()) {
                return $types + [
                     Order::getInstance(),
                     Product::getInstance(),
                ] + $types;
            }
            return $types;
        });

        return new \GraphQL\Schema([
            'query' => static::getQuery(),
            'mutation' => static::getMutation(),
            'types' => apply_filters('graphql-wp/get_post_types', apply_filters('graphql-wp/schema-types',[
                WPPost::getInstance(),
                WPTerm::getInstance(),
                Post::getInstance(),
                Page::getInstance(),
                Attachment::getInstance(),
                Category::getInstance(),
                Tag::getInstance(),
                PostFormat::getInstance()
            ]))
        ]);
    }

    static function withWooCommerce() {
        return (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins',get_option( 'active_plugins' ))));
    }

    static function getNodeDefinition() {

        return static::$nodeDefinition ?: static::$nodeDefinition = Relay::nodeDefinitions(
        function($globalID) {

            $idComponents = Relay::fromGlobalId($globalID);

            switch ($idComponents['type']){
                case WPPost::TYPE;
                return get_post($idComponents['id']);
                case WPTerm::TYPE;
                return get_term($idComponents['id']);
                default;
                return null;
            }
        },
        function($obj) {

            if ($obj instanceOf \WP_Post) {
                return WPPost::resolveType($obj);
            }
            if ($obj instanceOf \WP_Term) {
                return WPTerm::resolveType($obj);
            }
        });
    }

    static function getQuery() {
        return static::$query ?: static::$query = new ObjectType(static::getQuerySchema());
    }

    static function getQuerySchema() {

        $schema = apply_filters('graphql-wp/get_query_schema', [
            'name' => 'Query',
            'fields' => function() {
                return [
                    'wp_query' => [
                        'type' => WPQuery::getInstance(),
                        'resolve' => function($root, $args) {
                            global $wp_query;
                            return $wp_query;
                        }
                    ],
                    'wp_post' => [
                        'type' => WPPost::getInstance(),
                        'args' => [
                            'ID' => [
                                'name' => 'ID',
                                'description' => 'id of the post',
                                'type' => Type::int()
                            ],
                            'slug' => [
                                'name' => 'slug',
                                'description' => 'name of the post',
                                'type' => Type::string()
                            ],
                            'post_type' => [
                                'name' => 'post_type',
                                'description' => 'type of the post',
                                'type' => Type::string()
                            ]
                        ],
                        'resolve' =>  function ($root, $args) {
                            if(isset($args['ID'])){
                                return get_post($args['ID']);
                            }
                            return get_page_by_path( $args['slug'], \OBJECT, isset($args['post_type']) ? $args['post_type'] : WPPost::DEFAULT_TYPE );
                        }
                    ],
                    'term' => [
                        'type' => WPTerm::getInstance(),
                        'args' => [
                            'id' => [
                                'type' => Type::string(),
                                'desciption' => 'Term id'
                            ]
                        ],
                        'resolve' => function($root, $args) {
                            return get_term($args['id']);
                        }
                    ],
                    'node' => static::getNodeDefinition()['nodeField']
                ];
            }
        ]);

        return $schema;
    }

    static function getMutation() {
      return static::$mutation ?: static::$mutation = new ObjectType(static::getMutationSchema());
    }

    static function getMutationSchema() {
      return apply_filters('graphql-wp/get_mutation_schema', [
        'name' => 'Mutation',
        'fields' => function() {
          return [
            'insert_post' => [
              'args' => [
                'postdata' => PostInput::getInstance()
              ],
              // 'type' => Post::getInstance(),
              'type' => new ObjectType([
                'name' => 'InsertPostOutput',
                'fields' => [
                  'post' => Post::getInstance(),
                ]
              ]),
              'resolve' => function($root, $args) {
                $res = wp_insert_post($args['postdata'], true);
                if(is_wp_error($res)) {
                  throw new \Exception($res->get_error_message());
                }
                return [
                  'post' => get_post($res)
                ];
              }
            ]
          ];
        }
      ]);
    }
}
