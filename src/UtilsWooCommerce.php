<?php /** Author: Vitali Lupu <vitaliix@gmail.com> */

namespace wp;
final class UtilsWooCommerce
{
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
        /** Add WooComerce Theme Support */
        add_action('after_setup_theme', [$this, 'addSupportOfWooCommerce']);
        add_action('woocommerce_login_redirect', [$this, 'handleWooCommerceLoginRedirect'], 10, 1);
        /** Add: Menu Item Add to cart*/
        add_filter(WPActions::NAV_MENU_ITEM_LINK_ATTRIBUTES, [$this, 'handleNavMenuItemLinkAttributes'], 10, 4);
        add_filter('woocommerce_add_to_cart_fragments', [$this, 'handleNavMenuItemsAddToCart']);
        /** Remove: Product Link open and close tag*/
        remove_action('woocommerce_before_shop_loop_item',
            'woocommerce_template_loop_product_link_open', 10);
        remove_action('woocommerce_after_shop_loop_item',
            'woocommerce_template_loop_product_link_close', 5);

        add_action('woocommerce_before_shop_loop_item', [$this, 'handleBeforeShopLoopItem'], 10);
        add_action('woocommerce_after_shop_loop_item', [$this, 'handleAfterShopLoopItem'], 5);
        /* Remove: Thumbnail */
        remove_action('woocommerce_before_shop_loop_item_title',
            'woocommerce_template_loop_product_thumbnail', 10);
        add_action('woocommerce_before_shop_loop_item_title', [$this, 'handleBeforeShopLoopItemTitleThumb'], 10);
        /* Remove: Product Title*/
        remove_action('woocommerce_shop_loop_item_title',
            'woocommerce_template_loop_product_title', 10);
        add_action('woocommerce_shop_loop_item_title', [$this, 'handleBeforeShopLoopItemTitle']);
        /* Remove: Rating*/
        remove_action('woocommerce_after_shop_loop_item_title',
            'woocommerce_template_loop_rating', 5);
        /* Remove: Price*/
        remove_action('woocommerce_after_shop_loop_item_title',
            'woocommerce_template_loop_price', 10);
        add_action('woocommerce_after_shop_loop_item_title', [$this, 'handleAfterShopLoopItemTitle']);
        /* Remove: Add to Cart*/
        remove_action('woocommerce_after_shop_loop_item',
            'woocommerce_template_loop_add_to_cart', 10);
    }

    /** Add WooComerce Theme Support */
    function addSupportOfWooCommerce()
    {
        add_theme_support('woocommerce');
        /*add_theme_support( 'woocommerce', array(
            'thumbnail_image_width' => 150,
            'single_image_width'    => 300,
            'product_grid'          => array(
                'default_rows'    => 3,
                'min_rows'        => 2,
                'max_rows'        => 8,
                'default_columns' => 4,
                'min_columns'     => 2,
                'max_columns'     => 5,
            ),
        ) );*/
    }

    static public function getProductLink()
    {
        /**@var $product \WC_Product */
        global $product;
        return esc_url(apply_filters('woocommerce_loop_product_link', get_the_permalink(), $product));
    }

    static public function getHtmlProductSale()
    {

    }

    function getProductRatingMessage()
    {
        /**@var $product \WC_Product */
        global $product;
        //$countReviews = $product->get_review_count();
        $countRating = $product->get_rating_count();
        $averageRating = $product->get_average_rating();
        $textRating = sprintf(__('Rated %s out of 5', 'woocommerce'), $countRating);
        if (0 < $countRating) {
            /* translators: 1: rating 2: rating count */
            $textRating = _n('Rated %1$s out of 5 based on %2$s customer rating',
                'Rated %1$s out of 5 based on %2$s customer ratings', $countRating, 'woocommerce');
            $textRating = sprintf($textRating, $averageRating, $countRating);
        }
        return $textRating;
    }

    /**
     * Filters the HTML attributes applied to a menu item's anchor element.
     * @param array $attributes The HTML attributes applied to the menu item's `<a>` element, empty strings are ignored.
     * @param \WP_Post $item The current menu item.
     * @param \stdClass $args An object of wp_nav_menu() arguments.
     * @param int $depth Depth of menu item. Used for padding.
     * @return array
     */
    function handleNavMenuItemLinkAttributes(array $attributes, \WP_Post $item, \stdClass $args, int $depth)
    {
        $activePlugins = apply_filters('active_plugins', get_option('active_plugins'));
        if (in_array('woocommerce/woocommerce.php', $activePlugins) && $args->theme_location == '' && $depth == 0){
            $urlMenuItem = wc_get_cart_url();
            if ($urlMenuItem == $attributes['href']){
                $countCartContents = WC()->cart->get_cart_contents_count();
                $titleMenuItem = __('View your shopping cart', 'your-theme-slug');
                if ($countCartContents == 0) {
                    $titleMenuItem = __('Start shopping', 'your-theme-slug');
                }
                $textCartItems = _n('%d item', '%d items', $countCartContents, 'woocommerce');
                $textCartItems = sprintf($textCartItems, $countCartContents);
                $textCartTotal = WC()->cart->get_cart_total();

                $item->title = "<i class='fa fa-shopping-cart'></i> {$textCartItems} - {$textCartTotal}";
                $attributes['class'] = 'cart-contents';
                $attributes['title'] = $titleMenuItem;
            }
        }
        return $attributes;
    }

    function handleNavMenuItemsAddToCart($fragments)
    {
        $fragments['a.cart-contents'] = $this->getAddToCartLink();
        return $fragments;
    }

    function handleWooCommerceLoginRedirect($redirect)
    {
        $redirect_page_id = url_to_postid($redirect);
        $checkout_page_id = wc_get_page_id('checkout');

        if ($redirect_page_id == $checkout_page_id) {
            return $redirect;
        }
        return get_permalink(get_option('woocommerce_myaccount_page_id')) . 'edit-account/';
    }

    function handleBeforeShopLoopItem()
    {
        echo "<div class='card card-product'>";
    }

    function handleAfterShopLoopItem()
    {
        echo "</div>";
    }

    function handleBeforeShopLoopItemTitleThumb()
    {
        $productThumb = woocommerce_get_product_thumbnail();
        echo "<div class='card-image'><a href='{$this->getProductLink()}'>{$productThumb}</a></div>";
    }

    function handleBeforeShopLoopItemTitle()
    {
        $productTitle = get_the_title();
        echo "<h5 class='card-title text-hide-overflow'><a href='{$this->getProductLink()}'>{$productTitle}</a></h5>";
    }

    function handleAfterShopLoopItemTitle()
    {
        /**@var $product \WC_Product */
        global $product;
        //[Rating]
        $htmlRating = '';
        if (get_option('woocommerce_enable_review_rating') !== 'no') {
            $ratingWidth = (($product->get_average_rating() / 5) * 100);
            $htmlRating = "<div class='star-rating' style='display: inline-block'><span style='width:{$ratingWidth}%'></span></div>";
        }
        //[Price]
        if ($htmlPrice = $product->get_price_html()) {
            $htmlPrice = "<h5>{$htmlPrice}</h5>";
        }
        //[Add To Cart]
        ob_start();
        woocommerce_template_loop_add_to_cart();
        $htmlAddToCart = ob_get_clean();
        echo "<div class='text-xs-center'>{$htmlRating}{$htmlPrice}<p>{$htmlAddToCart}</p></div>";
    }
}