<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AlarndPI
{

    // Constructor
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));

    }

    /**
     * Registers the custom routes for the 'alaround-generate/v1' REST API endpoint.
     *
     * This function registers a single route for the 'add-order-items' endpoint, which accepts a POST request
     * and expects an 'order_id' parameter in the URL. The callback function for this route is the 'add_order_items'
     * method of the current class instance. The 'permission_callback' is set to '__return_true', which means that
     * any user is allowed to access this route.
     *
     * @return void
     */
    public function register_routes()
    {
        register_rest_route(
            'alaround-generate/v1',
            '/add-order-items/(?P<order_id>\d+)',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'add_order_items'),
                'permission_callback' => '__return_true'
            )
        );
        register_rest_route(
            'alarnd-main/v1',
            '/products',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'fetch_products'),
                'permission_callback' => '__return_true',
                // 'args' => array(
                //     'product_id' => array(
                //         'validate_callback' => function ($param, $request, $key) {
                //             return is_numeric($param);
                //         }
                //     )
                // )
            )
        );
        register_rest_route(
            'alarnd-main/v1',
            '/products(?:/(?P<product_id>\d+))?',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'fetch_products'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'product_id' => array(
                        'validate_callback' => function ($param, $request, $key) {
                            return is_numeric($param);
                        }
                    )
                )
            )
        );
        register_rest_route(
            'update-order/v1',
            '/add-item-to-order',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'add_item_to_order'),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            'update-order/v1',
            '/duplicate-delete-to-order',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'duplicate_delete_item_to_order'),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            'update-order/v1',
            '/update-item-details',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'update_item_details'),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            'update-order/v1',
            '/update-item-meta',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'update_item_meta'),
                'permission_callback' => '__return_true', // Adjust as necessary for your permissions
            )
        );
        register_rest_route(
            'update-order/v1',
            '/delete-item-meta',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'delete_item_meta'),
                'permission_callback' => '__return_true', // Adjust as necessary for your permissions
            )
        );
        register_rest_route(
            'update-order/v1',
            '/rearrange-order-items',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_rearrange_order_items'),
                'permission_callback' => '__return_true',
            )
        );
        // Add more routes here
    }


    /**
     * Adds order items to an existing order and creates a new order with the items.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The REST response object.
     */
    public function add_order_items($request)
    {
        error_log('ml_add_custom_order_item');
        error_log(print_r($request->get_params(), true));

        $request_data = $request->get_params();

        $order_id = isset($request_data['order_id']) ? $request_data['order_id'] : '';
        $name = isset($request_data['name']) ? $request_data['name'] : '';
        $price = isset($request_data['price']) ? $request_data['price'] : '';
        $quantity = isset($request_data['quantity']) ? $request_data['quantity'] : '';
        $upsell_product_id = isset($request_data['product_id']) ? $request_data['product_id'] : '';
        $customer_phone = isset($request_data['customer_phone']) ? $request_data['customer_phone'] : '';
        $proof_id = isset($request_data['proof_id']) ? $request_data['proof_id'] : '';
        $referenceID = isset($request_data['referenceID']) ? $request_data['referenceID'] : '';
        $items = isset($request_data['items']) ? $request_data['items'] : [];

        if (empty($request_data) || empty($order_id) || empty($items)) {
            return rest_ensure_response(
                array(
                    'success' => false,
                    'message' => "Required fields are empty.",
                )
            );
        }

        // Dummy product ID
        $dummy_product_id = 885; // Replace 123 with the actual dummy product ID

        // Load the old order
        $old_order = wc_get_order($order_id);

        if (!$old_order) {
            return new WP_Error('invalid_order', 'Order does not exist.', array('status' => 404));
        }

        // Add the dummy product to the order
        $product = wc_get_product($dummy_product_id);
        if (!$product) {
            return new WP_Error('invalid_product', 'Dummy product does not exist.', array('status' => 404));
        }

        // Create a new order
        $new_order = wc_create_order();

        // Set the parent order ID
        $new_order->set_parent_id($order_id);

        // Add each item to the new order
        foreach ($items as $item) {

            $item_name = $item['name'];
            $item_price = $item['price'];
            $item_quantity = $item['quantity'];
            $item_thumbnail = $item['thumbnail'];
            $subtotal = $item['subtotal'];
            $color = isset($item['color']) ? $item['color'] : "";
            $size = isset($item['size']) ? $item['size'] : "";

            // Create a new order item
            $order_item = new WC_Order_Item_Product();
            $order_item->set_product($product);
            $order_item->set_name($item_name);
            $order_item->set_quantity($item_quantity);
            $order_item->set_subtotal($subtotal);
            $order_item->set_total($subtotal);

            // Add custom attributes as metadata
            if (!empty($color)) {
                $order_item->add_meta_data('color', $color, true);
            }
            if (!empty($size)) {
                $order_item->add_meta_data('size', $size, true);
            }

            // Add the order item to the new order
            $new_order->add_item($order_item);

            // Save the order item to get its ID
            $item_id = $order_item->save();

            // Add thumbnail URL or attachment ID as hidden metadata if provided
            if (!empty($item_thumbnail)) {
                $order_item->add_meta_data('_mockup_thumbnail_url', $item_thumbnail, true);
            }
        }

        if (!empty($referenceID)) {
            $new_order->add_order_note("Reference Number: #" . $referenceID . " for newly order item added by upsell product: " . $name . '(' . $upsell_product_id . ')');
        }

        // Calculate totals and save the new order
        $new_order->calculate_totals();
        // Mark the order as paid (change this status to match your payment method)
        $new_order->set_status('wc-processing');
        $new_order->save();

        $new_order_id = $new_order->get_id();

        return rest_ensure_response(
            array(
                'success' => true,
                'order_id' => $new_order_id,
                'message' => 'Custom order created successfully',
            )
        );
    }


    /**
     * For Order Management Site.
     *
     * Duplicate/Delete to Order API.
     */
    public function duplicate_delete_item_to_order(WP_REST_Request $request)
    {
        $order_id = $request->get_param('order_id');
        $item_id = $request->get_param('item_id');
        $method = $request->get_param('method');
        $nonce = $request->get_param('nonce');

        error_log("Duplicate Item $item_id, $method, $nonce");

        // Verify nonce
        // if (!wp_verify_nonce($nonce, 'order_management_nonce')) {
        //     return new WP_Error('invalid_nonce', 'Nonce is not valid', array('status' => 403));
        // }

        // Handle item duplication
        if ($item_id && $method === 'duplicateItem') {
            $new_item = $this->duplicate_order_item($order_id, $item_id);
            if (is_wp_error($new_item)) {
                return $new_item;
            }
            return new WP_REST_Response('Item duplicated successfully', 200);
        }

        // Handle item deletion
        if ($item_id && $method === 'deleteItem') {
            $result = $this->delete_order_item($order_id, $item_id);
            if (is_wp_error($result)) {
                return $result;
            }
            return new WP_REST_Response('Item deleted successfully', 200);
        }

        // Handle adding a new item to the order
        if (!$order_id) {
            return new WP_Error('missing_data', 'Missing order ID', array('status' => 400));
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', 'Invalid order ID', array('status' => 400));
        }

        $order->calculate_totals();
        $order->save();

        return new WP_REST_Response('Order updated successfully', 200);
    }


    /**
     * For Order Management Site.
     *
     * Add to Order API.
     */
    public function add_item_to_order(WP_REST_Request $request)
    {
        $items = $request->get_json_params();
        $nonce = $items[0]['nonce']; // Assuming all items have the same nonce

        // Verify nonce
        // if (!wp_verify_nonce($nonce, 'order_management_nonce')) {
        //     return new WP_Error('invalid_nonce', 'Nonce is not valid', array('status' => 403));
        // }

        error_log("Processing " . count($items) . " items");

        $total_quantity = 0;
        $non_freestyle_items = [];

        foreach ($items as $item) {
            if ($item['product_id'] !== 'freestyle') {
                $total_quantity += $item['quantity'];
                $non_freestyle_items[] = $item;
            }
        }

        $dynamic_price = 0;

        if (!empty($non_freestyle_items)) {
            $first_non_freestyle_item = $non_freestyle_items[0];
            $product_id = $first_non_freestyle_item['product_id'];
            $product = wc_get_product($product_id);

            if ($product) {
                $enable_custom_quantity = get_post_meta($product_id, 'enable_custom_quantity', true);

                if ($enable_custom_quantity) {
                    $steps = get_field('quantity_steps', $product_id);
                    $is_quantity_steps = true;
                } else {
                    $steps = get_field('discount_steps', $product_id);
                    $is_quantity_steps = false;
                }

                $dynamic_price = $this->calculate_dynamic_price($steps, $total_quantity, $product, $is_quantity_steps);
            }
        }

        foreach ($items as $item) {
            $order_id = $item['order_id'];
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            $color = $item['alarnd_color'];
            $size = $item['alarnd_size'];
            $art_pos = isset($item['allaround_art_pos']) ? $item['allaround_art_pos'] : '';
            $artwork_urls = json_decode($item['alarnd_artwork'], true);
            $instruction_note = $item['allaround_instruction_note'];
            $subtotal = isset($item['subtotal']) ? $item['subtotal'] : 0;

            error_log("Adding item with product ID $product_id and quantity $quantity");

            // Validate parameters
            if (!$order_id || !$product_id || !$quantity) {
                return new WP_Error('missing_data', 'Missing parameters', array('status' => 400));
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                return new WP_Error('invalid_order', 'Invalid order ID', array('status' => 400));
            }

            $order_item = new WC_Order_Item_Product();

            if ($product_id === 'freestyle') {
                $product_name = 'Freestyle Item';
                $dynamic_price_item = $subtotal / $quantity;

                $order_item->set_quantity($quantity);
                $order_item->set_name($product_name);
                $order_item->set_subtotal($dynamic_price_item * $quantity);
                $order_item->set_total($dynamic_price_item * $quantity);
            } else {
                $product = wc_get_product($product_id);
                if (!$product) {
                    return new WP_Error('invalid_product', 'Invalid product ID', array('status' => 400));
                }

                $product_name = $product->get_name();

                $order_item->set_product_id($product_id);
                $order_item->set_quantity($quantity);
                $order_item->set_name($product_name);
                $order_item->set_subtotal($dynamic_price * $quantity);
                $order_item->set_total($dynamic_price * $quantity);
            }

            if (!empty($color)) {
                $order_item->add_meta_data(__('Color', 'hello-elementor'), $color);
            }
            if (!empty($size)) {
                $order_item->add_meta_data(__('Size', 'hello-elementor'), $size);
            }
            if (!empty($art_pos)) {
                $order_item->add_meta_data(__('Art Position', 'hello-elementor'), $art_pos);
            }
            if (!empty($instruction_note)) {
                $order_item->add_meta_data(__('Instruction Note', 'hello-elementor'), $instruction_note);
            }
            if (!empty($artwork_urls) && is_array($artwork_urls)) {
                foreach ($artwork_urls as $artwork_url) {
                    $artwork_html = "<p>" . basename($artwork_url) . "</p><a href=\"" . $artwork_url . "\" target=\"_blank\"><img class=\"alarnd__artwork_img\" src=\"" . $artwork_url . "\" /></a>";
                    $order_item->add_meta_data(__('Attachment', 'hello-elementor'), $artwork_html);
                }
            } elseif (!empty($artwork_urls)) {
                $artwork_html = "<p>" . basename($artwork_urls) . "</p><a href=\"" . $artwork_urls . "\" target=\"_blank\"><img class=\"alarnd__artwork_img\" src=\"" . $artwork_urls . "\" /></a>";
                $order_item->add_meta_data(__('Attachment', 'hello-elementor'), $artwork_html);
            }

            $order->add_item($order_item);
            $order->calculate_totals();
            $order->save();
        }

        return new WP_REST_Response('Item(s) added successfully', 200);
    }



    /**
     * Update Item Details API.
     */
    public function update_item_details(WP_REST_Request $request)
    {
        $order_id = $request->get_param('order_id');
        $item_id = $request->get_param('item_id');
        $new_cost = $request->get_param('new_cost');
        $new_quantity = $request->get_param('new_quantity');

        if (!$order_id || !$item_id || ($new_cost === null && $new_quantity === null)) {
            return new WP_Error('missing_data', 'Missing parameters', array('status' => 400));
        }

        // Convert new cost to float
        $new_cost = floatval($new_cost);
        $new_quantity = intval($new_quantity);

        // Retrieve the order
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', 'Order does not exist.', array('status' => 404));
        }

        // Find the item in the order and update the cost
        $item = $order->get_item($item_id);
        if (!$item) {
            return new WP_Error('invalid_item', 'Item does not exist.', array('status' => 404));
        }

        // Update item cost if provided
        if ($new_cost > 0) {
            $item->set_subtotal($new_cost * $item->get_quantity());
            $item->set_total($new_cost * $item->get_quantity());
        }

        // Update item quantity if provided
        if ($new_quantity > 0) {
            $item->set_quantity($new_quantity);

            // Update totals if new cost is also provided
            if ($new_cost > 0) {
                $item->set_subtotal($new_cost * $new_quantity);
                $item->set_total($new_cost * $new_quantity);
            } else {
                // Use the existing item cost if new cost is not provided
                $item->set_subtotal($item->get_subtotal() / $item->get_quantity() * $new_quantity);
                $item->set_total($item->get_total() / $item->get_quantity() * $new_quantity);
            }
        }

        // Save the item changes
        $item->save();

        // Recalculate the order totals and save
        $order->calculate_totals();
        $order->save();

        // Get the updated item total
        $item_total = $item->get_total();

        // Calculate updated totals
        $items_subtotal = 0;
        foreach ($order->get_items() as $order_item) {
            $items_subtotal += $order_item->get_total();
        }

        $shipping_total = $order->get_shipping_total();
        $order_total = $order->get_total();

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'Item details updated successfully!',
                'item_total' => number_format($item_total, 2),
                'items_subtotal' => number_format($items_subtotal, 2),
                'shipping_total' => number_format($shipping_total, 2),
                'order_total' => number_format($order_total, 2),
            ),
            200
        );
    }

    /**
     * Update Item Meta API.
     */
    public function update_item_meta(WP_REST_Request $request)
    {
        $order_id = $request->get_param('order_id');
        $item_id = $request->get_param('item_id');
        $new_size = $request->get_param('size');
        $new_color = $request->get_param('color');
        $new_art_meta_key = $request->get_param('art_meta_key');
        $new_art_meta_id = $request->get_param('art_meta_id');
        $new_art_img = $request->get_param('artwork_img');
        $new_art_position = $request->get_param('art_position');
        $new_instruction_note = $request->get_param('instruction_note');

        if (!$order_id || !$item_id) {
            return new WP_Error('missing_data', 'Missing order_id or item_id', array('status' => 400));
        }

        // Retrieve the order
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', 'Order does not exist.', array('status' => 404));
        }

        // Find the item in the order
        $item = $order->get_item($item_id);
        if (!$item) {
            return new WP_Error('invalid_item', 'Item does not exist.', array('status' => 404));
        }

        // Initialize an array to hold the updated fields
        $updated_data = array();

        // Update the item meta data
        // Update the item meta data if it has changed
        if ($new_size !== null && $new_size != $item->get_meta('Size')) {
            $item->update_meta_data('Size', $new_size);
            $updated_data['size'] = $new_size;
        }
        if ($new_color !== null && $new_color != $item->get_meta('Color')) {
            $item->update_meta_data('Color', $new_color);
            $updated_data['color'] = $new_color;
        }
        if ($new_art_position !== null && $new_art_position != $item->get_meta('Art Position')) {
            $item->update_meta_data('Art Position', $new_art_position);
            $updated_data['art_position'] = $new_art_position;
        }
        if ($new_instruction_note !== null) {
            if ($new_instruction_note === '') {
                $item->delete_meta_data('Instruction Note');
                $updated_data['instruction_note'] = 'Deleted!';
            } else if ($new_instruction_note != $item->get_meta('Instruction Note')) {
                $item->update_meta_data('Instruction Note', $new_instruction_note);
                $updated_data['instruction_note'] = $new_instruction_note;
            }
        }

        if ($new_art_meta_key !== null && $new_art_img !== null) {
            $artwork_html = "<p>" . basename($new_art_img) . "</p><a href=\"" . $new_art_img . "\" target=\"_blank\"><img class=\"alarnd__artwork_img\" src=\"" . $new_art_img . "\" /></a>";

            if ($new_art_meta_id !== null) {
                // Update the specific meta data entry by ID
                foreach ($item->get_meta_data() as $meta) {
                    if ($meta->id == $new_art_meta_id) {
                        $item->update_meta_data($meta->key, $artwork_html);
                        $updated_data['attachment_url'] = $new_art_img;
                        break;
                    }
                }
            } else {
                // Add a new meta data entry
                $item->update_meta_data(__('Attachment', 'hello-elementor'), $artwork_html);
                $updated_data['attachment_url'] = $new_art_img;
            }
        }

        if (!empty($updated_data)) {
            // Save the item changes
            $item->save();

            // Recalculate the order totals and save
            $order->calculate_totals();
            $order->save();

            return new WP_REST_Response(
                array(
                    'success' => true,
                    'message' => 'Item meta updated successfully!',
                    'data' => $updated_data,
                ),
                200
            );
        } else {
            return new WP_Error('no_changes', 'No changes detected.', array('status' => 400));
        }
    }


    /**
     * Delete Item Artwork API.
     */
    public function delete_item_meta(WP_REST_Request $request)
    {
        $order_id = $request->get_param('order_id');
        $item_id = $request->get_param('item_id');
        $art_meta_id = $request->get_param('art_meta_id');

        if (!$order_id || !$item_id || !$art_meta_id) {
            return new WP_Error('missing_data', 'Missing order_id, item_id, or art_meta_id', array('status' => 400));
        }

        // Retrieve the order
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', 'Order does not exist.', array('status' => 404));
        }

        // Find the item in the order
        $item = $order->get_item($item_id);
        if (!$item) {
            return new WP_Error('invalid_item', 'Item does not exist.', array('status' => 404));
        }

        // Delete the item meta data by meta ID
        foreach ($item->get_meta_data() as $meta) {
            if ($meta->id == $art_meta_id) {
                $item->delete_meta_data($meta->key);
                break;
            }
        }

        // Save the item changes
        $item->save();

        // Recalculate the order totals and save
        $order->calculate_totals();
        $order->save();

        // Log the final meta data for the item
        error_log("Final meta data for item $item_id after saving: " . print_r($item->get_meta_data(), true));

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'Item Artwork deleted successfully!',
            ),
            200
        );
    }


    /**
     * Duplicate Item API.
     */
    public function duplicate_order_item($order_id, $item_id)
    {
        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('Order not found: ' . $order_id);
            return new WP_Error('invalid_order', 'Invalid order ID');
        }

        // Get the original order item
        $original_item = $order->get_item($item_id);
        if (!$original_item) {
            error_log('Item not found: ' . $item_id);
            return new WP_Error('invalid_item', 'Invalid item ID');
        }

        // Create a new order item
        $new_item = new WC_Order_Item_Product();

        // Copy properties from the original item
        $new_item->set_product_id($original_item->get_product_id());
        $new_item->set_quantity($original_item->get_quantity());
        $new_item->set_name($original_item->get_name());
        $new_item->set_subtotal($original_item->get_subtotal());
        $new_item->set_total($original_item->get_total());

        // Copy meta data
        foreach ($original_item->get_meta_data() as $meta) {
            $new_item->add_meta_data($meta->key, $meta->value);
        }

        // Add the new item to the order
        $order->add_item($new_item);

        // Calculate totals and save the order
        $order->calculate_totals();
        $order->save();

        return $new_item;
    }

    /**
     * Delete Item API.
     */
    public function delete_order_item($order_id, $item_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', 'Invalid order ID', array('status' => 400));
        }

        $item = $order->get_item($item_id);
        if (!$item) {
            return new WP_Error('invalid_item', 'Invalid item ID', array('status' => 400));
        }

        $order->remove_item($item_id);
        $order->calculate_totals();
        $order->save();

        return true;
    }

    /**
     * Rearrange Item Order API.
     */
    public function handle_rearrange_order_items(WP_REST_Request $request)
    {
        $order_id = $request->get_param('order_id');
        $new_order = $request->get_param('new_order');

        if (!$order_id || !$new_order) {
            return new WP_Error('missing_data', 'Missing parameters', array('status' => 400));
        }

        $new_order = array_map('intval', $new_order); // Ensure new_order is an array of integers

        $result = $this->rearrange_order_items($order_id, $new_order);

        if (is_wp_error($result)) {
            return $result;
        }
        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'Order items rearranged successfully!',
            ),
            200
        );
    }

    /**
     * Fetch Product List API.
     */
    public function fetch_products(WP_REST_Request $request)
    {
        $product_id = $request->get_param('product_id');

        $args = array(
            'status' => 'publish',
            'limit' => -1,
        );

        if ($product_id) {
            $args['include'] = array($product_id);
        }

        $products = wc_get_products($args);
        $product_list = array();

        foreach ($products as $product) {
            $product_id = $product->get_id();

            // Fetch custom fields using ACF
            $if_group_enabled = get_field('group_enable', $product_id);
            $if_custom_quantity_enabled = get_field('enable_custom_quantity', $product_id);

            $colors = null; // Initialize colors to null
            $available_sizes = null; // Initialize sizes to null
            $art_positions = null; // Initialize art positions to null

            if ($if_custom_quantity_enabled) {
                $quantity_steps = get_field('quantity_steps', $product_id);
                $colors = get_field('colors', $product_id);
                $is_custom_quantity = true;
                $is_group_quantity = false;
            } else if ($if_group_enabled) {
                $quantity_steps = get_field('discount_steps', $product_id);
                $colors = get_field('color', $product_id);

                $adult_sizes = get_field('adult_sizes', 'option', false);
                $child_sizes = get_field('child_sizes', 'option', false);
                // Ensure sizes are arrays
                $adult_sizes = is_array($adult_sizes) ? $adult_sizes : ml_filter_string_to_array($adult_sizes);
                $child_sizes = is_array($child_sizes) ? $child_sizes : ml_filter_string_to_array($child_sizes);

                $sizes = array_merge($adult_sizes, $child_sizes);

                $omit_sizes = get_field('omit_sizes_from_chart', $product_id);
                if (is_array($omit_sizes) && isset($omit_sizes[0]['value'])) {
                    $omit_sizes = array_map(function ($size) {
                        return $size['value'];
                    }, $omit_sizes);
                } elseif (!is_array($omit_sizes)) {
                    $omit_sizes = ml_filter_string_to_array($omit_sizes);
                }

                // Exclude omitted sizes
                $available_sizes = array_diff($sizes, $omit_sizes);

                $art_positions = get_field('art_positions', $product_id);

                $is_custom_quantity = false;
                $is_group_quantity = true;
            } else {
                $quantity_steps = null;
                $is_custom_quantity = false;
                $is_group_quantity = false;
            }

            $thumbnail_url = get_the_post_thumbnail_url($product_id, 'thumbnail');
            $image_url = get_the_post_thumbnail_url($product_id, 'large');

            // Get product categories with name and slug
            $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'all'));
            $category_list = array_map(function ($category) {
                return array(
                    'name' => $category->name,
                    'slug' => $category->slug,
                );
            }, $categories);

            $product_list[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'is_custom_quantity' => $is_custom_quantity,
                'is_group_quantity' => $is_group_quantity,
                'quantity_steps' => $quantity_steps,
                'colors' => $colors,
                'sizes' => $available_sizes,
                'art_positions' => $art_positions,
                'thumbnail' => $thumbnail_url,
                'image' => $image_url,
                'categories' => $category_list,
            );
        }

        return new WP_REST_Response($product_list, 200);
    }

    /**
     * Order Management Functions END.
     */




    function calculate_dynamic_price($discount_steps, $quantity, $product, $is_quantity_steps = false)
    {
        $dynamic_price = $product->get_regular_price();
        if ($is_quantity_steps) {
            // Handle the quantity_steps structure
            foreach ($discount_steps as $step) {
                error_log("Checking step: " . print_r($step, true));
                if ($quantity >= $step['quantity']) {
                    $dynamic_price = $step['amount'];
                    error_log("Matched Step: " . print_r($step, true));
                    error_log("Updated Dynamic Price: " . $dynamic_price);
                } else {
                    break;
                }
            }
        } else {
            foreach ($discount_steps as $step) {
                error_log("Checking discount step: " . print_r($step, true));
                if ($quantity <= $step['quantity']) {
                    $dynamic_price = $step['amount'];
                    error_log("Matched discount Step: " . print_r($step, true));
                    error_log("Updated discount Dynamic Price: " . $dynamic_price);
                    break;
                }
            }
        }
        if ($dynamic_price == 0) {
            $dynamic_price = $product->get_regular_price();
        }

        return $dynamic_price;
    }

    /**
     * Rearrange order items.
     *
     * @param int $order_id The order ID.
     * @param array $new_order The new order of item IDs.
     * @return WP_Error|bool
     */
    function rearrange_order_items($order_id, $new_order)
    {
        error_log("Starting to rearrange order items");
        error_log("Order ID: " . $order_id);
        error_log("New Order: " . print_r($new_order, true));

        $order = wc_get_order($order_id);

        if (!$order) {
            error_log("Invalid order ID: " . $order_id);
            return new WP_Error('invalid_order', 'Invalid order ID', array('status' => 400));
        }

        $items = $order->get_items('line_item');
        error_log("Current items: " . print_r($items, true));

        // Create an associative array with item IDs as keys
        $items_array = [];
        foreach ($items as $item_id => $item) {
            $items_array[$item_id] = $item;
            error_log("Storing item ID: " . $item_id);
        }

        // Remove all existing items
        foreach ($items as $item_id => $item) {
            $order->remove_item($item_id);
            error_log("Removed item ID: " . $item_id);
        }

        // Add items back in the new order
        foreach ($new_order as $item_id) {
            if (isset($items_array[$item_id])) {
                $order->add_item($items_array[$item_id]);
                error_log("Added item ID: " . $item_id);
            } else {
                error_log("Item ID not found in items_array: " . $item_id);
            }
        }

        // Save the order
        $order->calculate_totals();
        $order->save();
        error_log("Order totals calculated and saved.");

        return true;
    }


}

// Initialize the class
new AlarndPI();