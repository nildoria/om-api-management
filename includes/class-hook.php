<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AlarndHook
{

    // Constructor
    public function __construct()
    {
        // hide unwanted meta from order item
        add_filter('woocommerce_hidden_order_itemmeta', array($this, 'hidden_order_itemmeta'), 50);

        // replace order item thumbnail with custom thumbnail URL
        add_filter('woocommerce_admin_order_item_thumbnail', array($this, 'order_item_thumbnail'), 30, 3);
    }

    // Register the custom routes
    function hidden_order_itemmeta($args)
    {
        $args[] = '_mockup_thumbnail_url';
        return $args;
    }

    /**
     * Modifies the order item thumbnail by wrapping it with an anchor tag and replacing the image source with a custom thumbnail URL.
     *
     * @param string $thumbnail The original thumbnail HTML.
     * @param int $item_id The ID of the order item.
     * @param mixed $item The order item object.
     * @return string The modified thumbnail HTML.
     */
    function order_item_thumbnail($thumbnail, $item_id, $item)
    {

        // error_log( "custom_order_item_thumbnail ---------- $item_id" );

        $wc_thumb = '';

        $_mockup_thumbnail_url = wc_get_order_item_meta($item_id, '_mockup_thumbnail_url', true);
        if (!empty($_mockup_thumbnail_url)) {
            // error_log( "_mockup_thumbnail_url $_mockup_thumbnail_url" );
            $wc_thumb = $_mockup_thumbnail_url;
        }

        if ($wc_thumb) {
            // Wrap the thumbnail with an anchor tag
            $thumbnail = '<a target="_blank" href="' . esc_url($wc_thumb) . '">' . $thumbnail . '</a>';

            // Replace the image source with the custom thumbnail URL
            $thumbnail = preg_replace('/src="([^"]*)"/', 'src="' . esc_url($wc_thumb) . '"', $thumbnail);
        }

        return $thumbnail;
    }
}

// Initialize the class
new AlarndHook();