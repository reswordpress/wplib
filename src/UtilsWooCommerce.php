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
        add_filter(WPActions::NAV_MENU_ITEMS, [$this, 'handleNavMenuItems'], 10, 2);
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

    function getAddToCartLink()
    {
        $activePlugins = apply_filters('active_plugins', get_option('active_plugins'));
        $content = '';
        if (in_array('woocommerce/woocommerce.php', $activePlugins)) {
            $countCartContents = WC()->cart->get_cart_contents_count();
            $titleMenuItem = __('View your shopping cart', 'your-theme-slug');
            $urlMenuItem = wc_get_cart_url();
            if ($countCartContents == 0) {
                $urlMenuItem = get_permalink(wc_get_page_id('shop'));
                $titleMenuItem = __('Start shopping', 'your-theme-slug');
            }
            $textCartItems = _n('%d item', '%d items', $countCartContents, 'woocommerce');
            $textCartItems = sprintf($textCartItems, $countCartContents);
            $textCartTotal = WC()->cart->get_cart_total();
            //$urlMenuItem = '#';
            $content = "<a href='{$urlMenuItem}' title='{$titleMenuItem}' class='cart-contents' tabindex='7'>
            <i class='fa fa-shopping-cart'></i> {$textCartItems} - {$textCartTotal}</a>";
        }
        return $content;
    }

    /**
     * @param string $items The HTML list content for the menu items.
     * @param \stdClass $args An object containing wp_nav_menu() arguments.
     * @return string Modified HTML list content for the menu items.
     */
    function handleNavMenuItems(string $items, \stdClass $args)
    {
        $linkAddToCart = $this->getAddToCartLink();
        if ($linkAddToCart !== '' && 'primary' !== $args->theme_location) {
            //ob_start();the_widget(WC_Widget_Cart::class); $subMenu = ob_get_clean();
            //$items .= "<li class='menu-item menu-item-has-children'>{$linkAddToCart}<ul class='sub-menu'><li class='menu-item'>{$subMenu}</li></ul></li>";
            $items .= "<li class='menu-item'>{$linkAddToCart}</li>";
        }
        return $items;
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