<?php
/**
 * Author: Vitali Lupu <vitaliix@gmail.com>
 * Date: 3/5/18
 * Time: 8:29 PM
 */

namespace wp;
abstract class PostBase
{
    const TYPE = "post";
    const COLUMN_THUMB = "columnThumb";
    static $counter = 0;
    protected static $instances = [];
    protected $name = "Post";
    protected $namePlural = "Posts";
    protected $enableQuickEdit = false;
    protected $hasThumbnail = true;
    private $type = PostBase::TYPE;

    private function __construct()
    {
        $this->type = static::TYPE;
        add_action(WPActions::INIT, [$this, 'registerPost']);
        add_action(WPActions::RESTRICT_MANAGE_POSTS, [$this, 'createTaxonomyFilter'], 10);
        //https://codex.wordpress.org/Plugin_API/Filter_Reference/wp_insert_post_data
        add_filter('wp_insert_post_data', [$this, 'handlePostSaveBefore'], 99, 2);
        add_filter("manage_{$this->type}_posts_custom_column", [$this, 'handlePostColumnsContent'], 10, 2);
        add_filter("manage_edit-{$this->type}_columns", [$this, "handlePostColumns"]);
        add_filter("manage_edit-{$this->type}_sortable_columns", [$this, "handlePostColumnsSortable"]);
        add_filter(MetaBoxFilter::REGISTER, [$this, 'registerPostMetaBoxes']);
        add_filter('post_row_actions', [$this, 'handlePostRowActions'], 10, 2);
        add_filter('page_row_actions', [$this, 'handlePostRowActions'], 10, 2);
        add_filter("bulk_actions-edit-{$this->type}", [$this, 'handlePostBulkActions'], 10, 2);
        add_action('transition_post_status', [$this, 'handlePostStatusChange'], 10, 3);
    }

    final public static function i()
    {
        if (!isset(static::$instances[static::TYPE])) {
            static::$instances[static::TYPE] = new static();
        }
        return static::$instances[static::TYPE];
    }

    final public static function getPostViews($postID)
    {
        //TODO Add a statistics mechanism to Project Without external dependency
        $count = 0;
        $countKey = MetaPost::VIEW_COUNT;
        $metaCount = get_post_meta($postID, $countKey, true);
//		$hasStatisticsPlugin = function_exists( 'wp_statistics_pages' );
        $hasStatisticsPlugin = function_exists('pvc_get_post_views');//do_shortcode('[post-views]')
        if ($hasStatisticsPlugin) {
//			$count = wp_statistics_pages( 'total', wp_statistics_get_uri(), get_the_ID() );
            $count = pvc_get_post_views(get_the_ID());
        }
        if ($metaCount == '') {
            //delete_post_meta($postID, $countKey);
            if ($hasStatisticsPlugin == false) {
                $count++;
            }
            add_post_meta($postID, $countKey, $count);
        }
        update_post_meta($postID, $countKey, $count);

        return $count;
    }

    static function getTaxonomyLabels($name, $namePlural, $namePost)
    {
        return [
            WPTaxonomyLabels::NAME_IN_MENU => __("$namePlural", 'wptheme'),
            WPTaxonomyLabels::NAME_PLURAL => __("$namePost $namePlural", 'wptheme'),
            WPTaxonomyLabels::NAME_SINGULAR => __("$namePost $name", 'wptheme'),
            WPTaxonomyLabels::CHOSE_FROM_MOST_USED => __("Choose from the most used $namePost $namePlural", 'wptheme'),
            WPTaxonomyLabels::ITEM_ADD_NEW => __("Add New $name", 'wptheme'),
            WPTaxonomyLabels::ITEM_NEW_NAME => __("New $namePost $name Name", 'wptheme'),
            WPTaxonomyLabels::ITEM_EDIT => __("Edit $namePost $name", 'wptheme'),
            WPTaxonomyLabels::ITEM_UPDATE => __("Update $namePost $name", 'wptheme'),
            WPTaxonomyLabels::ITEM_PARENT => __("Parent $namePost $name", 'wptheme'),
            WPTaxonomyLabels::ITEM_PARENT_COLON => __("Parent $namePost $name:", 'wptheme'),
            WPTaxonomyLabels::ITEMS_ALL => __("All $namePlural", 'wptheme'),
            WPTaxonomyLabels::ITEMS_SEARCH => __("Search $namePost $namePlural", 'wptheme'),
            WPTaxonomyLabels::ITEMS_POPULAR => __("Popular $namePost $namePlural", 'wptheme'),
            WPTaxonomyLabels::ITEMS_SEPARATE_WITH_COMMAS => __("Separate $namePost $namePlural with commas", 'wptheme'),
            WPTaxonomyLabels::ITEMS_ADD_OR_REMOVE => __("Add or remove $namePost $namePlural", 'wptheme'),
        ];
    }

    static function getPostLabels($name, $namePlural)
    {
        return [
            WPostLabels::NAME_PLURAL => __($namePlural, 'wptheme'),
            WPostLabels::NAME_SINGULAR => __($name, 'wptheme'),
            WPostLabels::ADD_NEW => __("Add New", 'wptheme'),
            WPostLabels::ITEM_ADD_NEW => __("Add New $name", 'wptheme'),
            WPostLabels::ITEM_EDIT => __("Edit $name", 'wptheme'),
            WPostLabels::ITEM_NEW => __("New $name", 'wptheme'),
            WPostLabels::ITEM_VIEW => __("View $name", 'wptheme'),
            WPostLabels::ITEMS_SEARCH => __("Search $name", 'wptheme'),
            WPostLabels::NOT_FOUND => __("No $name found", 'wptheme'),
            WPostLabels::NOT_FOUND_IN_TRASH => __("No $name found in Trash", 'wptheme'),
            WPostLabels::ITEM_PARENT_COLON => "",
        ];
    }

    // function to display number of posts.

    function handlePostStatusChange($new_status, $old_status, $post)
    {
        if ($new_status == WPostStatus::PUBLISH && $old_status == WPostStatus::AUTO_DRAFT) {
            // the post is inserted
            $this->handlePostInsert($post);
        } else if ($new_status == WPostStatus::PUBLISH && $old_status != WPostStatus::PUBLISH) {
            // the post is published
            $this->handlePostPublish($post);
        } else {
            // the post is updated
            $this->handlePostUpdate($post);
        }
    }

    function handlePostInsert($post)
    {

    }

    function handlePostPublish($post)
    {

    }

    function handlePostUpdate($post)
    {

    }

    public function registerPost()
    {
    }

    public function handlePostSaveBefore($data, $postarr)
    {
        return $data;
    }

    public function handlePostRowActions($actions)
    {
        if ($this->enableQuickEdit == false) {
            unset($actions['inline hide-if-no-js']);
        }

        return $actions;
    }

    function handlePostBulkActions($actions)
    {
        //unset( $actions['inline'] );
        return $actions;
    }

    public function handlePostColumns($columns)
    {
        if ($this->hasThumbnail) {
            self::arrayInsert($columns, 2, [self::COLUMN_THUMB => '<i class="fa fa-picture-o"></i>']);
        }

        return $columns;
    }

    static function arrayInsert(&$array, $position, $insert_array)
    {
        $first_array = array_splice($array, 0, $position);
        $array = array_merge($first_array, $insert_array, $array);
    }

    public function handlePostColumnsSortable($columns)
    {
        return $columns;
    }

    public function handlePostColumnsContent($column, $postId)
    {
        switch ($column) {
            case self::COLUMN_THUMB:
                {
                    $thumb = __('No Thumbnail', 'wptheme');
                    if (has_post_thumbnail($postId)) {
                        $thumb = sprintf('<a href="%1$s" target="_blank">%2$s</a>',
                            get_the_permalink(),
                            get_the_post_thumbnail(get_post($postId), [108, 73]));
                    }
                    echo $thumb;
                    break;
                }
        }
    }

    public function registerPostMetaBoxes($meta_boxes)
    {
        //TODO Add verb translation to be able to identify video and images type
        //	MetaBoxField::NAME        => sprintf(__('Video of %s', 'wptheme'), __( $this->name, 'wptheme' )),
        //	MetaBoxFieldImage::NAME   => sprintf(__('Images of %s', 'wptheme'), __( $this->name, 'wptheme' )),
        $meta_boxes[] = [
            MetaBox::ID => MetaPost::ID_BOX_GALLERY,
            MetaBox::TITLE => PostBase::getIcon("fa-picture-o") . __('Gallery Images', 'wptheme'),
            MetaBox::POST_TYPES => [$this->type],
            MetaBox::CONTEXT => MetaBoxContext::NORMAL,
            MetaBox::PRIORITY => MetaBoxPriority::HIGH,
            MetaBox::FIELDS => [
                [
                    MetaBoxFieldImage::TYPE => MetaBoxFieldType::IMAGE_UPLOAD,
                    MetaBoxFieldImage::COLUMNS => 12,
                    MetaBoxFieldImage::MAX_UPLOADS => 30,
                    MetaBoxFieldImage::MAX_STATUS => true,
//										MetaBoxFieldImage::FORCE_DELETE => true, // Cause Removing of image when rearrange
                    MetaBoxFieldImage::ID => MetaPost::GALLERY,
                    //					MetaBoxFieldImage::NAME         => __( 'Images' ),
                    MetaBoxFieldImage::DESCRIPTION => sprintf(__('Images should have minimum width of %spx and minimum height of %spx.', 'wptheme'), 1280, 720),
                ],
            ],
        ];
        if (WPUsers::isSiteEditor()) {
            $meta_boxes[] = [
                MetaBox::ID => MetaPost::ID_BOX_VIDEO,
                MetaBox::TITLE => PostBase::getIcon("fa-film") . __('Video Embed Code', 'wptheme'),
                MetaBox::POST_TYPES => [$this->type],
                MetaBox::CONTEXT => MetaBoxContext::NORMAL,
                MetaBox::PRIORITY => MetaBoxPriority::HIGH,
                MetaBox::FIELDS => [
                    [
                        MetaBoxField::TYPE => MetaBoxFieldType::EMBED,
                        MetaBoxField::ID => MetaPost::VIDEO,
                        MetaBoxField::COLUMNS => 12,
                        MetaBoxField::NAME => __('Video'),

                        MetaBoxField::DESCRIPTION => __('If you are not using self hosted videos then please provide the video embed code and remove the width and height attributes.', 'wptheme'),
                    ],
                ],
            ];
        }

        return $meta_boxes;
    }

    static function getIcon($data)
    {
        return "<i class='fa $data' aria-hidden='true'></i> ";
    }

    /**
     * Comma separated taxonomy terms with admin side links
     *
     * @param $postId
     * @param $taxonomyName
     * @param $postType
     *
     * @return string
     */
    function getTaxonomyLinksOfTerms($postId, $taxonomyName, $postType)
    {
        $terms = get_the_terms($postId, $taxonomyName);
        $result = '';
        if (!empty ($terms)) {
            $links = [];
            /* Loop through each term, linking to the 'edit posts' page for the specific term. */
            foreach ($terms as $term) {
                $links[] = sprintf("<a href='%s'>%s</a>",
                    esc_url(add_query_arg([
                        QueryPost::TYPE => $postType,
                        $taxonomyName => $term->slug,
                    ], 'edit.php')),
                    esc_html(sanitize_term_field('name', $term->name, $term->term_id, $taxonomyName, 'display'))
                );
            }
            $result = join(', ', $links);
        }

        return $result;
    }

    /**
     * Filter the request to just give posts for the given taxonomy, if applicable.
     * @link https://developer.wordpress.org/reference/hooks/restrict_manage_posts/
     * @link https://wp-kama.ru/hook/restrict_manage_posts
     *
     * @param string $postType The post type slug.
     *
     * @return array
     */
    function createTaxonomyFilter($postType)
    {
        $taxonomies = [];
        if (isset(self::$instances[$postType])) {
            $taxonomies = get_object_taxonomies($postType, 'objects');
        }

        return $taxonomies;
    }
}