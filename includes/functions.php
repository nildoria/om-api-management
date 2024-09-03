<?php
/**
 * For Order Management Site.
 *
 * 
 */

// Hook into WooCommerce REST API order creation
add_action('woocommerce_rest_insert_shop_order_object', 'send_order_to_other_domain_rest', 10, 3);
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
    process_order_data($order, 'manual_order');
}

// Hook into WooCommerce order status change
add_action('woocommerce_order_status_changed', 'send_order_to_other_domain', 10, 4);
function send_order_to_other_domain($order_id, $old_status, $new_status, $order)
{
    // Ensure we are only sending the order when it changes to "processing"
    if ($new_status !== 'processing') {
        return;
    }

    // Ensure the order object is retrieved
    if (!$order) {
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('Order not found: ' . $order_id);
            return;
        }
    }

    // Check if the order has already been sent
    $order_already_sent = get_post_meta($order_id, '_order_sent_to_management', true);

    if ($order_already_sent) {
        // If the order has already been sent, don't send it again
        error_log('Order ' . $order_id . ' has already been sent. Skipping...');
        return;
    }

    // Check if the order was created manually
    $manual_order_created = get_post_meta($order_id, '_manual_order_created', true);
    if ($manual_order_created === 'yes') {
        // If the order was created manually, do not send it
        error_log('Manual order ' . $order_id . ' detected. Skipping send_order_to_other_domain...');
        return;
    }

    // Process order data
    process_order_data($order, 'mainSite_order');

    // Mark the order as sent
    update_post_meta($order_id, '_order_sent_to_management', true);
}

function process_order_data($order, $order_source)
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

    error_log('Shipping lines: ' . print_r($shipping_lines, true));

    // Get the order totoal
    $order_total = $order->get_total();

    // get date created
    $date_created = $order->get_date_created();

    // Get the order data
    $orderData = array(
        'order_number' => $order->get_order_number(),
        'order_id' => $order->get_id(),
        'order_status' => $order->get_status(),
        'date_created' => $date_created->date('Y-m-d H:i:s'),
        'shipping_lines' => $shipping_lines,
        'items' => $orderItems,
        'billing' => $order->get_address('billing'),
        'shipping' => $order->get_address('shipping'),
        'payment_method' => $order->get_payment_method(),
        'payment_method_title' => $order->get_payment_method_title(),
        'total' => $order_total,
        'site_url' => get_site_url(),
        'order_source' => $order_source,
        // Add other order data here
    );

    // error_log(print_r($orderData, true));

    // Username and Password for Basic Authentication
    $username = 'OmAdmin';
    $host = $_SERVER['HTTP_HOST'];

    if (strpos($host, 'allaround.test') !== false) {
        // Local environment
        $password = 'Qj0p rsPu eU2i Fzco pwpX eCPD';
        $api_url = 'https://ordermanage.test/wp-json/manage-order/v1/create';
    } elseif (strpos($host, 'lukpaluk.xyz') !== false) {
        // Staging environment
        $password = 'vZmm GYw4 LKDg 4ry5 BMYC 4TMw';
        $api_url = 'https://om.lukpaluk.xyz/wp-json/manage-order/v1/create';
    } else {
        // Production environment
        $password = 'Vlh4 F7Sw Zu26 ShUG 6AYu DuRI';
        $api_url = 'https://om.allaround.co.il/wp-json/manage-order/v1/create';
    }

    $auth_header = get_basic_auth_header($username, $password);

    // Send the order data to the other domain
    $response = wp_remote_post(
        $api_url,
        array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => $auth_header
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

function get_basic_auth_header($username, $password)
{
    $auth = base64_encode("$username:$password");
    return 'Basic ' . $auth;
}


/**
 * Order Management Functions END.
 */

// function order_management_enqueue_scripts()
// {
//     wp_enqueue_script('order-management-progress', plugin_dir_url(__FILE__) . 'order-management-progress.js', array('jquery'), null, true);
//     wp_localize_script(
//         'order-management-progress',
//         'orderManagementVars',
//         array(
//             'ajaxUrl' => admin_url('admin-ajax.php'),
//         )
//     );
// }
// add_action('admin_enqueue_scripts', 'order_management_enqueue_scripts');

// /**
//  * Fetch Previous orders from the main site
//  */
// add_action('admin_menu', 'order_management_menu');

// function order_management_menu()
// {
//     add_menu_page(
//         'Order Management',        // Page title
//         'Order Management',        // Menu title
//         'manage_options',          // Capability
//         'order-management',        // Menu slug
//         'order_management_page',   // Callback function
//         'dashicons-admin-generic', // Icon
//         20                         // Position
//     );
// }

// function order_management_page()
// {
//     if (!current_user_can('manage_options')) {
//         return;
//     }

//     // Get the processed order IDs from the database
//     $processed_order_ids = get_option('processed_order_ids', array());

//     echo '<div class="wrap">';
//     echo '<h1>Order Management</h1>';
//     echo '<p>';
//     echo '<button id="startProcessing" class="button-primary">Start Processing</button>';
//     echo ' <button id="stopProcessing" class="button-secondary">Stop Processing</button>';
//     echo '</p>';
//     echo '<div id="progressWrapper" style="display:none;">';
//     echo '<div id="progressBar" style="width: 0%; background-color: #0073aa; height: 20px;"></div>';
//     echo '</div>';
//     echo '<p id="progressStatus" style="display:none;">Processing orders... <span id="processedCount">0</span> processed.</p>';

//     // Display the processed order IDs
//     if (!empty($processed_order_ids)) {
//         echo '<h2>Processed Order IDs</h2>';
//         echo '<ul>';
//         foreach ($processed_order_ids as $order_id) {
//             echo '<li>Order ID: ' . esc_html($order_id) . '</li>';
//         }
//         echo '</ul>';
//     } else {
//         echo '<p>No orders have been processed yet.</p>';
//     }

//     echo '</div>';
// }


// function process_order_batch_callback()
// {
//     // Set the batch size (how many orders to process at once)
//     $batch_size = 50;

//     // Get the list of processed order IDs
//     $processed_order_ids = get_option('processed_order_ids', array());

//     // Query existing orders that haven't been processed yet
//     $args = array(
//         'type' => 'shop_order',
//         'post_status' => array('wc-processing', 'wc-completed'),
//         'posts_per_page' => $batch_size,
//         'orderby' => 'ID',
//         'order' => 'ASC',
//         'exclude' => $processed_order_ids, // Exclude already processed orders
//         'meta_query' => array(
//             array(
//                 'key' => '_order_sent_to_management',
//                 'compare' => 'NOT EXISTS',
//             ),
//         ),
//     );

//     $orders = wc_get_orders($args);

//     // If there are no orders left to process
//     if (empty($orders)) {
//         delete_option('processed_order_ids');
//         wp_send_json_error('No more orders to process.');
//         return;
//     }

//     foreach ($orders as $order) {
//         // Process each order
//         process_order_data($order, 'mainSite_order');

//         // Mark the order as sent by adding a custom meta field
//         update_post_meta($order->get_id(), '_order_sent_to_management', true);

//         // Add the processed order ID to the list
//         $processed_order_ids[] = $order->get_id();
//     }

//     // Update the processed order IDs in the database
//     update_option('processed_order_ids', $processed_order_ids);

//     // Count remaining orders that still need to be processed
//     $remaining_orders = wc_get_orders(
//         array(
//             'type' => 'shop_order',
//             'post_status' => array('wc-processing', 'wc-completed', 'wc-pending', 'wc-on-hold'),
//             'posts_per_page' => -1,
//             'orderby' => 'ID',
//             'order' => 'ASC',
//             'exclude' => $processed_order_ids, // Exclude already processed orders
//             'meta_query' => array(
//                 array(
//                     'key' => '_order_sent_to_management',
//                     'compare' => 'NOT EXISTS',
//                 ),
//             ),
//             'fields' => 'ids',
//         )
//     );

//     $remaining_orders_count = count($remaining_orders);

//     // If there are still more orders to process, continue processing
//     if ($remaining_orders_count > 0) {
//         wp_send_json_success(array('processed' => count($orders), 'remaining_orders' => $remaining_orders_count));
//     } else {
//         // If all orders have been processed, clear the option and stop the process
//         // delete_option('processed_order_ids');
//         wp_send_json_success(array('processed' => count($orders), 'remaining_orders' => 0));
//     }
// }

// add_action('wp_ajax_process_order_batch', 'process_order_batch_callback');