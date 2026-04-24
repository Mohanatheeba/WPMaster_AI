<?php
/**
 * Tool registry and base class.
 *
 * Discovers, registers, and provides tool definitions
 * to the agent engine for AI tool-use.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Abstract base class for all tools.
 */
abstract class ClawWP_Tool {

    /**
     * Get the tool name (used as function name in AI API calls).
     *
     * @return string
     */
    abstract public function get_name();

    /**
     * Get a human-readable description of what this tool does.
     *
     * @return string
     */
    abstract public function get_description();

    /**
     * Get the JSON Schema for the tool's parameters.
     *
     * @return array
     */
    abstract public function get_parameters();

    /**
     * Execute the tool with the given parameters.
     *
     * @param array $params Validated parameters.
     * @return mixed Result to send back to the AI.
     */
    abstract public function execute( array $params );

    /**
     * Get the WordPress capability required to use this tool.
     *
     * @return string
     */
    abstract public function get_required_capability();

    /**
     * Whether this tool requires Pro license.
     *
     * @return bool
     */
    public function is_pro() {
        return false;
    }

    /**
     * Get the tool definition for the AI API.
     *
     * @return array
     */
    public function get_definition() {
        return array(
            'name'        => $this->get_name(),
            'description' => $this->get_description(),
            'parameters'  => $this->get_parameters(),
        );
    }
}

/**
 * Tool registry — manages all available tools.
 */
class ClawWP_Tools {

    /** @var ClawWP_Tool[] */
    private $tools = array();

    public function __construct() {
        $this->register_core_tools();

        /**
         * Allow Pro and third-party tools to register.
         *
         * @param ClawWP_Tools $registry The tool registry instance.
         */
        do_action( 'clawwp_register_tools', $this );
    }

    /**
     * Register all built-in core tools.
     */
    private function register_core_tools() {
        $tool_classes = array(
            'ClawWP_Tool_Posts',
            'ClawWP_Tool_Pages',
            'ClawWP_Tool_Comments',
            'ClawWP_Tool_Media',
            'ClawWP_Tool_SEO',
            'ClawWP_Tool_Site_Info',
            'ClawWP_Tool_WooCommerce',
            'ClawWP_Tool_Wallet',
        );

        foreach ( $tool_classes as $class ) {
            if ( class_exists( $class ) ) {
                $this->register( new $class() );
            }
        }

        // Register MCP tools from connected servers.
        // Built-in servers (e.g. GuessMarket) are available to all users.
        // Custom user-added MCP servers require Pro.
        $this->register_mcp_tools();
    }

    /**
     * Register tools discovered from MCP servers.
     */
    private function register_mcp_tools() {
        $registry = new ClawWP_MCP_Registry();
        $is_pro   = ClawWP_License::is_pro();

        foreach ( $registry->get_all_tools() as $server_id => $server_tools ) {
            $server = $registry->get_server( $server_id );
            if ( ! $server ) {
                continue;
            }

            // Custom (non-built-in) MCP servers require Pro.
            if ( ! $registry->is_builtin( $server_id ) && ! $is_pro ) {
                continue;
            }

            foreach ( $server_tools as $tool_def ) {
                $this->register( new ClawWP_MCP_Tool( $server, $tool_def ) );
            }
        }
    }

    /**
     * Register a tool.
     *
     * @param ClawWP_Tool $tool
     */
    public function register( ClawWP_Tool $tool ) {
        $this->tools[ $tool->get_name() ] = $tool;
    }

    /**
     * Get a tool by name.
     *
     * @param string $name
     * @return ClawWP_Tool|null
     */
    public function get_tool( $name ) {
        return $this->tools[ $name ] ?? null;
    }

    /**
     * Get all registered tools.
     *
     * @return ClawWP_Tool[]
     */
    public function get_all() {
        return $this->tools;
    }

    /**
     * Get tool definitions for a specific user (filtered by capabilities and tier).
     *
     * @param int $user_id WordPress user ID.
     * @return array Tool definitions for the AI API.
     */
    public function get_definitions_for_user( $user_id ) {
        $definitions = array();
        $is_pro      = ClawWP_License::is_pro();

        foreach ( $this->tools as $tool ) {
            // Skip Pro tools if not licensed.
            if ( $tool->is_pro() && ! $is_pro ) {
                continue;
            }

            // Only include tools the user has permission for.
            if ( user_can( $user_id, $tool->get_required_capability() ) ) {
                $definitions[] = $tool->get_definition();
            }
        }

        return $definitions;
    }

    /**
     * Get a list of tool names for display.
     *
     * @return array
     */
    public function get_tool_names() {
        return array_keys( $this->tools );
    }
}
