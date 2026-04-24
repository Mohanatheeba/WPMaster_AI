<?php
/**
 * WooCommerce tool (Pro) — sales, orders, products, inventory, and customers.
 *
 * Provides comprehensive WooCommerce management through the AI agent.
 * Only available when WooCommerce is active and the site has a Pro license.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Tool_WooCommerce extends ClawWP_Tool {

    public function get_name() {
        return 'manage_woocommerce';
    }

    public function get_description() {
        return 'Manage WooCommerce: view sales reports, check orders, manage products and inventory, look up customers. Requires WooCommerce to be active.';
    }

    public function get_parameters() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'action' => array(
                    'type'        => 'string',
                    'enum'        => array(
                        'sales_summary', 'recent_orders', 'order_details',
                        'list_products', 'update_product', 'low_stock',
                        'customer_lookup', 'top_products', 'revenue_by_date',
                    ),
                    'description' => 'The WooCommerce operation to perform.',
                ),
                'order_id' => array(
                    'type'        => 'integer',
                    'description' => 'Order ID (for order_details).',
                ),
                'product_id' => array(
                    'type'        => 'integer',
                    'description' => 'Product ID (for update_product).',
                ),
                'customer_email' => array(
                    'type'        => 'string',
                    'description' => 'Customer email (for customer_lookup).',
                ),
                'period' => array(
                    'type'        => 'string',
                    'enum'        => array( 'today', 'week', 'month', 'year' ),
                    'description' => 'Time period for reports. Default: month.',
                ),
                'price' => array(
                    'type'        => 'number',
                    'description' => 'New price (for update_product).',
                ),
                'stock_quantity' => array(
                    'type'        => 'integer',
                    'description' => 'New stock quantity (for update_product).',
                ),
                'stock_status' => array(
                    'type'        => 'string',
                    'enum'        => array( 'instock', 'outofstock', 'onbackorder' ),
                    'description' => 'Stock status (for update_product).',
                ),
                'sale_price' => array(
                    'type'        => 'number',
                    'description' => 'Sale price (for update_product).',
                ),
                'status_filter' => array(
                    'type'        => 'string',
                    'enum'        => array( 'any', 'processing', 'completed', 'on-hold', 'pending', 'refunded', 'cancelled' ),
                    'description' => 'Filter orders by status.',
                ),
                'search' => array(
                    'type'        => 'string',
                    'description' => 'Search query for products.',
                ),
                'limit' => array(
                    'type'        => 'integer',
                    'description' => 'Number of results (default 10, max 50).',
                ),
            ),
            'required' => array( 'action' ),
        );
    }

    public function get_required_capability() {
        return 'manage_woocommerce';
    }

    public function is_pro() {
        return true;
    }

    public function execute( array $params ) {
        // Verify WooCommerce is active.
        if ( ! class_exists( 'WooCommerce' ) ) {
            return array( 'error' => 'WooCommerce is not active on this site.' );
        }

        $action = $params['action'];

        switch ( $action ) {
            case 'sales_summary':
                return $this->sales_summary( $params );
            case 'recent_orders':
                return $this->recent_orders( $params );
            case 'order_details':
                return $this->order_details( $params );
            case 'list_products':
                return $this->list_products( $params );
            case 'update_product':
                return $this->update_product( $params );
            case 'low_stock':
                return $this->low_stock( $params );
            case 'customer_lookup':
                return $this->customer_lookup( $params );
            case 'top_products':
                return $this->top_products( $params );
            case 'revenue_by_date':
                return $this->revenue_by_date( $params );
            default:
                return array( 'error' => 'Unknown WooCommerce action.' );
        }
    }

    /**
     * Get a sales summary for a given period.
     */
    private function sales_summary( $params ) {
        $period    = $params['period'] ?? 'month';
        $date_args = $this->get_date_range( $period );

        $args = array(
            'status'     => array( 'wc-completed', 'wc-processing' ),
            'date_after' => $date_args['after'],
            'limit'      => -1,
            'return'     => 'ids',
        );

        $orders = wc_get_orders( $args );

        $total_revenue = 0;
        $total_items   = 0;
        $order_count   = count( $orders );

        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $total_revenue += (float) $order->get_total();
                $total_items   += $order->get_item_count();
            }
        }

        $avg_order = $order_count > 0 ? $total_revenue / $order_count : 0;

        return array(
            'period'           => $period,
            'total_revenue'    => round( $total_revenue, 2 ),
            'order_count'      => $order_count,
            'items_sold'       => $total_items,
            'average_order'    => round( $avg_order, 2 ),
            'currency'         => get_woocommerce_currency(),
        );
    }

    /**
     * Get recent orders.
     */
    private function recent_orders( $params ) {
        $limit  = min( (int) ( $params['limit'] ?? 10 ), 50 );
        $status = $params['status_filter'] ?? 'any';

        $args = array(
            'limit'   => $limit,
            'orderby' => 'date',
            'order'   => 'DESC',
        );

        if ( 'any' !== $status ) {
            $args['status'] = 'wc-' . $status;
        }

        $orders = wc_get_orders( $args );
        $result = array();

        foreach ( $orders as $order ) {
            $result[] = array(
                'id'         => $order->get_id(),
                'status'     => $order->get_status(),
                'total'      => $order->get_total(),
                'currency'   => $order->get_currency(),
                'items'      => $order->get_item_count(),
                'customer'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email'      => $order->get_billing_email(),
                'date'       => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i' ) : '',
                'payment'    => $order->get_payment_method_title(),
            );
        }

        return array(
            'count'  => count( $result ),
            'orders' => $result,
        );
    }

    /**
     * Get detailed order information.
     */
    private function order_details( $params ) {
        if ( empty( $params['order_id'] ) ) {
            return array( 'error' => 'order_id is required.' );
        }

        $order = wc_get_order( (int) $params['order_id'] );
        if ( ! $order ) {
            return array( 'error' => 'Order not found.' );
        }

        $items = array();
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $items[] = array(
                'name'     => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total'    => $item->get_total(),
                'sku'      => $product ? $product->get_sku() : '',
            );
        }

        return array(
            'id'              => $order->get_id(),
            'status'          => $order->get_status(),
            'total'           => $order->get_total(),
            'subtotal'        => $order->get_subtotal(),
            'tax_total'       => $order->get_total_tax(),
            'shipping_total'  => $order->get_shipping_total(),
            'discount_total'  => $order->get_discount_total(),
            'currency'        => $order->get_currency(),
            'payment_method'  => $order->get_payment_method_title(),
            'date_created'    => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i' ) : '',
            'date_completed'  => $order->get_date_completed() ? $order->get_date_completed()->date( 'Y-m-d H:i' ) : '',
            'billing'         => array(
                'name'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email'   => $order->get_billing_email(),
                'phone'   => $order->get_billing_phone(),
                'address' => $order->get_formatted_billing_address(),
            ),
            'shipping'        => array(
                'name'    => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                'address' => $order->get_formatted_shipping_address(),
            ),
            'items'           => $items,
            'customer_note'   => $order->get_customer_note(),
        );
    }

    /**
     * List products with optional search.
     */
    private function list_products( $params ) {
        $limit = min( (int) ( $params['limit'] ?? 10 ), 50 );

        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => $limit,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( ! empty( $params['search'] ) ) {
            $args['s'] = sanitize_text_field( $params['search'] );
        }

        $query    = new WP_Query( $args );
        $products = array();

        foreach ( $query->posts as $post ) {
            $product = wc_get_product( $post->ID );
            if ( ! $product ) {
                continue;
            }

            $products[] = array(
                'id'             => $product->get_id(),
                'name'           => $product->get_name(),
                'sku'            => $product->get_sku(),
                'price'          => $product->get_price(),
                'regular_price'  => $product->get_regular_price(),
                'sale_price'     => $product->get_sale_price(),
                'stock_status'   => $product->get_stock_status(),
                'stock_quantity' => $product->get_stock_quantity(),
                'type'           => $product->get_type(),
                'url'            => get_permalink( $product->get_id() ),
            );
        }

        return array(
            'total'    => $query->found_posts,
            'count'    => count( $products ),
            'products' => $products,
        );
    }

    /**
     * Update a product's price, stock, or sale price.
     */
    private function update_product( $params ) {
        if ( empty( $params['product_id'] ) ) {
            return array( 'error' => 'product_id is required.' );
        }

        $product = wc_get_product( (int) $params['product_id'] );
        if ( ! $product ) {
            return array( 'error' => 'Product not found.' );
        }

        $updated = array();

        if ( isset( $params['price'] ) ) {
            $price = max( 0, (float) $params['price'] );
            $product->set_regular_price( $price );
            $updated[] = 'price';
        }

        if ( isset( $params['sale_price'] ) ) {
            $sale_price = max( 0, (float) $params['sale_price'] );
            $product->set_sale_price( $sale_price > 0 ? $sale_price : '' );
            $updated[] = 'sale_price';
        }

        if ( isset( $params['stock_quantity'] ) ) {
            $qty = max( 0, (int) $params['stock_quantity'] );
            $product->set_stock_quantity( $qty );
            $product->set_manage_stock( true );
            $updated[] = 'stock_quantity';
        }

        if ( isset( $params['stock_status'] ) ) {
            $allowed = array( 'instock', 'outofstock', 'onbackorder' );
            if ( in_array( $params['stock_status'], $allowed, true ) ) {
                $product->set_stock_status( $params['stock_status'] );
                $updated[] = 'stock_status';
            }
        }

        if ( empty( $updated ) ) {
            return array( 'error' => 'No valid fields to update. Provide price, sale_price, stock_quantity, or stock_status.' );
        }

        $product->save();

        return array(
            'success'        => true,
            'product_id'     => $product->get_id(),
            'product_name'   => $product->get_name(),
            'updated_fields' => $updated,
            'current_price'  => $product->get_price(),
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status'   => $product->get_stock_status(),
        );
    }

    /**
     * Get products with low stock.
     */
    private function low_stock( $params ) {
        $limit     = min( (int) ( $params['limit'] ?? 20 ), 50 );
        $threshold = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );

        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => $limit,
            'post_status'    => 'publish',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_manage_stock',
                    'value'   => 'yes',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_stock',
                    'value'   => $threshold,
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ),
            ),
            'orderby'  => 'meta_value_num',
            'meta_key' => '_stock',
            'order'    => 'ASC',
        );

        $query    = new WP_Query( $args );
        $products = array();

        foreach ( $query->posts as $post ) {
            $product = wc_get_product( $post->ID );
            if ( ! $product ) {
                continue;
            }

            $products[] = array(
                'id'             => $product->get_id(),
                'name'           => $product->get_name(),
                'sku'            => $product->get_sku(),
                'stock_quantity' => $product->get_stock_quantity(),
                'stock_status'   => $product->get_stock_status(),
                'price'          => $product->get_price(),
            );
        }

        return array(
            'threshold' => $threshold,
            'count'     => count( $products ),
            'products'  => $products,
        );
    }

    /**
     * Look up a customer by email.
     */
    private function customer_lookup( $params ) {
        if ( empty( $params['customer_email'] ) ) {
            return array( 'error' => 'customer_email is required.' );
        }

        $email = sanitize_email( $params['customer_email'] );
        if ( ! is_email( $email ) ) {
            return array( 'error' => 'Invalid email address.' );
        }

        // Find orders by this email.
        $orders = wc_get_orders( array(
            'billing_email' => $email,
            'limit'         => 50,
            'orderby'       => 'date',
            'order'         => 'DESC',
        ) );

        if ( empty( $orders ) ) {
            return array( 'error' => 'No orders found for this email.' );
        }

        $total_spent  = 0;
        $order_count  = count( $orders );
        $first_order  = end( $orders );
        $last_order   = reset( $orders );
        $recent       = array();

        foreach ( $orders as $order ) {
            $total_spent += (float) $order->get_total();
        }

        // Show last 5 orders.
        foreach ( array_slice( $orders, 0, 5 ) as $order ) {
            $recent[] = array(
                'id'     => $order->get_id(),
                'date'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : '',
                'total'  => $order->get_total(),
                'status' => $order->get_status(),
            );
        }

        return array(
            'email'         => $email,
            'name'          => $last_order->get_billing_first_name() . ' ' . $last_order->get_billing_last_name(),
            'total_orders'  => $order_count,
            'total_spent'   => round( $total_spent, 2 ),
            'average_order' => round( $total_spent / $order_count, 2 ),
            'first_order'   => $first_order->get_date_created() ? $first_order->get_date_created()->date( 'Y-m-d' ) : '',
            'last_order'    => $last_order->get_date_created() ? $last_order->get_date_created()->date( 'Y-m-d' ) : '',
            'currency'      => get_woocommerce_currency(),
            'recent_orders' => $recent,
        );
    }

    /**
     * Get top-selling products.
     */
    private function top_products( $params ) {
        $limit  = min( (int) ( $params['limit'] ?? 10 ), 50 );
        $period = $params['period'] ?? 'month';
        $date   = $this->get_date_range( $period );

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT oi.order_item_name as product_name,
                    SUM(oim_qty.meta_value) as total_qty,
                    SUM(oim_total.meta_value) as total_revenue,
                    oim_pid.meta_value as product_id
             FROM {$wpdb->prefix}woocommerce_order_items oi
             INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_qty
                 ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
             INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_total
                 ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
             INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_pid
                 ON oi.order_item_id = oim_pid.order_item_id AND oim_pid.meta_key = '_product_id'
             INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
             WHERE p.post_type = 'shop_order'
               AND p.post_status IN ('wc-completed', 'wc-processing')
               AND p.post_date >= %s
             GROUP BY oim_pid.meta_value
             ORDER BY total_qty DESC
             LIMIT %d",
            $date['after'],
            $limit
        ), ARRAY_A );

        $products = array();
        foreach ( $results as $row ) {
            $products[] = array(
                'product_id'    => (int) $row['product_id'],
                'product_name'  => $row['product_name'],
                'units_sold'    => (int) $row['total_qty'],
                'total_revenue' => round( (float) $row['total_revenue'], 2 ),
            );
        }

        return array(
            'period'   => $period,
            'count'    => count( $products ),
            'products' => $products,
        );
    }

    /**
     * Get daily revenue breakdown.
     */
    private function revenue_by_date( $params ) {
        $period = $params['period'] ?? 'month';
        $date   = $this->get_date_range( $period );

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(p.post_date) as order_date,
                    COUNT(DISTINCT p.ID) as order_count,
                    SUM(pm.meta_value) as revenue
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
             WHERE p.post_type = 'shop_order'
               AND p.post_status IN ('wc-completed', 'wc-processing')
               AND p.post_date >= %s
             GROUP BY DATE(p.post_date)
             ORDER BY order_date ASC",
            $date['after']
        ), ARRAY_A );

        $days = array();
        foreach ( $results as $row ) {
            $days[] = array(
                'date'        => $row['order_date'],
                'orders'      => (int) $row['order_count'],
                'revenue'     => round( (float) $row['revenue'], 2 ),
            );
        }

        return array(
            'period'   => $period,
            'currency' => get_woocommerce_currency(),
            'days'     => $days,
        );
    }

    /**
     * Get date range for a period.
     *
     * @param string $period
     * @return array{after: string}
     */
    private function get_date_range( $period ) {
        switch ( $period ) {
            case 'today':
                return array( 'after' => gmdate( 'Y-m-d 00:00:00' ) );
            case 'week':
                return array( 'after' => gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) ) );
            case 'year':
                return array( 'after' => gmdate( 'Y-01-01 00:00:00' ) );
            case 'month':
            default:
                return array( 'after' => gmdate( 'Y-m-01 00:00:00' ) );
        }
    }
}
