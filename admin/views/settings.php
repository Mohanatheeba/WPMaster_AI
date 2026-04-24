<?php
/**
 * Settings page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$api_key         = ClawWP::get_option( 'anthropic_api_key' );
$has_api_key     = ! empty( $api_key );
$claude_model    = ClawWP::get_option( 'claude_model', 'claude-sonnet-4-5-20250929' );
$ai_provider     = ClawWP::get_option( 'ai_provider', 'claude' );
$openai_key      = ClawWP::get_option( 'openai_api_key' );
$has_openai_key  = ! empty( $openai_key );
$openai_model    = ClawWP::get_option( 'openai_model', 'gpt-4o' );
$telegram_token  = ClawWP::get_option( 'telegram_bot_token' );
$has_telegram    = ! empty( $telegram_token );
$slack_token     = ClawWP::get_option( 'slack_bot_token' );
$has_slack       = ! empty( $slack_token );
$slack_secret    = ClawWP::get_option( 'slack_signing_secret' );
$has_slack_secret = ! empty( $slack_secret );
$discord_token   = ClawWP::get_option( 'discord_bot_token' );
$has_discord     = ! empty( $discord_token );
$discord_app_id  = ClawWP::get_option( 'discord_application_id' );
$discord_pubkey  = ClawWP::get_option( 'discord_public_key' );
$mcp_registry    = new ClawWP_MCP_Registry();
$mcp_servers     = $mcp_registry->get_servers();
$aw_api_url      = ClawWP::get_option( 'agentwallet_api_url', 'https://hifriendbot.com/wp-json/agentwallet/v1/' );
$aw_username     = ClawWP::get_option( 'agentwallet_username' );
$aw_api_key      = ClawWP::get_option( 'agentwallet_api_key' );
$has_aw_key      = ! empty( $aw_api_key );
$gm_server       = $mcp_registry->get_server( 'guessmarket' );
$gm_enabled      = $gm_server ? ! empty( $gm_server['enabled'] ) : true;
$gm_tool_count   = $gm_server && ! empty( $gm_server['tools'] ) ? count( $gm_server['tools'] ) : 0;
$monthly_budget  = ClawWP::get_option( 'monthly_budget', 0 );
$sidebar_enabled = ClawWP::get_option( 'sidebar_enabled', true );

$permissions = new ClawWP_Permissions();
$pairings    = $permissions->get_user_pairings( get_current_user_id() );

$license_status = ClawWP_License::get_status();
$is_pro         = ClawWP_License::is_pro();
?>
<div class="clawwp-wrap">
    <div class="clawwp-page-header">
        <div class="clawwp-page-header-top">
            <img src="<?php echo esc_url( CLAWWP_PLUGIN_URL . 'assets/images/icon.svg' ); ?>" alt="ClawWP" class="clawwp-page-header-logo" />
            <span class="clawwp-page-header-brand">ClawWP</span>
        </div>
        <h1><?php esc_html_e( 'Settings', 'clawwp' ); ?></h1>
        <p><?php esc_html_e( 'Configure your AI agent, channels, and preferences', 'clawwp' ); ?></p>
    </div>

    <div class="clawwp-page-content">
        <form method="post" action="options.php">
            <?php settings_fields( 'clawwp_settings' ); ?>

            <!-- Pro License -->
            <div class="clawwp-section">
                <h2 class="clawwp-section-title"><?php esc_html_e( 'Pro License', 'clawwp' ); ?></h2>
                <div class="clawwp-form-card">
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'License Key', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <?php if ( $is_pro ) : ?>
                                <div class="clawwp-form-field-row">
                                    <input type="text"
                                           class="clawwp-input"
                                           value="<?php echo esc_attr( $license_status['key_masked'] ); ?>"
                                           disabled />
                                    <span class="clawwp-status clawwp-status--ok"><?php esc_html_e( 'Active', 'clawwp' ); ?></span>
                                </div>
                                <div class="clawwp-license-details" style="margin-top: 8px;">
                                    <?php if ( ! empty( $license_status['tier'] ) ) : ?>
                                        <span class="clawwp-badge clawwp-badge--channel"><?php echo esc_html( ucfirst( $license_status['tier'] ) ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $license_status['expires_at'] ) ) : ?>
                                        <span class="clawwp-text-muted" style="margin-left: 8px;">
                                            <?php printf( esc_html__( 'Expires: %s', 'clawwp' ), esc_html( $license_status['expires_at'] ) ); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div style="margin-top: 12px;">
                                    <button type="button" id="clawwp_license_deactivate" class="clawwp-btn clawwp-btn-danger clawwp-btn-sm">
                                        <?php esc_html_e( 'Deactivate License', 'clawwp' ); ?>
                                    </button>
                                </div>
                            <?php else : ?>
                                <div class="clawwp-form-field-row">
                                    <input type="text"
                                           id="clawwp_license_key"
                                           class="clawwp-input"
                                           placeholder="XXXX-XXXX-XXXX-XXXX"
                                           autocomplete="off" />
                                    <button type="button" id="clawwp_license_activate" class="clawwp-btn clawwp-btn-primary">
                                        <?php esc_html_e( 'Activate', 'clawwp' ); ?>
                                    </button>
                                </div>
                                <p class="clawwp-form-help">
                                    <?php printf(
                                        esc_html__( 'Get a Pro license at %s to unlock WooCommerce tools, Telegram/Slack/Discord, Cognitive Memory, and more.', 'clawwp' ),
                                        '<a href="https://hifriendbot.com/clawwp" target="_blank" rel="noopener">hifriendbot.com/clawwp</a>'
                                    ); ?>
                                </p>
                            <?php endif; ?>
                            <div id="clawwp_license_result" class="clawwp-pair-result"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Provider -->
            <div class="clawwp-section">
                <h2 class="clawwp-section-title"><?php esc_html_e( 'AI Provider', 'clawwp' ); ?></h2>
                <div class="clawwp-form-card">
                    <?php if ( $is_pro ) : ?>
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Provider', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <select id="clawwp_ai_provider" name="clawwp_ai_provider" class="clawwp-select">
                                <option value="proxy" <?php selected( $ai_provider, 'proxy' ); ?>><?php esc_html_e( 'HFB Proxy (Recommended)', 'clawwp' ); ?></option>
                                <option value="claude" <?php selected( $ai_provider, 'claude' ); ?>><?php esc_html_e( 'Anthropic Claude', 'clawwp' ); ?></option>
                                <option value="openai" <?php selected( $ai_provider, 'openai' ); ?>><?php esc_html_e( 'OpenAI', 'clawwp' ); ?></option>
                            </select>
                            <p class="clawwp-form-help" id="clawwp_provider_help_proxy" style="<?php echo esc_attr( 'proxy' !== $ai_provider ? 'display:none;' : '' ); ?>">
                                <?php esc_html_e( 'No API key needed. Requests routed through HiFriendbot.com (500K tokens/month included with Pro).', 'clawwp' ); ?>
                            </p>
                            <p class="clawwp-form-help" id="clawwp_provider_help_default" style="<?php echo esc_attr( 'proxy' === $ai_provider ? 'display:none;' : '' ); ?>">
                                <?php esc_html_e( 'Choose your preferred AI provider. Claude is recommended for tool use.', 'clawwp' ); ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div id="clawwp_claude_fields" style="<?php echo esc_attr( 'proxy' === $ai_provider ? 'display:none;' : '' ); ?>">
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Anthropic API Key', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <div class="clawwp-form-field-row">
                                <input type="password"
                                       id="clawwp_anthropic_api_key"
                                       name="clawwp_anthropic_api_key"
                                       value="<?php echo esc_attr( $has_api_key ? '••••••••••••••••' : '' ); ?>"
                                       class="clawwp-input"
                                       placeholder="sk-ant-..."
                                       autocomplete="off" />
                                <?php if ( $has_api_key ) : ?>
                                    <span class="clawwp-status clawwp-status--ok"><?php esc_html_e( 'Connected', 'clawwp' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="clawwp-form-help">
                                <?php printf(
                                    esc_html__( 'Get your API key from %s. Keys are stored encrypted.', 'clawwp' ),
                                    '<a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com</a>'
                                ); ?>
                            </p>
                        </div>
                    </div>

                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Claude Model', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <select id="clawwp_claude_model" name="clawwp_claude_model" class="clawwp-select">
                                <?php foreach ( ClawWP_AI_Claude::MODELS as $model_id => $model_info ) : ?>
                                    <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $claude_model, $model_id ); ?>>
                                        <?php echo esc_html( $model_info['name'] ); ?>
                                        (<?php echo esc_html( sprintf( '$%.2f / $%.2f per 1M tokens', $model_info['input'], $model_info['output'] ) ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="clawwp-form-help"><?php esc_html_e( 'Sonnet 4.5 offers the best balance of speed, quality, and cost.', 'clawwp' ); ?></p>
                        </div>
                    </div>
                    </div>

                    <?php if ( $is_pro ) : ?>
                    <div id="clawwp_openai_fields" style="<?php echo esc_attr( 'openai' !== $ai_provider ? 'display:none;' : '' ); ?>">
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'OpenAI API Key', 'clawwp' ); ?> <span class="clawwp-badge clawwp-badge--channel">Pro</span></div>
                        <div class="clawwp-form-field">
                            <div class="clawwp-form-field-row">
                                <input type="password"
                                       id="clawwp_openai_api_key"
                                       name="clawwp_openai_api_key"
                                       value="<?php echo esc_attr( $has_openai_key ? '••••••••••••••••' : '' ); ?>"
                                       class="clawwp-input"
                                       placeholder="sk-..."
                                       autocomplete="off" />
                                <?php if ( $has_openai_key ) : ?>
                                    <span class="clawwp-status clawwp-status--ok"><?php esc_html_e( 'Connected', 'clawwp' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="clawwp-form-help">
                                <?php printf(
                                    esc_html__( 'Get your API key from %s. Only needed if using OpenAI as your provider.', 'clawwp' ),
                                    '<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com</a>'
                                ); ?>
                            </p>
                        </div>
                    </div>

                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'OpenAI Model', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <select id="clawwp_openai_model" name="clawwp_openai_model" class="clawwp-select">
                                <?php foreach ( ClawWP_AI_OpenAI::MODELS as $model_id => $model_info ) : ?>
                                    <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $openai_model, $model_id ); ?>>
                                        <?php echo esc_html( $model_info['name'] ); ?>
                                        (<?php echo esc_html( sprintf( '$%.2f / $%.2f per 1M tokens', $model_info['input'], $model_info['output'] ) ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Telegram -->
            <div class="clawwp-section">
                <h2 class="clawwp-section-title"><?php esc_html_e( 'Telegram', 'clawwp' ); ?></h2>
                <div class="clawwp-form-card">
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Bot Token', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <div class="clawwp-form-field-row">
                                <input type="password"
                                       id="clawwp_telegram_bot_token"
                                       name="clawwp_telegram_bot_token"
                                       value="<?php echo esc_attr( $has_telegram ? '••••••••••••••••' : '' ); ?>"
                                       class="clawwp-input"
                                       placeholder="123456789:ABCdefGHIjklMNOpqrSTUvwxYZ"
                                       autocomplete="off" />
                                <?php if ( $has_telegram ) : ?>
                                    <span class="clawwp-status clawwp-status--ok"><?php esc_html_e( 'Connected', 'clawwp' ); ?></span>
                                <?php else : ?>
                                    <span class="clawwp-status clawwp-status--info"><?php esc_html_e( 'Optional', 'clawwp' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="clawwp-form-help"><?php esc_html_e( 'Create a bot with @BotFather on Telegram, then paste the token here.', 'clawwp' ); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slack (Pro) -->
            <?php if ( $is_pro ) : ?>
            <div class="clawwp-section">
                <h2 class="clawwp-section-title"><?php esc_html_e( 'Slack', 'clawwp' ); ?> <span class="clawwp-badge clawwp-badge--channel">Pro</span></h2>
                <div class="clawwp-form-card">
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Bot Token', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <div class="clawwp-form-field-row">
                                <input type="password"
                                       id="clawwp_slack_bot_token"
                                       name="clawwp_slack_bot_token"
                                       value="<?php echo esc_attr( $has_slack ? '••••••••••••••••' : '' ); ?>"
                                       class="clawwp-input"
                                       placeholder="xoxb-..."
                                       autocomplete="off" />
                                <?php if ( $has_slack ) : ?>
                                    <span class="clawwp-status clawwp-status--ok"><?php esc_html_e( 'Connected', 'clawwp' ); ?></span>
                                <?php else : ?>
                                    <span class="clawwp-status clawwp-status--info"><?php esc_html_e( 'Optional', 'clawwp' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="clawwp-form-help">
                                <?php printf(
                                    esc_html__( 'Create a Slack app at %s, install it to your workspace, and paste the Bot User OAuth Token here.', 'clawwp' ),
                                    '<a href="https://api.slack.com/apps" target="_blank" rel="noopener">api.slack.com/apps</a>'
                                ); ?>
                            </p>
                        </div>
                    </div>
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Signing Secret', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <div class="clawwp-form-field-row">
                                <input type="password"
                                       id="clawwp_slack_signing_secret"
                                       name="clawwp_slack_signing_secret"
                                       value="<?php echo esc_attr( $has_slack_secret ? '••••••••••••••••' : '' ); ?>"
                                       class="clawwp-input"
                                       placeholder="Signing Secret from Basic Information"
                                       autocomplete="off" />
                                <?php if ( $has_slack_secret ) : ?>
                                    <span class="clawwp-status clawwp-status--ok"><?php esc_html_e( 'Set', 'clawwp' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="clawwp-form-help"><?php esc_html_e( 'Found under your Slack app\'s Basic Information > Signing Secret. Used to verify incoming webhooks.', 'clawwp' ); ?></p>
                        </div>
                    </div>
                    <?php if ( $has_slack ) : ?>
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Webhook URL', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <code class="clawwp-code-block"><?php echo esc_url( rest_url( 'clawwp/v1/slack' ) ); ?></code>
                            <p class="clawwp-form-help"><?php esc_html_e( 'Enter this URL in your Slack app\'s Event Subscriptions > Request URL.', 'clawwp' ); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Discord (Pro) -->
            <?php if ( $is_pro ) : ?>
            <div class="clawwp-section">
                <h2 class="clawwp-section-title"><?php esc_html_e( 'Discord', 'clawwp' ); ?> <span class="clawwp-badge clawwp-badge--channel">Pro</span></h2>
                <div class="clawwp-form-card">
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Bot Token', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <div class="clawwp-form-field-row">
                                <input type="password"
                                       id="clawwp_discord_bot_token"
                                       name="clawwp_discord_bot_token"
                                       value="<?php echo esc_attr( $has_discord ? '••••••••••••••••' : '' ); ?>"
                                       class="clawwp-input"
                                       placeholder="Discord bot token"
                                       autocomplete="off" />
                                <?php if ( $has_discord ) : ?>
                                    <span class="clawwp-status clawwp-status--ok"><?php esc_html_e( 'Connected', 'clawwp' ); ?></span>
                                <?php else : ?>
                                    <span class="clawwp-status clawwp-status--info"><?php esc_html_e( 'Optional', 'clawwp' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="clawwp-form-help">
                                <?php printf(
                                    esc_html__( 'Create a Discord application at %s, add a bot, and paste the token here.', 'clawwp' ),
                                    '<a href="https://discord.com/developers/applications" target="_blank" rel="noopener">discord.com/developers</a>'
                                ); ?>
                            </p>
                        </div>
                    </div>
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Application ID', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <input type="text"
                                   id="clawwp_discord_application_id"
                                   name="clawwp_discord_application_id"
                                   value="<?php echo esc_attr( $discord_app_id ); ?>"
                                   class="clawwp-input"
                                   placeholder="e.g. 123456789012345678" />
                            <p class="clawwp-form-help"><?php esc_html_e( 'Found on your Discord application\'s General Information page.', 'clawwp' ); ?></p>
                        </div>
                    </div>
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Public Key', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <input type="text"
                                   id="clawwp_discord_public_key"
                                   name="clawwp_discord_public_key"
                                   value="<?php echo esc_attr( $discord_pubkey ); ?>"
                                   class="clawwp-input"
                                   placeholder="Ed25519 public key (hex)" />
                            <p class="clawwp-form-help"><?php esc_html_e( 'Found on your Discord application\'s General Information page. Used for webhook signature verification.', 'clawwp' ); ?></p>
                        </div>
                    </div>
                    <?php if ( $has_discord ) : ?>
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Interactions URL', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <code class="clawwp-code-block"><?php echo esc_url( rest_url( 'clawwp/v1/discord' ) ); ?></code>
                            <p class="clawwp-form-help"><?php esc_html_e( 'Enter this URL in your Discord application\'s General Information > Interactions Endpoint URL.', 'clawwp' ); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- GuessMarket -->
            <div class="clawwp-section">
                <h2 class="clawwp-section-title"><?php esc_html_e( 'GuessMarket', 'clawwp' ); ?></h2>

                <div class="clawwp-form-card">
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Prediction Markets', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <div class="clawwp-toggle-row">
                                <label class="clawwp-toggle">
                                    <input type="checkbox" id="clawwp_gm_enabled" <?php checked( $gm_enabled ); ?> />
                                    <span class="clawwp-toggle-track"></span>
                                </label>
                                <span class="clawwp-toggle-label"><?php esc_html_e( 'Enable GuessMarket prediction market tools', 'clawwp' ); ?></span>
                            </div>
                            <p class="clawwp-form-help">
                                <?php printf(
                                    esc_html__( 'Adds tools for browsing markets, checking odds, and placing bets on %s prediction markets. No API key required.', 'clawwp' ),
                                    '<a href="https://guessmarket.com" target="_blank" rel="noopener">GuessMarket</a>'
                                ); ?>
                            </p>
                            <?php if ( $gm_tool_count > 0 ) : ?>
                                <span class="clawwp-badge clawwp-badge--channel"><?php echo esc_html( (int) $gm_tool_count ); ?> tools discovered</span>
                                <button type="button" id="clawwp_gm_rediscover" class="clawwp-btn clawwp-btn-primary clawwp-btn-sm" style="margin-left: 8px;">
                                    <?php esc_html_e( 'Rediscover', 'clawwp' ); ?>
                                </button>
                            <?php elseif ( $gm_enabled ) : ?>
                                <button type="button" id="clawwp_gm_discover" class="clawwp-btn clawwp-btn-primary clawwp-btn-sm">
                                    <?php esc_html_e( 'Discover Tools', 'clawwp' ); ?>
                                </button>
                            <?php endif; ?>
                            <div id="clawwp_gm_result" class="clawwp-pair-result"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AgentWallet (Pro) -->
            <?php if ( $is_pro ) : ?>
            <div class="clawwp-section">
                <h2 class="clawwp-section-title"><?php esc_html_e( 'AgentWallet', 'clawwp' ); ?> <span class="clawwp-badge clawwp-badge--channel">Pro</span></h2>

                <div class="clawwp-form-card">
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'API URL', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <input type="text"
                                   id="clawwp_agentwallet_api_url"
                                   name="clawwp_agentwallet_api_url"
                                   value="<?php echo esc_attr( $aw_api_url ); ?>"
                                   class="clawwp-input"
                                   placeholder="https://hifriendbot.com/wp-json/agentwallet/v1/" />
                            <p class="clawwp-form-help"><?php esc_html_e( 'Default: https://hifriendbot.com/wp-json/agentwallet/v1/', 'clawwp' ); ?></p>
                        </div>
                    </div>
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Username', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <input type="text"
                                   id="clawwp_agentwallet_username"
                                   name="clawwp_agentwallet_username"
                                   value="<?php echo esc_attr( $aw_username ); ?>"
                                   class="clawwp-input"
                                   placeholder="Your AgentWallet username"
                                   autocomplete="off" />
                        </div>
                    </div>
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'API Key', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <div class="clawwp-form-field-row">
                                <input type="password"
                                       id="clawwp_agentwallet_api_key"
                                       name="clawwp_agentwallet_api_key"
                                       value="<?php echo esc_attr( $has_aw_key ? '••••••••••••••••' : '' ); ?>"
                                       class="clawwp-input"
                                       placeholder="Your AgentWallet API key"
                                       autocomplete="off" />
                                <?php if ( $has_aw_key ) : ?>
                                    <span class="clawwp-status clawwp-status--ok"><?php esc_html_e( 'Connected', 'clawwp' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="clawwp-form-help">
                                <?php printf(
                                    esc_html__( 'Get your free API key at %s. 6,000 free transactions per month.', 'clawwp' ),
                                    '<a href="https://hifriendbot.com/wallet/" target="_blank" rel="noopener">hifriendbot.com/wallet</a>'
                                ); ?>
                            </p>
                        </div>
                    </div>
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Test Connection', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <button type="button" id="clawwp_aw_test" class="clawwp-btn clawwp-btn-primary clawwp-btn-sm">
                                <?php esc_html_e( 'Test Connection', 'clawwp' ); ?>
                            </button>
                            <div id="clawwp_aw_test_result" class="clawwp-pair-result"></div>
                        </div>
                    </div>
                    <p style="margin: 16px 0 0; padding-top: 12px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 13px;">
                        <?php esc_html_e( 'GuessMarket tools prepare transactions. AgentWallet signs and broadcasts them. Both are needed for placing bets.', 'clawwp' ); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Custom MCP Servers -->
            <div class="clawwp-section">
                <h2 class="clawwp-section-title"><?php esc_html_e( 'Custom MCP Servers', 'clawwp' ); ?></h2>

                <?php
                // Filter out built-in servers from the table.
                $custom_servers = array_filter( $mcp_servers, function( $srv ) { return empty( $srv['builtin'] ); } );
                ?>
                <?php if ( ! empty( $custom_servers ) ) : ?>
                <div class="clawwp-table-wrap" style="margin-bottom: 20px;">
                    <table class="clawwp-table" id="clawwp_mcp_table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Name', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'Endpoint', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'Tools', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'clawwp' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $custom_servers as $srv ) : ?>
                            <tr data-server-id="<?php echo esc_attr( $srv['id'] ); ?>">
                                <td>
                                    <strong><?php echo esc_html( $srv['name'] ); ?></strong>
                                    <span class="clawwp-badge" style="margin-left: 6px; font-size: 10px;"><?php echo esc_html( strtoupper( $srv['transport'] ?? 'http' ) ); ?></span>
                                </td>
                                <td><code style="font-size: 12px;"><?php echo esc_html( 'stdio' === ( $srv['transport'] ?? 'http' ) ? ( $srv['command'] ?? '' ) : $srv['endpoint'] ); ?></code></td>
                                <td class="clawwp-mcp-tool-count">
                                    <span class="clawwp-badge clawwp-badge--channel"><?php echo esc_html( count( $srv['tools'] ?? array() ) ); ?> tools</span>
                                </td>
                                <td>
                                    <button type="button" class="clawwp-btn clawwp-btn-primary clawwp-btn-sm clawwp-mcp-discover"
                                            data-id="<?php echo esc_attr( $srv['id'] ); ?>">
                                        <?php esc_html_e( 'Rediscover', 'clawwp' ); ?>
                                    </button>
                                    <button type="button" class="clawwp-btn clawwp-btn-danger clawwp-btn-sm clawwp-mcp-remove"
                                            data-id="<?php echo esc_attr( $srv['id'] ); ?>">
                                        <?php esc_html_e( 'Remove', 'clawwp' ); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <div class="clawwp-form-card">
                    <p style="margin: 0 0 16px; color: #64748b; font-size: 13px;">
                        <?php esc_html_e( 'Connect any MCP server to add its tools to your Ai agent. Supports HTTP endpoints and local stdio commands.', 'clawwp' ); ?>
                    </p>
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Server Name', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <input type="text"
                                   id="clawwp_mcp_name"
                                   class="clawwp-input"
                                   placeholder="e.g. My MCP Server" />
                        </div>
                    </div>
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Transport', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <select id="clawwp_mcp_transport" class="clawwp-select" style="max-width: 280px;">
                                <option value="http"><?php esc_html_e( 'HTTP (Streamable HTTP endpoint)', 'clawwp' ); ?></option>
                                <option value="stdio"><?php esc_html_e( 'Stdio (local command)', 'clawwp' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="clawwp-form-row" id="clawwp_mcp_endpoint_row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Endpoint URL', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <input type="url"
                                   id="clawwp_mcp_endpoint"
                                   class="clawwp-input"
                                   placeholder="https://example.com/mcp" />
                        </div>
                    </div>
                    <div class="clawwp-form-row" id="clawwp_mcp_command_row" style="display:none;">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Command', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <input type="text"
                                   id="clawwp_mcp_command"
                                   class="clawwp-input"
                                   placeholder="npx some-mcp-server" />
                            <p class="clawwp-form-help"><?php esc_html_e( 'The shell command to run the MCP server. Requires Node.js on this server.', 'clawwp' ); ?></p>
                        </div>
                    </div>
                    <div class="clawwp-form-row" id="clawwp_mcp_auth_row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Authentication', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <select id="clawwp_mcp_auth_type" class="clawwp-select" style="max-width: 200px;">
                                <option value="none"><?php esc_html_e( 'None', 'clawwp' ); ?></option>
                                <option value="basic"><?php esc_html_e( 'Basic Auth', 'clawwp' ); ?></option>
                                <option value="bearer"><?php esc_html_e( 'Bearer Token', 'clawwp' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="clawwp-form-row" id="clawwp_mcp_creds_row" style="display:none;">
                        <div class="clawwp-form-label" id="clawwp_mcp_creds_label"><?php esc_html_e( 'Credentials', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <input type="password"
                                   id="clawwp_mcp_credentials"
                                   class="clawwp-input"
                                   placeholder=""
                                   autocomplete="off" />
                            <p class="clawwp-form-help" id="clawwp_mcp_creds_help"></p>
                        </div>
                    </div>
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"></div>
                        <div class="clawwp-form-field">
                            <button type="button" id="clawwp_mcp_add" class="clawwp-btn clawwp-btn-primary">
                                <?php esc_html_e( 'Add Server', 'clawwp' ); ?>
                            </button>
                            <div id="clawwp_mcp_result" class="clawwp-pair-result"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Channel Pairings -->
            <div class="clawwp-section">
                <h2 class="clawwp-section-title"><?php esc_html_e( 'Channel Pairings', 'clawwp' ); ?></h2>

                <?php if ( ! empty( $pairings ) ) : ?>
                <div class="clawwp-table-wrap" style="margin-bottom: 20px;">
                    <table class="clawwp-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Channel', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'User ID', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'Paired', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'clawwp' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $pairings as $pairing ) : ?>
                            <tr>
                                <td><span class="clawwp-badge clawwp-badge--channel"><?php echo esc_html( ucfirst( $pairing['channel'] ) ); ?></span></td>
                                <td><code><?php echo esc_html( $pairing['channel_user_id'] ); ?></code></td>
                                <td class="clawwp-text-muted"><?php echo esc_html( human_time_diff( strtotime( $pairing['paired_at'] ) ) ); ?> ago</td>
                                <td>
                                    <button type="button" class="clawwp-btn clawwp-btn-danger clawwp-btn-sm clawwp-unpair"
                                            data-channel="<?php echo esc_attr( $pairing['channel'] ); ?>"
                                            data-user="<?php echo esc_attr( $pairing['channel_user_id'] ); ?>">
                                        <?php esc_html_e( 'Unpair', 'clawwp' ); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <div class="clawwp-form-card">
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Pairing Code', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <div class="clawwp-form-field-row">
                                <input type="text"
                                       id="clawwp_pair_code"
                                       class="clawwp-input clawwp-input--small"
                                       placeholder="123456"
                                       maxlength="6"
                                       pattern="[0-9]{6}"
                                       style="max-width: 140px; letter-spacing: 0.15em; text-align: center; font-weight: 600;" />
                                <button type="button" id="clawwp_pair_submit" class="clawwp-btn clawwp-btn-primary">
                                    <?php esc_html_e( 'Pair', 'clawwp' ); ?>
                                </button>
                            </div>
                            <p class="clawwp-form-help"><?php esc_html_e( 'Send /pair to your Telegram bot to get a code, then enter it here.', 'clawwp' ); ?></p>
                            <div id="clawwp_pair_result" class="clawwp-pair-result"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Budget & Limits -->
            <div class="clawwp-section">
                <h2 class="clawwp-section-title"><?php esc_html_e( 'Budget & Limits', 'clawwp' ); ?></h2>
                <div class="clawwp-form-card">
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Monthly Budget (USD)', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <input type="number"
                                   id="clawwp_monthly_budget"
                                   name="clawwp_monthly_budget"
                                   value="<?php echo esc_attr( $monthly_budget ); ?>"
                                   min="0"
                                   step="1"
                                   class="clawwp-input clawwp-input--small" />
                            <p class="clawwp-form-help"><?php esc_html_e( 'Set to 0 for no limit. You will receive alerts at 80% and 100% usage.', 'clawwp' ); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Interface -->
            <div class="clawwp-section">
                <h2 class="clawwp-section-title"><?php esc_html_e( 'Interface', 'clawwp' ); ?></h2>
                <div class="clawwp-form-card">
                    <div class="clawwp-form-row">
                        <div class="clawwp-form-label"><?php esc_html_e( 'Sidebar Chat', 'clawwp' ); ?></div>
                        <div class="clawwp-form-field">
                            <div class="clawwp-toggle-row">
                                <label class="clawwp-toggle">
                                    <input type="checkbox"
                                           id="clawwp_sidebar_enabled"
                                           name="clawwp_sidebar_enabled"
                                           value="1"
                                           <?php checked( $sidebar_enabled ); ?> />
                                    <span class="clawwp-toggle-track"></span>
                                </label>
                                <span class="clawwp-toggle-label"><?php esc_html_e( 'Show chat sidebar on all admin pages', 'clawwp' ); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="submit">
                <button type="submit" id="submit" class="clawwp-btn clawwp-btn-primary"><?php esc_html_e( 'Save Changes', 'clawwp' ); ?></button>
            </div>
        </form>
    </div>
</div>
<script>
(function() {
    // Escape HTML to prevent XSS from API responses.
    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    var providerSelect = document.getElementById('clawwp_ai_provider');
    if (!providerSelect) return;

    providerSelect.addEventListener('change', function() {
        var val = this.value;
        var claudeFields = document.getElementById('clawwp_claude_fields');
        var openaiFields = document.getElementById('clawwp_openai_fields');
        var helpProxy = document.getElementById('clawwp_provider_help_proxy');
        var helpDefault = document.getElementById('clawwp_provider_help_default');

        if (claudeFields) claudeFields.style.display = (val === 'proxy') ? 'none' : '';
        if (openaiFields) openaiFields.style.display = (val === 'openai') ? '' : 'none';
        if (helpProxy) helpProxy.style.display = (val === 'proxy') ? '' : 'none';
        if (helpDefault) helpDefault.style.display = (val === 'proxy') ? 'none' : '';
    });

    // AgentWallet test connection.
    var awTestBtn = document.getElementById('clawwp_aw_test');
    if (awTestBtn) {
        awTestBtn.addEventListener('click', function() {
            var result = document.getElementById('clawwp_aw_test_result');
            result.innerHTML = '<span style="color:#666;">Testing...</span>';
            awTestBtn.disabled = true;

            fetch(clawwpChat.restUrl + 'agentwallet-test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': clawwpChat.nonce
                }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    result.innerHTML = '<span style="color:#16a34a;">&#10003; Connected! ' + escHtml(data.chains || 0) + ' chains available.</span>';
                } else {
                    result.innerHTML = '<span style="color:#dc2626;">&#10007; ' + escHtml(data.error || 'Connection failed.') + '</span>';
                }
            })
            .catch(function() {
                result.innerHTML = '<span style="color:#dc2626;">&#10007; Request failed.</span>';
            })
            .finally(function() {
                awTestBtn.disabled = false;
            });
        });
    }

    // GuessMarket — enable/disable toggle.
    var gmToggle = document.getElementById('clawwp_gm_enabled');
    if (gmToggle) {
        gmToggle.addEventListener('change', function() {
            fetch(clawwpChat.restUrl + 'mcp-servers/guessmarket/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': clawwpChat.nonce
                },
                body: JSON.stringify({ enabled: this.checked })
            });
        });
    }

    // GuessMarket — discover/rediscover tools.
    ['clawwp_gm_discover', 'clawwp_gm_rediscover'].forEach(function(btnId) {
        var btn = document.getElementById(btnId);
        if (btn) {
            btn.addEventListener('click', function() {
                var result = document.getElementById('clawwp_gm_result');
                result.innerHTML = '<span style="color:#666;">Discovering tools...</span>';
                btn.disabled = true;

                fetch(clawwpChat.restUrl + 'mcp-servers/guessmarket/discover', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': clawwpChat.nonce }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        result.innerHTML = '<span style="color:#16a34a;">&#10003; Discovered ' + escHtml(data.tools) + ' tools.</span>';
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        result.innerHTML = '<span style="color:#dc2626;">&#10007; ' + escHtml(data.error || 'Discovery failed.') + '</span>';
                    }
                })
                .catch(function() {
                    result.innerHTML = '<span style="color:#dc2626;">&#10007; Request failed.</span>';
                })
                .finally(function() { btn.disabled = false; });
            });
        }
    });

    // MCP Servers — transport type toggle.
    var mcpTransportSelect = document.getElementById('clawwp_mcp_transport');
    if (mcpTransportSelect) {
        mcpTransportSelect.addEventListener('change', function() {
            var isStdio = this.value === 'stdio';
            var endpointRow = document.getElementById('clawwp_mcp_endpoint_row');
            var commandRow = document.getElementById('clawwp_mcp_command_row');
            var authRow = document.getElementById('clawwp_mcp_auth_row');
            var credsRow = document.getElementById('clawwp_mcp_creds_row');

            endpointRow.style.display = isStdio ? 'none' : '';
            commandRow.style.display = isStdio ? '' : 'none';
            authRow.style.display = isStdio ? 'none' : '';
            if (isStdio) credsRow.style.display = 'none';
        });
    }

    // MCP Servers — auth type toggle.
    var mcpAuthSelect = document.getElementById('clawwp_mcp_auth_type');
    if (mcpAuthSelect) {
        mcpAuthSelect.addEventListener('change', function() {
            var val = this.value;
            var row = document.getElementById('clawwp_mcp_creds_row');
            var label = document.getElementById('clawwp_mcp_creds_label');
            var input = document.getElementById('clawwp_mcp_credentials');
            var help = document.getElementById('clawwp_mcp_creds_help');

            if (val === 'none') {
                row.style.display = 'none';
            } else {
                row.style.display = '';
                if (val === 'basic') {
                    label.textContent = 'Username:Password';
                    input.placeholder = 'username:password';
                    help.textContent = 'Format: username:password';
                } else {
                    label.textContent = 'Bearer Token';
                    input.placeholder = 'your-api-token';
                    help.textContent = 'API token or key for the MCP server.';
                }
            }
        });
    }

    // MCP Servers — add server.
    var mcpAddBtn = document.getElementById('clawwp_mcp_add');
    if (mcpAddBtn) {
        mcpAddBtn.addEventListener('click', function() {
            var name = document.getElementById('clawwp_mcp_name').value.trim();
            var transport = document.getElementById('clawwp_mcp_transport').value;
            var endpoint = document.getElementById('clawwp_mcp_endpoint').value.trim();
            var authType = document.getElementById('clawwp_mcp_auth_type').value;
            var credentials = document.getElementById('clawwp_mcp_credentials')?.value || '';
            var command = document.getElementById('clawwp_mcp_command')?.value.trim() || '';
            var result = document.getElementById('clawwp_mcp_result');

            if (!name) {
                result.innerHTML = '<span style="color:#dc2626;">Server name is required.</span>';
                return;
            }
            if (transport === 'stdio' && !command) {
                result.innerHTML = '<span style="color:#dc2626;">Command is required for stdio transport.</span>';
                return;
            }
            if (transport === 'http' && !endpoint) {
                result.innerHTML = '<span style="color:#dc2626;">Endpoint URL is required for HTTP transport.</span>';
                return;
            }

            result.innerHTML = '<span style="color:#666;">Connecting and discovering tools...</span>';
            mcpAddBtn.disabled = true;

            fetch(clawwpChat.restUrl + 'mcp-servers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': clawwpChat.nonce
                },
                body: JSON.stringify({ name: name, transport: transport, endpoint: endpoint, auth_type: authType, credentials: credentials, command: command })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var msg = '&#10003; Added "' + escHtml(data.server.name) + '" with ' + escHtml(data.server.tools) + ' tools.';
                    if (data.discover_error) {
                        msg += ' <span style="color:#d97706;">(Discovery warning: ' + escHtml(data.discover_error) + ')</span>';
                    }
                    result.innerHTML = '<span style="color:#16a34a;">' + msg + '</span>';
                    // Reload after short delay to show the server in the table.
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    result.innerHTML = '<span style="color:#dc2626;">&#10007; ' + escHtml(data.error || 'Failed to add server.') + '</span>';
                }
            })
            .catch(function() {
                result.innerHTML = '<span style="color:#dc2626;">&#10007; Request failed.</span>';
            })
            .finally(function() {
                mcpAddBtn.disabled = false;
            });
        });
    }

    // MCP Servers — discover tools.
    document.querySelectorAll('.clawwp-mcp-discover').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.dataset.id;
            var row = this.closest('tr');
            var countCell = row.querySelector('.clawwp-mcp-tool-count');
            btn.disabled = true;
            btn.textContent = 'Discovering...';

            fetch(clawwpChat.restUrl + 'mcp-servers/' + id + '/discover', {
                method: 'POST',
                headers: { 'X-WP-Nonce': clawwpChat.nonce }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    countCell.innerHTML = '<span class="clawwp-badge clawwp-badge--channel">' + escHtml(data.tools) + ' tools</span>';
                    btn.textContent = 'Rediscover';
                } else {
                    btn.textContent = 'Failed';
                    setTimeout(function() { btn.textContent = 'Rediscover'; }, 2000);
                }
            })
            .catch(function() {
                btn.textContent = 'Error';
                setTimeout(function() { btn.textContent = 'Rediscover'; }, 2000);
            })
            .finally(function() {
                btn.disabled = false;
            });
        });
    });

    // MCP Servers — remove server.
    document.querySelectorAll('.clawwp-mcp-remove').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Remove this MCP server and all its tools?')) return;

            var id = this.dataset.id;
            var row = this.closest('tr');
            btn.disabled = true;

            fetch(clawwpChat.restUrl + 'mcp-servers/' + id, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': clawwpChat.nonce }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    row.remove();
                }
            })
            .finally(function() {
                btn.disabled = false;
            });
        });
    });
})();
</script>
