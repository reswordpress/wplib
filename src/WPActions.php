<?php
/**
 * Author: Vitali Lupu <vitaliix@gmail.com>
 * Date: 3/5/18
 * Time: 8:24 PM
 */

namespace wp;
final class WPActions
{
    /**
     * @const
     */
    const ADMIN_LOGIN_TITLE = 'login_headertitle';
    /**
     * @const
     */
    const ADMIN_LOGIN_URL = 'login_headerurl';
    /**
     * @const Fires for the URL of user’s profile editor.
     * @url https://developer.wordpress.org/reference/hooks/edit_profile_url/
     */
    const USER_EDIT_PROFILE_URL = 'edit_profile_url';

    const ENQUEUE_SCRIPTS_ADMIN_LOGIN = 'login_enqueue_scripts';
    /**
     * @const Fires before determining which template to load.
     * @url https://codex.wordpress.org/Plugin_API/Action_Reference/template_redirect
     */
    const TEMPLATE_REDIRECT = 'template_redirect';
    /**
     * @const Initialise Theme
     */
    const THEME_SETUP = 'after_setup_theme';
    /**
     * @const Decorates a menu item object with the shared navigation menu item properties.
     * @url https://developer.wordpress.org/reference/functions/wp_setup_nav_menu_item/
     */
    const WP_SETUP_NAV_MENU_ITEM = 'wp_setup_nav_menu_item';
    /**
     * @const Filter the Walker class used when adding nav menu items.
     * @url https://developer.wordpress.org/reference/hooks/wp_edit_nav_menu_walker-2/
     */
    const WP_EDIT_NAV_MENU_WALKER = 'wp_edit_nav_menu_walker';
    /**
     * @const Fires after a navigation menu item has been updated.
     * @url https://developer.wordpress.org/reference/hooks/wp_update_nav_menu_item/
     */
    const WP_UPDATE_NAV_MENU_ITEM = 'wp_update_nav_menu_item';
    /**
     * @const Filters the arguments for a single nav menu item.
     * @url https://developer.wordpress.org/reference/hooks/nav_menu_item_args/
     */
    const NAV_MENU_ITEM_ARGS = 'nav_menu_item_args';
    /**
     * @const Filters the HTML list content for navigation menus.
     * @url https://developer.wordpress.org/reference/hooks/wp_nav_menu_items/
     */
    const NAV_MENU_ITEMS = 'wp_nav_menu_items';
    /**
     * @const Filters the HTML attributes applied to a menu item's anchor element.
     * @url https://developer.wordpress.org/reference/hooks/nav_menu_link_attributes/
     */
    const NAV_MENU_ITEM_LINK_ATTRIBUTES = 'nav_menu_link_attributes';
    /**
     * @const Load assets for: Frontend
     */
    const ENQUEUE_SCRIPTS_THEME = 'wp_enqueue_scripts';
    /**
     * @const Load assets for: Backend Login
     */
    const ENQUEUE_SCRIPTS_LOGIN = 'login_enqueue_scripts';
    /**
     * @const Load assets for: Backend
     */
    const ENQUEUE_SCRIPTS_ADMIN = 'admin_enqueue_scripts';
    /**
     * @const Load assets for: Customizer
     * @url https://codex.wordpress.org/Plugin_API/Action_Reference/customize_controls_enqueue_scripts
     */
    const ENQUEUE_SCRIPTS_CUSTOMIZER = 'customize_controls_enqueue_scripts';
    /**
     * @const Before Delete Post
     */
    const BEFORE_DELETE_POST = 'before_delete_post';
    /**
     * @const Before Query User Table
     */
    const PRE_USER_QUERY = 'pre_user_query';
    /**
     * @const Fires after all default WordPress widgets have been registered.
     */
    const WIDGETS_INIT = 'widgets_init';
    /**
     * Filters the settings for a particular widget instance.
     * @see https://developer.wordpress.org/reference/hooks/widget_display_callback/
     * @const
     */
    const WIDGET_DISPLAY = 'widget_display_callback';
    /**
     * @const
     */
    const WIDGET_UPDATE = 'widget_update_callback';
    /**
     * @const Before Display Widget Form
     */
    const WIDGET_FORM_BEFORE = 'widget_form_callback';
    /**
     * @const After Display Widget Form
     */
    const WIDGET_FORM_AFTER = 'in_widget_form';
    /**
     * @const Filters the list of sidebars and their widgets.
     * @url https://developer.wordpress.org/reference/hooks/sidebars_widgets/
     */
    const WIDGETS_IN_SIDEBARS = 'sidebars_widgets';
    /**
     * @const Fires after WordPress has finished loading but before any headers are sent.
     * Use to register Post and Taxonomy
     */
    const INIT = 'init';

    const INIT_ADMIN = 'admin_init';

    const LOGOUT = 'wp_logout';
    /**
     * @const Initialise Customizer
     * @url https://codex.wordpress.org/Plugin_API/Action_Reference/customize_preview_init
     */
    const CUSTOMIZER_INIT = 'customize_preview_init';
    /**
     * @const
     */
    const CUSTOMIZER_REGISTER = 'customize_register';
    /**
     * @const
     */
    const CUSTOMIZER_AFTER_SAVE = "customize_save_after";
    /**
     * @const Function is triggered when is called by wp_head() placed between <head></head> section
     */
    const WP_HEAD = 'wp_head';
    /**
     * @const Fires in each custom column in the Posts list table.
     * @url https://developer.wordpress.org/reference/hooks/manage_posts_custom_column-8/
     */
    const MANAGE_POSTS_CUSTOM_COLUMN = 'manage_posts_custom_column';
    /**
     * @const
     */
    const MANAGE_PAGES_CUSTOM_COLUMN = 'manage_pages_custom_column';
    /**
     * @const
     */
    const SAVE_POST = 'save_post';
    /**
     * @const
     */
    const RESTRICT_MANAGE_POSTS = 'restrict_manage_posts';

    const AJAX_BACKEND = "ajaxBackend";
    const AJAX_FRONTEND = "ajaxFrontend";
    const AJAX_BOTH = "ajaxBoth";

    /**
     * @param callable $callback Server Side function handler for the ajax request
     * @param string $restriction Set default is WPActions::AJAX_FRONTEND if you whant to handle ajax request only for logged in users
     *
     * @return true Will return true if handler was added false if not.
     * @internal param callable $function_to_add The name of the function you wish to be called.
     */
    static function addAjaxHandler(callable $callback, $restriction = WPActions::AJAX_FRONTEND)
    {
        $name = "";
        $isHandlerAdded = false;
        if (is_callable($callback, false, $name)) {
            if (is_array($callback) && count($callback) == 2) {
                $name = $callback[1];
            }
            if ($restriction == WPActions::AJAX_BOTH) {
                $isHandlerAdded = add_action("wp_ajax_$name", $callback) && add_action("wp_ajax_nopriv_$name", $callback);
            } else if ($restriction == WPActions::AJAX_BACKEND) {
                $isHandlerAdded = add_action("wp_ajax_$name", $callback);
            } else if ($restriction == WPActions::AJAX_FRONTEND) {
                $isHandlerAdded = add_action("wp_ajax_nopriv_$name", $callback);
            }
        }

        return $isHandlerAdded;
    }
}