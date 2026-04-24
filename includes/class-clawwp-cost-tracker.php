<?php
/**
 * Token usage and cost tracking.
 *
 * Tracks API usage per user, calculates estimated costs,
 * and triggers budget alerts. Directly addresses OpenClaw's
 * biggest user complaint: surprise $300-700/month bills.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Cost_Tracker {

    /**
     * Record token usage for an API call.
     *
     * @param int    $user_id
     * @param string $model      Model ID used.
     * @param int    $tokens_in  Input tokens consumed.
     * @param int    $tokens_out Output tokens generated.
     */
    public function record( $user_id, $model, $tokens_in, $tokens_out ) {
        global $wpdb;

        $cost = $this->estimate_cost( $model, $tokens_in, $tokens_out );

        $wpdb->insert(
            $wpdb->prefix . 'clawwp_usage',
            array(
                'user_id'        => $user_id,
                'model'          => $model,
                'tokens_in'      => $tokens_in,
                'tokens_out'     => $tokens_out,
                'estimated_cost' => $cost,
                'created_at'     => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%d', '%d', '%f', '%s' )
        );
    }

    /**
     * Estimate cost for a given model and token count.
     *
     * @param string $model
     * @param int    $tokens_in
     * @param int    $tokens_out
     * @return float Estimated cost in USD.
     */
    public function estimate_cost( $model, $tokens_in, $tokens_out ) {
        $pricing = $this->get_model_pricing( $model );
        $input_cost  = ( $tokens_in / 1000000 ) * $pricing['input'];
        $output_cost = ( $tokens_out / 1000000 ) * $pricing['output'];
        return round( $input_cost + $output_cost, 6 );
    }

    /**
     * Get usage summary for a user within a date range.
     *
     * @param int    $user_id
     * @param string $period  'today', 'week', 'month', or 'all'.
     * @return array{total_tokens_in: int, total_tokens_out: int, total_cost: float, request_count: int}
     */
    public function get_usage_summary( $user_id, $period = 'month' ) {
        global $wpdb;

        list( $date_clause, $date_value ) = $this->get_date_condition( $period );
        $args = array( $user_id );
        if ( null !== $date_value ) {
            $args[] = $date_value;
        }

        $result = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COALESCE(SUM(tokens_in), 0) as total_tokens_in,
                COALESCE(SUM(tokens_out), 0) as total_tokens_out,
                COALESCE(SUM(estimated_cost), 0) as total_cost,
                COUNT(*) as request_count
             FROM {$wpdb->prefix}clawwp_usage
             WHERE user_id = %d {$date_clause}",
            $args
        ), ARRAY_A );

        return array(
            'total_tokens_in'  => (int) $result['total_tokens_in'],
            'total_tokens_out' => (int) $result['total_tokens_out'],
            'total_cost'       => (float) $result['total_cost'],
            'request_count'    => (int) $result['request_count'],
        );
    }

    /**
     * Get daily usage breakdown for charts.
     *
     * @param int $user_id
     * @param int $days Number of days to look back.
     * @return array
     */
    public function get_daily_breakdown( $user_id, $days = 30 ) {
        global $wpdb;

        // Clamp days to a safe integer range.
        $days = max( 1, min( (int) $days, 365 ) );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT
                DATE(created_at) as date,
                SUM(tokens_in) as tokens_in,
                SUM(tokens_out) as tokens_out,
                SUM(estimated_cost) as cost,
                COUNT(*) as requests
             FROM {$wpdb->prefix}clawwp_usage
             WHERE user_id = %d AND created_at >= %s
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $user_id,
            gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) )
        ), ARRAY_A );
    }

    /**
     * Get usage breakdown by model.
     *
     * @param int    $user_id
     * @param string $period
     * @return array
     */
    public function get_model_breakdown( $user_id, $period = 'month' ) {
        global $wpdb;

        list( $date_clause, $date_value ) = $this->get_date_condition( $period );
        $args = array( $user_id );
        if ( null !== $date_value ) {
            $args[] = $date_value;
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT
                model,
                SUM(tokens_in) as tokens_in,
                SUM(tokens_out) as tokens_out,
                SUM(estimated_cost) as cost,
                COUNT(*) as requests
             FROM {$wpdb->prefix}clawwp_usage
             WHERE user_id = %d {$date_clause}
             GROUP BY model
             ORDER BY cost DESC",
            $args
        ), ARRAY_A );
    }

    /**
     * Check budget alerts for all users.
     */
    public function check_budget_alerts() {
        $budget = (float) ClawWP::get_option( 'monthly_budget', 0 );
        if ( $budget <= 0 ) {
            return;
        }

        // Get all users with usage this month.
        global $wpdb;
        $month_start = gmdate( 'Y-m-01 00:00:00' );

        $users = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, SUM(estimated_cost) as total_cost
             FROM {$wpdb->prefix}clawwp_usage
             WHERE created_at >= %s
             GROUP BY user_id",
            $month_start
        ), ARRAY_A );

        foreach ( $users as $user_data ) {
            $user_id    = (int) $user_data['user_id'];
            $total_cost = (float) $user_data['total_cost'];
            $percentage = ( $total_cost / $budget ) * 100;

            // Alert at 80% and 100%.
            if ( $percentage >= 100 ) {
                $this->send_budget_alert( $user_id, $total_cost, $budget, 'exceeded' );
            } elseif ( $percentage >= 80 ) {
                $already_warned = get_user_meta( $user_id, 'clawwp_budget_warned_month', true );
                if ( $already_warned !== gmdate( 'Y-m' ) ) {
                    $this->send_budget_alert( $user_id, $total_cost, $budget, 'warning' );
                    update_user_meta( $user_id, 'clawwp_budget_warned_month', gmdate( 'Y-m' ) );
                }
            }
        }
    }

    /**
     * Send a budget alert notification.
     */
    private function send_budget_alert( $user_id, $current_cost, $budget, $type ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $subject = 'warning' === $type
            ? sprintf( '[ClawWP] Budget Warning: 80%% of $%.2f monthly limit reached', $budget )
            : sprintf( '[ClawWP] Budget Exceeded: $%.2f spent of $%.2f monthly limit', $current_cost, $budget );

        $message = sprintf(
            "Hi %s,\n\nYour ClawWP AI usage this month has %s your budget.\n\nCurrent spend: $%.2f\nMonthly budget: $%.2f\n\nYou can adjust your budget in WordPress admin under ClawWP > Settings.\n\n— ClawWP by HiFriendbot",
            $user->display_name,
            'warning' === $type ? 'reached 80% of' : 'exceeded',
            $current_cost,
            $budget
        );

        wp_mail( $user->user_email, $subject, $message );

        ClawWP::audit_log( $user_id, 'budget_alert', array(
            'type'   => $type,
            'cost'   => $current_cost,
            'budget' => $budget,
        ) );
    }

    /**
     * Get pricing for a model.
     *
     * @param string $model
     * @return array{input: float, output: float} Price per million tokens.
     */
    private function get_model_pricing( $model ) {
        // Claude models.
        if ( isset( ClawWP_AI_Claude::MODELS[ $model ] ) ) {
            $m = ClawWP_AI_Claude::MODELS[ $model ];
            return array( 'input' => $m['input'], 'output' => $m['output'] );
        }

        // OpenAI models (approximate pricing).
        $openai_pricing = array(
            'gpt-4o'      => array( 'input' => 2.50, 'output' => 10.00 ),
            'gpt-4o-mini' => array( 'input' => 0.15, 'output' => 0.60 ),
            'gpt-4-turbo' => array( 'input' => 10.00, 'output' => 30.00 ),
        );

        if ( isset( $openai_pricing[ $model ] ) ) {
            return $openai_pricing[ $model ];
        }

        // Default fallback.
        return array( 'input' => 3.00, 'output' => 15.00 );
    }

    /**
     * Get the SQL clause and bind value for a date period filter.
     *
     * Returns an array with the clause string and an optional value,
     * designed to be merged into the caller's $wpdb->prepare() args.
     *
     * @param string $period 'today', 'week', 'month', or 'all'.
     * @return array{string, string|null} [ clause, value ].
     */
    private function get_date_condition( $period ) {
        switch ( $period ) {
            case 'today':
                return array( 'AND created_at >= %s', gmdate( 'Y-m-d 00:00:00' ) );
            case 'week':
                return array( 'AND created_at >= %s', gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) ) );
            case 'month':
                return array( 'AND created_at >= %s', gmdate( 'Y-m-01 00:00:00' ) );
            case 'all':
            default:
                return array( '', null );
        }
    }
}
