<?php


/**
 * For Order Management Site.
 *
 * 
 */
// Hook into WooCommerce checkout order processed
add_action('woocommerce_checkout_order_processed', 'send_order_to_other_domain', 10, 3);

// Hook into WooCommerce REST API order creation
add_action('woocommerce_rest_insert_shop_order_object', 'send_order_to_other_domain_rest', 10, 3);

function send_order_to_other_domain($order_id, $posted_data, $order)
{
    // Ensure the order object is retrieved
    if (!$order) {
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('Order not found: ' . $order_id);
            return;
        }
    }

    // Process order data
    process_order_data($order);
}

function send_order_to_other_domain_rest($order, $request, $creating)
{
    // Check if the request is for the specific endpoint
    if ($request->get_route() !== '/wc/v3/orders') {
        return;
    }

    // Check if the request contains line_items and set_paid data
    $body_params = $request->get_body_params();
    if (!isset($body_params['line_items']) || !isset($body_params['set_paid'])) {
        return;
    }

    // Process order data
    process_order_data($order);
}

function process_order_data($order)
{
    // Get the order items
    $items = $order->get_items();
    $orderItems = array();

    if (empty($items)) {
        error_log('No items found in order: ' . $order->get_id());
        return; // Return early if there are no items in the order
    } else {
        foreach ($items as $item_id => $item) {
            $product = $item->get_product();
            if ($product) {
                $orderItems[] = array(
                    'product_id' => $product->get_id(),
                    'product_name' => $product->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total(),
                    // Add other item data here
                );
            }
        }
    }

    // Extract shipping lines
    $shipping_lines = array();
    foreach ($order->get_items('shipping') as $shipping_item_id => $shipping_item) {
        $shipping_lines[] = array(
            'method_id' => $shipping_item->get_method_id(),
            'method_title' => $shipping_item->get_name(),
            'total' => $shipping_item->get_total(),
        );
    }

    // Get the order data
    $orderData = array(
        'order_number' => $order->get_order_number(),
        'order_id' => $order->get_id(),
        'order_status' => $order->get_status(),
        'shipping_lines' => $shipping_lines,
        'items' => $orderItems,
        'billing' => $order->get_address('billing'),
        'shipping' => $order->get_address('shipping'),
        'payment_method' => $order->get_payment_method(),
        'payment_method_title' => $order->get_payment_method_title(),
        'site_url' => get_site_url(),
        // Add other order data here
    );

    error_log(print_r($orderData, true));

    // Basic Authentication details
    // $username = 'hobbit';
    // $password = 'punctual';
    // $auth = base64_encode("$username:$password");

    $jwtToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL29yZGVybWFuYWdlLnRlc3QiLCJpYXQiOjE3MjExNDMyMTMsIm5iZiI6MTcyMTE0MzIxMywiZXhwIjoxNzIxNzQ4MDEzLCJkYXRhIjp7InVzZXIiOnsiaWQiOiIxIn19fQ.Hs6OXlqf8KGnj2h4ZMsCF5cxMam6tKUkMC4o3T2BLG4';

    // Send the order data to the other domain
    $response = wp_remote_post(
        // 'https://om.lukpaluk.xyz/wp-json/manage-order/v1/create',
        'https://ordermanage.test/wp-json/manage-order/v1/create',
        array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                // 'Authorization' => 'Basic ' . $auth
                'Authorization' => 'Bearer ' . $jwtToken
            ),
            'body' => json_encode($orderData),
            'sslverify' => false
        )
    );

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log("Something went wrong: $error_message");
    } else {
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        error_log('Response: ' . print_r($response_body, true));
    }
}

/**
 * Order Management Functions END.
 */