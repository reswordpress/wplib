<?php /** Author: Vitali Lupu <vitaliix@gmail.com> */

namespace wp;
final class UtilsTheme
{
    //Singleton Instance
    protected static $instance = null;

    public static function i()
    {
        if (!self::$instance) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    protected function __construct()
    {
        /**
         * Set the content width based on the theme's design and stylesheet.
         * MetaBox: can break UI in the admin without this parameters
         */
        global $content_width;
        if (!isset($content_width)) {
            $content_width = 1200;
        }
        //TODO Make Options for all action to be able to decide what disable
        // Add: Remove handler fo attached Images from Deleted Post
        add_action(WPActions::BEFORE_DELETE_POST, [WPUtils::class, 'deletePostAttachments']);
        // Add: OpenGraph Meta to Header
        add_action(WPActions::WP_HEAD, [$this, 'addToHeaderOpenGraphMeta'], 5);
        // Add: Radom OrderBy Support
        add_action(WPActions::PRE_USER_QUERY, [$this, 'handleUserQuery']);
        // Add: Mail From Name the Site Name
        add_filter('wp_mail_from_name', [WPOptions::class, "getSiteName"]);
        //-------------------------------[USER RELATED]
        add_action(WPActions::INIT, [$this, 'preventUserAdminBarUsage'], 9);
        add_action(WPActions::INIT_ADMIN, [$this, 'preventUserAdminAccess']);
        if (!WPUsers::isSiteEditor()) {

            add_action('pre_get_posts', [$this, 'showOnlyRelatedPosts']);
        }
        //https://bhoover.com/remove-unnecessary-code-from-your-wordpress-blog-header/
        //-------------------------------[FRONTEND]
        // Remove: oEmbed Discovery Links
        remove_action(WPActions::WP_HEAD, WPActionsHandlers::ADD_LINKS_OF_oEMBED, 10);
        // Remove: Blog Version
        remove_action(WPActions::WP_HEAD, WPActionsHandlers::ADD_GENERATOR);
        // Remove: REST API Link Tag
        remove_action(WPActions::WP_HEAD, WPActionsHandlers::ADD_LINK_OF_REST, 10);
        // Remove: REST API Link in HTTP headers
        remove_action(WPActions::TEMPLATE_REDIRECT, WPActionsHandlers::ADD_LINK_OF_REST_HEADER,
            11);
        //Remove: RSD Link - Weblog Client Link
        remove_action(WPActions::WP_HEAD, 'rsd_link');
        //Remove: Windows Live Writer Manifest Link
        remove_action(WPActions::WP_HEAD, 'wlwmanifest_link');
        //Remove: WordPress Generator (with version information)
        remove_action(WPActions::WP_HEAD, 'wp_generator');
        // Remove: Canonical Link
        remove_action(WPActions::WP_HEAD, 'rel_canonical');
        // Remove: Short Link
        remove_action(WPActions::WP_HEAD, 'wp_shortlink_wp_head');
        add_filter('the_generator', '__return_false');
        //Disable: XMLRPC
        add_filter('xmlrpc_enabled', '__return_false');
        // Remove: WP Version From Styles
        add_filter('style_loader_src', [$this, 'removeWpVersion'], 9999);
        // Remove: WP Version From Scripts
        add_filter('script_loader_src', [$this, 'removeWpVersion'], 9999);
        // DISABLE: Emoticons
        $this->disableEmotionIcons();
        //add_filter('rest_url', '__return_false');
        //Disable: Image src set Calculation
        add_filter('wp_calculate_image_srcset', '__return_false');
        //add_filter('show_admin_bar','__return_false');
        //-------------------------------[BACKEND]
        //Disable: Wordpress CoreUpdates Notifications
        //add_action( 'after_setup_theme', 'disableCoreUpdatesNotifications' );
        //Disable: Notice “CONNECT YOUR STORE TO WOOCOMMERCE.COM”
        add_filter('woocommerce_helper_suppress_admin_notices', '__return_true');
        // Remove Admin Footer Message
        add_filter('admin_footer_text', '__return_false');
        // Remove Admin Footer Version
        add_filter('update_footer', '__return_false', 9999);

        add_filter(WPActions::ADMIN_LOGIN_TITLE, [UtilsTheme::class, 'getLoginLogoTitle']);
        add_filter(WPActions::ADMIN_LOGIN_URL, [UtilsTheme::class, 'getLoginLogoUrl']);
        add_action(WPActions::LOGOUT, [UtilsTheme::class, 'handleLogout']);
        add_filter(WPActions::USER_EDIT_PROFILE_URL, [$this, 'handleEditProfileUrl'], 10, 2);
        //add_action(WPActions::ENQUEUE_SCRIPTS_ADMIN_LOGIN, [$this, 'handleScriptsAdminLoginLogo']);
    }

    /**
     * Adding the Open Graph Meta Info
     * Docs: http://ogp.me
     */
    function addToHeaderOpenGraphMeta()
    {
        //TODO Review this to publish all information about Post to facebook OpenGraph
        if (is_single()) {
            global $post;
            if (has_excerpt($post->ID)) {
                $description = strip_tags(get_the_excerpt());
            } else {
                $description = str_replace("\r\n", ' ', substr(strip_tags(strip_shortcodes($post->post_content)), 0, 160));
            }
            if (empty($description)) {
                $description = get_bloginfo('description');
            }
            $pageThumb = "";
            if (has_post_thumbnail($post->ID)) {
                $thumbnailSrc = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full');
                $pageThumb = esc_attr($thumbnailSrc[0]);
            }
            $metas = [
                'og:site_name' => get_bloginfo('name'),
                'og:url' => get_permalink(),
                'og:type' => 'article',
                'og:image' => $pageThumb,
                'og:title' => get_the_title(),
                'og:description' => $description
            ];
            foreach ($metas as $property => $content) {
                echo "<meta property='$property' content='$content'>\n";
            }
        }
    }

    function handleUserQuery($query)
    {
        if (WPOrderBy::RANDOM == $query->query_vars[QueryUsers::ORDER_BY]) {
            $query->query_orderby = str_replace(WPUsers::LOGIN, "RAND()", $query->query_orderby);
        }
    }

    /**
     * Hide the admin bar on front end for users with user level equal to or below restricted level
     */
    function preventUserAdminBarUsage()
    {
        if (is_user_logged_in()) {
            if (WPUtils::isUserRestricted()) {
                add_filter('show_admin_bar', '__return_false');
            }
            // get the the role object
            $editor = get_role('editor');
            // add $cap capability to this role object
            $editor->add_cap('edit_theme_options');
        }
    }

    /**
     * Restrict user access to admin if his level is equal or below restricted level
     * Or request is an AJAX request or delete request from my properties page
     */
    function preventUserAdminAccess()
    {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            // let it go
        } else if (isset($_GET['action']) && ($_GET['action'] == 'delete')) {
            // let it go as it is from my properties delete button
        } else {
            if (WPUtils::isUserRestricted()) {
                wp_redirect(esc_url_raw(home_url('/')));
                exit;
            }
        }
    }

    /**
     * Show Posts related to current Author only
     */
    function showOnlyRelatedPosts($wp_query)
    {
        global $current_user;
        if (is_admin() && !current_user_can('edit_others_posts')) {
            $wp_query->set('author', $current_user->ID);
        }
    }

    /**
     * WordPress Login page Logo Title
     * @return string - site name
     */
    static function getLoginLogoTitle()
    {
        return get_bloginfo('name');
    }

    /**
     * WordPress Login page Logo URL
     * @return string - site url
     */
    static function getLoginLogoUrl()
    {
        return home_url();
    }

    /**
     * WordPress Login Logout Redirect
     * @return string - site url
     */
    static function handleLogout()
    {
        wp_redirect(home_url());
        exit();
    }

    /** Load Styles & Scripts for: Login Backend*/
    function handleScriptsAdminLoginLogo()
    {
        $logoId = get_theme_mod(WPOptions::SITE_LOGO);
        $image = wp_get_attachment_image_url($logoId, 'full');
        $textIndent = "-9999px";
        if (!$image) {
            $textIndent = "0";
        }
        $cssContent = "body.login div#login h1 a { 
			background-image: url($image);
			background-position: center center;
			background-size:auto;
			height: 96px;
		    width: auto;
		    text-indent: $textIndent;
		    font-size: 36px;
		    font-weight: bold;
		}
		.login form{background:none; margin-top:10px;} #backtoblog, #nav{display:none;}";
        wp_add_inline_style('login', $cssContent);
    }

    /**
     * Filters the URL for a user’s profile editor.
     * @link  https://developer.wordpress.org/reference/hooks/edit_profile_url/
     * @param string $url The complete URL including scheme and path.
     * @param int $userId The user ID.
     * @return string
     */
    function handleEditProfileUrl(string $url, int $userId)
    {
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen->id == "profile") {
                $url = get_author_posts_url($userId);
            }
        }

        return $url;
    }

    function disableCoreUpdatesNotifications()
    {
        if (current_user_can('update_core')) {
            add_action('init', function () {
                remove_action('init', 'wp_version_check');
            }, 2);
            add_filter('pre_option_update_core', '__return_null');
            add_filter('pre_site_transient_update_core', '__return_null');
        }
    }

    function disableEmotionIcons()
    {
        if (is_admin() == false) {
            remove_action('wp_head', 'print_emoji_detection_script', 7);
            remove_action('admin_print_scripts', 'print_emoji_detection_script');
            remove_action('wp_print_styles', 'print_emoji_styles');
            remove_action('admin_print_styles', 'print_emoji_styles');
            remove_filter('the_content_feed', 'wp_staticize_emoji');
            remove_filter('comment_text_rss', 'wp_staticize_emoji');
            remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        }
    }

    function removeWpVersion($src)
    {
        if (strpos($src, 'ver=')) {
            $src = remove_query_arg('ver', $src);
        }
        return $src;
    }
}