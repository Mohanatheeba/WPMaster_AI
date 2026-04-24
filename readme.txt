=== ClawWP ===
Contributors: hifriendbot
Tags: ai, chatbot, agent, telegram, woocommerce
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI agent for your site. Chat from the admin sidebar or Telegram — manage posts, comments, media, and more.

== Description ==

ClawWP is a personal AI agent for WordPress site owners. Instead of clicking through wp-admin, just send a message — from the built-in admin sidebar chat, Telegram, Slack, or Discord.

**What can ClawWP do?**

* **Create and manage posts** — Draft, edit, schedule, and publish posts with natural language ("write a blog post about our spring sale and schedule it for Monday")
* **Moderate comments** — Approve, spam, trash, or reply to comments from your phone
* **Manage pages** — Create and edit pages without touching the block editor
* **Upload media** — Add images and files to your media library
* **Generate SEO** — Auto-generate meta titles, descriptions, and schema markup for any post or page
* **Trade prediction markets** — Browse, trade, and track positions on [GuessMarket](https://guessmarket.com) directly through chat
* **Blockchain transactions** — Connect AgentWallet to sign and broadcast on-chain transactions on Ethereum, Base, Polygon, and any EVM chain
* **Connect MCP servers** — Extend your agent with any Model Context Protocol (MCP) server — HTTP or stdio transport
* **Search your content** — Find any post, page, or media item instantly
* **Track costs** — See exactly how much you're spending on AI API calls with built-in usage tracking

**How it works:**

1. Install ClawWP and enter your Anthropic API key (bring your own key)
2. Optionally connect Telegram — create a bot with @BotFather and paste the token
3. Enable GuessMarket in settings to trade prediction markets
4. Start chatting — ClawWP understands natural language and executes WordPress actions on your behalf

**Built for security:**

* Every action respects WordPress user roles and capabilities
* Channel users are verified through a pairing system — your Telegram must be linked to your WordPress account
* All API keys are stored encrypted (AES-256-CBC)
* Webhook signatures are verified for every incoming request
* Full audit log of every action the agent takes
* Rate limiting to prevent abuse

**Memory that works:**

ClawWP remembers your preferences, past decisions, and site context across conversations. It learns how you like things done and gets better over time.

= Pro Features =

Upgrade to [ClawWP Pro](https://hifriendbot.com/clawwp) to unlock:

* **AgentWallet blockchain trading** — Sign and broadcast on-chain transactions via AgentWallet
* **Slack and Discord channels** — Chat with your AI agent from your team's Slack workspace or Discord server
* **WooCommerce tools** — Sales reports, inventory management, order details, customer lookup, and product management
* **Cognitive Memory** — HiFriendbot's three-layer memory architecture with semantic recall, importance scoring, and cross-session persistence
* **OpenAI support** — Choose between Anthropic Claude and OpenAI GPT models
* **500K tokens/month** — Included via the HiFriendbot Proxy — no API key needed
* **Unlimited user pairings** — Let your whole team use ClawWP from their own channels

= External Services =

ClawWP connects to the following third-party services when configured by the user. No data is sent to any external service until you explicitly configure it in ClawWP > Settings.

**Anthropic API**
ClawWP sends your chat messages to the Anthropic API to generate AI responses. This is the core AI provider. You must enter your own API key to use this service.

* Service: [https://www.anthropic.com](https://www.anthropic.com)
* Terms of Service: [https://www.anthropic.com/policies/terms](https://www.anthropic.com/policies/terms)
* Privacy Policy: [https://www.anthropic.com/policies/privacy](https://www.anthropic.com/policies/privacy)

**OpenAI API** (Pro only)
When configured with an OpenAI API key, ClawWP can send messages to OpenAI models (GPT-4o, etc.) as an alternative to Anthropic.

* Service: [https://openai.com](https://openai.com)
* Terms of Use: [https://openai.com/policies/terms-of-use](https://openai.com/policies/terms-of-use)
* Privacy Policy: [https://openai.com/policies/privacy-policy](https://openai.com/policies/privacy-policy)

**HiFriendbot Proxy** (Pro only)
Pro subscribers can route API requests through HiFriendbot's proxy server, which includes 500K tokens/month. License validation also uses this service.

* Service: [https://hifriendbot.com](https://hifriendbot.com)
* Terms of Service: [https://hifriendbot.com/terms-of-service/](https://hifriendbot.com/terms-of-service/)
* Privacy Policy: [https://hifriendbot.com/privacy-policy/](https://hifriendbot.com/privacy-policy/)

**GuessMarket MCP Server**
When enabled in settings, ClawWP connects to the GuessMarket MCP server to provide prediction market trading tools. GuessMarket prepares blockchain transactions for market trades.

* Service: [https://guessmarket.com](https://guessmarket.com)
* Terms of Service: [https://guessmarket.com/terms](https://guessmarket.com/terms)
* Privacy Policy: [https://guessmarket.com/privacy](https://guessmarket.com/privacy)

**AgentWallet API** (Pro only)
When configured with AgentWallet credentials, ClawWP can create wallets, check balances, and sign blockchain transactions on EVM chains.

* Service: [https://hifriendbot.com/wallet/](https://hifriendbot.com/wallet/)
* Terms of Service: [https://hifriendbot.com/terms-of-service/](https://hifriendbot.com/terms-of-service/)
* Privacy Policy: [https://hifriendbot.com/privacy-policy/](https://hifriendbot.com/privacy-policy/)

**Telegram Bot API**
When a Telegram bot token is configured, ClawWP receives and sends messages through Telegram's Bot API via webhook.

* Service: [https://core.telegram.org/bots](https://core.telegram.org/bots)
* Terms of Service: [https://telegram.org/tos](https://telegram.org/tos)
* Privacy Policy: [https://telegram.org/privacy](https://telegram.org/privacy)

**Slack API** (Pro only)
When configured, ClawWP communicates with your Slack workspace via the Slack API to receive and send messages.

* Service: [https://api.slack.com](https://api.slack.com)
* Terms of Service: [https://slack.com/terms-of-service](https://slack.com/terms-of-service)
* Privacy Policy: [https://slack.com/trust/privacy/privacy-policy](https://slack.com/trust/privacy/privacy-policy)

**Discord API** (Pro only)
When configured, ClawWP communicates with your Discord server via the Discord API to receive and send messages.

* Service: [https://discord.com/developers](https://discord.com/developers)
* Terms of Service: [https://discord.com/terms](https://discord.com/terms)
* Privacy Policy: [https://discord.com/privacy](https://discord.com/privacy)

**User-Configured MCP Servers**
ClawWP supports connecting to additional MCP (Model Context Protocol) servers configured by the user. Data sent depends on the specific server configured. Users are responsible for reviewing the terms and privacy policies of any custom MCP servers they connect.

== Installation ==

1. Upload the `clawwp` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **ClawWP > Settings** and enter your Anthropic API key
4. (Optional) Create a Telegram bot with [@BotFather](https://t.me/BotFather) and enter the token in settings
5. (Optional) Enable GuessMarket to trade prediction markets
6. Start chatting via the admin sidebar or Telegram

= Minimum Requirements =

* WordPress 6.0 or higher
* PHP 8.0 or higher
* An Anthropic API key ([get one here](https://console.anthropic.com/settings/keys))
* OpenSSL PHP extension (for API key encryption)
* HTTPS enabled on your site (required for webhook security)

== Frequently Asked Questions ==

= Do I need my own API key? =

Yes. ClawWP uses a bring-your-own-key (BYOK) model for the free version. You'll need an Anthropic API key to use the plugin. Pro subscribers get 500K tokens/month included via the HiFriendbot Proxy — no API key needed.

= How much does it cost to use? =

The plugin itself is free. You pay Anthropic directly for API usage. A typical conversation costs $0.01-0.05 depending on complexity. ClawWP includes a built-in cost tracker so you always know what you're spending.

= Is it safe to give an AI agent access to my WordPress site? =

ClawWP is built with security as a first-class concern:

* The agent can only perform actions that your WordPress user role allows
* Destructive actions require explicit confirmation
* Every action is logged in a full audit trail
* All credentials are encrypted at rest
* Webhook signatures prevent forged requests

= Can my editors and authors use it too? =

Yes. Each user pairs their own Telegram (or Slack/Discord with Pro) account. The agent respects each user's WordPress capabilities — an editor can manage posts but not delete users.

= Does it work with WooCommerce? =

With ClawWP Pro, yes. You can check sales, manage orders, update products, monitor inventory, and look up customers — all through natural language chat.

= How do I trade prediction markets? =

Enable GuessMarket in ClawWP > Settings. The agent connects to GuessMarket's MCP server and can browse markets, check odds, and place trades. GuessMarket prepares the blockchain transactions and AgentWallet (Pro) signs them on-chain.

= What is AgentWallet? =

AgentWallet is a server-side EVM wallet for AI agents, available at hifriendbot.com/wallet. It lets your ClawWP agent sign and broadcast blockchain transactions on Ethereum, Base, Polygon, and any EVM chain. 6,000 free transactions per month.

= What is MCP? =

MCP (Model Context Protocol) is an open standard that lets AI agents connect to external tools. ClawWP supports both HTTP and stdio MCP transports. GuessMarket and AgentWallet are built-in, and you can add your own custom MCP servers in settings.

= What AI models are supported? =

The free version supports Anthropic Claude models (Sonnet 4.5, Opus 4, Haiku 3.5). ClawWP Pro adds OpenAI models (GPT-4o, GPT-4o-mini, o1).

= Can I set a spending limit? =

Yes. Go to ClawWP > Settings > Budget & Limits to set a monthly budget. You'll receive alerts at 80% and 100% usage.

== Screenshots ==

1. Admin sidebar chat — talk to your AI agent from any WordPress admin page
2. Settings page — configure your API key, channels, and preferences
3. Dashboard — see recent conversations, usage stats, and quick actions
4. Cost tracker — monitor your AI spending with daily breakdowns
5. Telegram chat — manage your site from your phone

== Changelog ==

= 1.0.0 =
* Initial release
* Admin sidebar chat with streaming responses
* Telegram channel integration
* Post, page, comment, media, and SEO tools
* GuessMarket prediction market trading via MCP
* AgentWallet blockchain transaction support (Pro)
* MCP server support (HTTP and stdio transports)
* Permission system with role-based access control
* Channel pairing with one-time codes
* Cost tracking with budget alerts
* Audit logging
* AES-256-CBC encrypted credential storage
* Webhook signature verification (Telegram, Slack, Discord)

== Upgrade Notice ==

= 1.0.0 =
Initial release of ClawWP. Install, add your API key, and start chatting.
