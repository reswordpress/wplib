<?php
/**
 * Author: Vitali Lupu <vitaliix@gmail.com>
 * Date: 3/5/18
 * Time: 6:10 PM
 */

namespace wp;

use WP_Query;

final class WPUtils
{
    /** @var \WP_Query $currentQuery */
    private static $currentQuery;
    private static $postsWatermarked = [];

    static function getPostTypes($args = [], $field = false, $operator = 'and')
    {
        global $wp_post_types;
        return wp_filter_object_list($wp_post_types, $args, $operator, $field);
    }

    static function getPosTaxonomies($postName = "", $field = false, $operator = 'and')
    {
        global $wp_taxonomies;
        return wp_filter_object_list($wp_taxonomies, ['object_type' => [$postName]], $operator, $field);
    }

    static function getCurrentQuery()
    {
        return self::$currentQuery;
    }

    static function locatePostTemplate(string $postType, string $name, string $currentDir = '')
    {
        if ($name) {
            $name = strtolower($name);
        }
        $postBaseType = PostBase::TYPE;
        $templateNames = [];
        if ($name !== '') {
            $templateNames[] = "{$postType}-{$name}.php";
            if ($postType != $postBaseType) {
                $templateNames [] = "{$postBaseType}-{$name}.php";
            }
        }
        $located = '';
        foreach ($templateNames as $templateName) {
            if ($templateName) {
                if (file_exists(STYLESHEETPATH . '/' . $templateName)) {
                    $located = STYLESHEETPATH . '/' . $templateName;
                    break;
                } else if (file_exists(TEMPLATEPATH . '/' . $templateName)) {
                    $located = TEMPLATEPATH . '/' . $templateName;
                    break;
                } else if (file_exists(ABSPATH . WPINC . '/theme-compat/' . $templateName)) {
                    $located = ABSPATH . WPINC . '/theme-compat/' . $templateName;
                    break;
                } else if (file_exists($currentDir . '/' . $templateName)) {
                    $located = $currentDir . '/' . $templateName;
                    break;
                }
            }
        }

        return $located;
    }

    static function renderTemplate(array $queryArgs, string $templatePath = '', &$results = 0)
    {
        $content = '';
        if ($templatePath != '') {
            self::$currentQuery = new WP_Query($queryArgs);
            $results = self::$currentQuery->post_count;
            while (self::$currentQuery->have_posts()) {
                self::$currentQuery->the_post();
                ob_start();
                load_template($templatePath, false);
                $content .= ob_get_clean();
            }
        }
        if (empty($content)) {
            $content .= self::getNotFoundMessage(__('No posts found.'));
        }
        self::$currentQuery = null;
        wp_reset_query();

        return $content;
    }
    // TODO: Make this functionality optional because it is not verify attachment dependency for case when some one use same image in different post type
    // TODO: Make a function that register who and when delete the object

    static function getNotFoundMessage($message = '', $redirectLink = '')
    {
        if (!isset($redirectLink)) {
            $redirectLink = '';
        }
        $textNotFound = sprintf(__("It looks like nothing was found at this location. Maybe try visiting %s directly?"),
            $redirectLink);
        if (!empty($message)) {
            $textNotFound = $message;
        }
        return "<h1 class='not-found text-xs-center'>{$textNotFound}</h1>";
    }

    /**
     * Delete Atached images to the post
     * Docs: https://codex.wordpress.org/Plugin_API/Action_Reference/before_delete_post
     * Solution: https://wordpress.org/support/topic/is-there-any-solution-for-deleting-posts-also-deletes-image-attached-to-it/
     * @param $post_id
     */
    static function deletePostAttachments($post_id)
    {
        //TODO Make Customizer option where we be able to chose for which post Media files must be deleted
        $postType = get_post_type($post_id);
        if (in_array($postType, self::$postsWatermarked)) {
            $media = get_children([
                QueryPost::PARENT => $post_id,
                QueryPost::TYPE => WPostTypes::ATTACHMENT
            ]);
            if (!empty($media)) {
                foreach ($media as $file) {
                    // pick what you want to do
                    // unlink(get_attached_file($file->ID));
                    //TODO Delete Cached File with same id
                    wp_delete_attachment($file->ID);
                }
            }
        }
    }

    static function getThumbnail($size = WPImages::THUMB, $attr = null)
    {
        $postId = get_the_ID();
        $result = false;
        //TODO Add Option to Customizer where we be able to choose which Post will be watermarked
        if (has_post_thumbnail($postId)) {
            $imageId = get_post_thumbnail_id($postId);
            //TODO Don't add attr if i want to watermark it will return only scaled image path then we will watermark and add watermark-termination
            $result = Placeholder::getScaledImage($imageId, $size, $attr);
            //$postType = get_post_type($postId);
            //in_array($postType, self::$postsWatermarked)
        }
        if (!$result) {
            $result = Placeholder::getPlaceHolder($size, $attr);
        }

        return $result;
    }

    static function loadThemeLocale($domain, $language = "")
    {
        unload_textdomain($domain);
        if (empty($language)) {
            $language = WPUtils::getLanguageShortCode();
        }
        $result = load_textdomain($domain, get_template_directory() . "/languages/$language.mo");
        if (!$result) {
            $result = load_textdomain($domain, WP_LANG_DIR . "/themes/$domain-$language.mo");
        }

        return $result;
    }

    static function getLanguageShortCode()
    {
        $locale = get_locale();
        if (is_admin()) {
            $locale = get_user_locale();
        }
        $locale = substr($locale, 0, 2);

        return $locale;
    }

    /**
     * Retrieve or display nonce hidden field for forms.
     *
     * The nonce field is used to validate that the contents of the form came from
     * the location on the current site and not somewhere else. The nonce does not
     * offer absolute protection, but should protect against most cases. It is very
     * important to use nonce field in forms.
     *
     * The $action and $name are optional, but if you want to have better security,
     * it is strongly suggested to set those two parameters. It is easier to just
     * call the function without any parameters, because validation of the nonce
     * doesn't require any parameters, but since crackers know what the default is
     * it won't be difficult for them to find a way around your nonce and cause
     * damage.
     *
     * The input name will be whatever $name value you gave. The input value will be
     * the nonce creation value.
     * @param int|string $action Optional. Action name. Default -1.
     * @param string $name Optional. Nonce name. Default '_wpnonce'.
     * @param bool $referrer Optional. Whether to set the referer field for validation. Default true.
     * @param bool $echo Optional. Whether to display or return hidden form field. Default true.
     * @return string Nonce field HTML markup.
     */
    static function getNonceField($action = -1, string $name = "_nonce", bool $referrer = true, bool $echo = true)
    {
        $name = esc_attr($name);
        $nonce = wp_create_nonce($action);
        $nonceField = "<input type='hidden' name='$name' value='$nonce'/>";
        if ($referrer) {
            $nonceField .= wp_referer_field(false);
        }
        if ($echo) {
            echo $nonceField;
        }
        return $nonceField;
    }

    static function isRealUserBrowser()
    {
        //Unknown
        return (isset($_SERVER['HTTP_USER_AGENT']) && empty($_SERVER['HTTP_USER_AGENT']) === false && strpos($_SERVER['HTTP_USER_AGENT'], 'GTmetrix') === false && strpos($_SERVER['HTTP_USER_AGENT'], 'Speed Insights') === false && strpos($_SERVER['HTTP_USER_AGENT'], 'Bot') === false && strpos($_SERVER['HTTP_USER_AGENT'], 'bot') === false);
    }

    static function saveServerReferrals()
    {
        $httpUserAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "Empty";
        //$httpReferral = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "EmptyReferer";
        if (!empty($httpUserAgent)) {
            $fp = fopen('file.txt', 'a');
            //fwrite($fp, "$httpUserAgent [$httpReferral]" . "\n");
            fwrite($fp, "$httpUserAgent" . "\n");
            fclose($fp);
        }
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

    /**
     * WordPress Login page Logo URL
     * @return string - site url
     */
    static function getLoginLogoUrl()
    {
        return home_url();
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
     * Checks if current user is restricted or not
     * @return bool
     */
    static function isUserRestricted()
    {
        // get restricted level from theme options
        $levelOfRestriction = get_option(Customizer::BACKEND_ACCESS_LEVEL);
        if (!empty($levelOfRestriction)) {
            $levelOfRestriction = intval($levelOfRestriction);
        } else {
            $levelOfRestriction = 0;
        }
        $current_user = wp_get_current_user();
        // Redirects user below a certain user level to home url
        // Ref: https://codex.wordpress.org/Roles_and_Capabilities#User_Level_to_Role_Conversion
        if ($current_user->user_level <= $levelOfRestriction) {
            return true;
        }
        return false;
    }

    /**
     * Register Custom Widgets and Sidebars
     * @param        $id
     * @param        $name
     * @param        $description
     * @param string $tagTitle
     * @param string $tagContent
     */
    static function registerSidebarWidget($id, $name, $description = '', $tagTitle = 'h2', $tagContent = 'div')
    {
        register_sidebar([
            WPSidebar::ID => $id,
            WPSidebar::NAME => $name,
            WPSidebar::DESCRIPTION => $description,
            WPSidebar::BEFORE_WIDGET => "<$tagContent id='%1\$s' class='widget %2\$s'>",
            WPSidebar::AFTER_WIDGET => "</$tagContent>",
            WPSidebar::BEFORE_TITLE => "<$tagTitle class='widgettitle'>",
            WPSidebar::AFTER_TITLE => "</$tagTitle>",
            WPSidebar::CONTAINER_SELECTOR => "#$id"
        ]);
    }

    static function getSidebarContent(string $id, string $tagContent = 'div')
    {
        $content = "";
        if (is_active_sidebar($id)) {
            ob_start();
            dynamic_sidebar($id);
            $content = ob_get_clean();
            $content = self::getSidebar($id, $content, $tagContent);
        }
        return $content;
    }

    static function getSidebar(string $id, string $content = '', string $tagContent = 'div')
    {
        $classes = [];
        $sidebarMetaValue = (array)get_option(Widget::WIDGET_AREA);
        if (isset($sidebarMetaValue[$id]) && isset($sidebarMetaValue[$id][Widget::CSS_CLASSES])) {
            $sidebarBgColourValue = $sidebarMetaValue[$id][Widget::CSS_CLASSES];
            if (!empty($sidebarBgColourValue)) {
                $classes = explode(" ", $sidebarBgColourValue);
            }
        }
        $classes = array_map('esc_attr', $classes);
        $classes = array_unique($classes);
        $classesContent = join(" ", $classes);
        return "<$tagContent id='{$id}' class='widget-area $id {$classesContent}'>{$content}</$tagContent>";
    }

    static function getCurrentAuthor()
    {
        if (get_query_var('author_name')) {
            $currentAuthor = get_user_by('slug', get_query_var('author_name'));
        } else {
            $currentAuthor = get_userdata(get_query_var('author'));
        }
        if (!$currentAuthor) {
            global $authordata;
            $currentAuthor = $authordata;
        }

        return $currentAuthor;
    }

    static function getUriToLibsDir($path = __FILE__)
    {
        return get_template_directory_uri() . self::getDirName($path, 4) . 'libs';
    }

    static function getDirName($path, $level)
    {
        $result = DIRECTORY_SEPARATOR;
        if (isset($path) && $level > 0) {
            while ($level !== 0) {
                $result .= basename(dirname($path, $level--)) . DIRECTORY_SEPARATOR;
            }
        }
        return $result;
    }

    static function getPostAuthorAndDate($showFullDate = false, $showAuthor = false)
    {
        $textPublished = __('Published');
        $publishDate = human_time_diff(get_the_modified_time('U'), current_time('timestamp'));
        $publishTime = get_the_modified_time('d M Y');
        $result = sprintf(__('%1$s %2$s, %3$s ago (%4$s)'), '', $textPublished, $publishDate, $publishTime);
        if ($showFullDate == false) {
            $result = strtok($result, '(');
        }
        if ($showAuthor) {
            $result .= ' ' . self::getAuthorOfPost();
        }
        return $result;
    }

    static function getAuthorOfPost($showAvatar = true)
    {
        $author = get_the_author_meta('display_name');
        $byAuthor = '';
        if ($showAvatar) {
            //TODO Add code Author avatar photo resolve
        }
        $textAuthor = __('Author:');
        $byAuthor .= "<strong>{$textAuthor} {$author}</strong>";
        return $byAuthor;
    }
}