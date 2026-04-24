# ClawWP — Ai Agent for WordPress

**Manage your WordPress site through chat.** Create posts, moderate comments, generate SEO, trade prediction markets, execute blockchain transactions — all from a simple conversation.

ClawWP puts an Ai agent in your WordPress admin sidebar. Instead of clicking through wp-admin menus, just tell your site what to do.

[![Download](https://img.shields.io/badge/Download-ClawWP%20Free-8b5cf6?style=for-the-badge)](https://hifriendbot.com/clawwp/)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b?style=for-the-badge&logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![License](https://img.shields.io/badge/License-GPLv2-green?style=for-the-badge)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Looking for an OpenClaw Alternative?

OpenClaw is a powerful open-source Ai assistant — but it requires self-hosting on a VPS or Docker, managing your own infrastructure, and connecting to WordPress through external plugins or APIs. For WordPress site owners who just want an Ai agent that works, that's a lot of overhead.

ClawWP is purpose-built for WordPress. Install, activate, add your API key, and start chatting — 60 seconds, no servers to manage.

| | ClawWP | OpenClaw |
|---|---|---|
| **Installation** | WordPress plugin — upload and activate | Self-hosted (Docker/VPS) or managed hosting |
| **WordPress integration** | Native — uses WP roles, hooks, capabilities | Connects via REST API or MCP plugin |
| **Cost tracking** | Built-in with budget alerts at 80% and 100% | No built-in cost tracking |
| **MCP support** | Full client (HTTP + stdio) | Yes (native) |
| **Telegram, Slack, Discord** | Yes (Telegram free, Slack/Discord Pro) | Yes |
| **Prediction markets** | Built-in (GuessMarket) | No |
| **Blockchain wallets** | Built-in (AgentWallet, all EVM chains) | No |
| **Infrastructure needed** | None — runs inside WordPress | VPS, Docker, or managed hosting ($9-39/mo) |
| **Price** | Free / $9.99 Pro | Free (open source) + hosting + API costs |

If you're already running WordPress and want an Ai agent without spinning up extra infrastructure, ClawWP gets you there faster.

---

## What Can ClawWP Do?

### Core Tools (Free)
- **Content management** — Create, edit, schedule, and publish posts and pages through natural language
- **Comment moderation** — Approve, spam, trash, or reply to comments with a simple command
- **SEO optimization** — Generate meta titles, descriptions, and schema markup powered by Ai
- **Media management** — Search, organize, update alt text and captions
- **Site information** — Check site health, stats, and configuration
- **Cost tracking** — Monitor token usage with daily breakdowns and budget alerts at 80% and 100%
- **Memory** — The agent remembers your preferences across conversations

### Channels
- **Admin sidebar** — Built right into wp-admin (Free)
- **Telegram** — Manage your site from your phone (Free)
- **Slack** — Chat from your team's workspace (Pro)
- **Discord** — Same agent, wherever your community lives (Pro)

### Integrations
- **MCP servers** — Connect any Model Context Protocol server to extend your agent with custom tools
- **GuessMarket** — Trade prediction markets through natural conversation (Free)
- **AgentWallet** — Sign and broadcast blockchain transactions on all EVM chains (Pro)
- **WooCommerce** — Manage orders, products, customers, and inventory through chat (Pro)

---

## Quick Start

**1.** Download ClawWP from [hifriendbot.com/clawwp](https://hifriendbot.com/clawwp/)

**2.** Upload and activate in WordPress (Plugins → Add New → Upload Plugin)

**3.** Go to **ClawWP → Settings** and enter your [Anthropic API key](https://console.anthropic.com/settings/keys)

**4.** Start chatting from the admin sidebar — your Ai agent is ready

Pro subscribers don't need an API key — 500K tokens/month are included.

---

## How It Works

ClawWP uses Claude's native tool use (function calling) to execute WordPress actions. The agent has 7+ tools with JSON Schema parameter definitions and runs a multi-step execution loop:

1. You send a message
2. The agent analyzes your request and selects the right tools
3. Each tool call checks WordPress user capabilities
4. Results feed back into the conversation for follow-up actions
5. Up to 10 tool iterations per turn

```
You: "Write a blog post about our summer sale, add SEO, and schedule it for Monday"

ClawWP: [creates post with content]
        [generates meta description]
        [schedules for Monday 9:00 AM]

        Done! "Summer Sale 2026" is scheduled for Monday at 9:00 AM.
```

---

## Cost Tracking

The #1 complaint about Ai WordPress plugins is surprise bills. ClawWP solves this:

- Token usage and cost per conversation
- Daily and monthly spending breakdowns
- Cost breakdown by model
- Monthly budget with alerts at 80% and 100%
- Typical conversation costs $0.01–0.05

You always know what you're spending. No surprises.

---

## MCP Support

ClawWP is the first WordPress plugin with full **Model Context Protocol** client support:

- **HTTP transport** — Connect to any remote MCP server
- **stdio transport** — Run local MCP servers via command line
- **Auto-discovery** — Tools are discovered and registered automatically
- **Built-in servers** — GuessMarket and AgentWallet come pre-configured

Add your own MCP servers in ClawWP → Settings to give your agent any capability you need.

---

## Security

- Every action checks WordPress user capabilities
- Destructive actions require server-side confirmation tokens
- All API keys encrypted with AES-256-CBC
- Webhook signatures verified on every request
- Full audit log of every agent action
- Rate limiting to prevent abuse
- Comprehensive security audit: 49 PHP files, 0 critical findings

---

## Free vs Pro

| Feature | Free | Pro — $9.99/mo |
|---|---|---|
| Admin sidebar chat | ✓ | ✓ |
| Telegram | ✓ | ✓ |
| Slack & Discord | — | ✓ |
| Core WordPress tools | ✓ | ✓ |
| WooCommerce tools | — | ✓ |
| GuessMarket trading | ✓ | ✓ |
| AgentWallet blockchain | — | ✓ |
| MCP server support | ✓ | ✓ |
| OpenAI models | — | ✓ |
| CogmemAi | Basic | Full |
| Cost tracking | ✓ | ✓ |
| Included tokens | BYOK | 500K/mo |

The free tier is genuinely useful — no artificial limits to push you into upgrading.

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Anthropic API key ([get one here](https://console.anthropic.com/settings/keys)) or ClawWP Pro license
- OpenSSL PHP extension
- HTTPS enabled

---

## Supported Ai Models

**Free:** Claude Sonnet 4.5, Claude Opus 4, Claude Haiku 3.5

**Pro:** All Claude models + GPT-4o, GPT-4o-mini, o1

---

## Links

- **Website:** [hifriendbot.com/clawwp](https://hifriendbot.com/clawwp/)
- **Documentation:** [hifriendbot.com/clawwp/docs](https://hifriendbot.com/clawwp/docs/)
- **Download:** [hifriendbot.com/clawwp](https://hifriendbot.com/clawwp/)
- **GuessMarket:** [guessmarket.com](https://guessmarket.com)
- **AgentWallet:** [hifriendbot.com/wallet](https://hifriendbot.com/wallet/)
- **CogmemAi:** [hifriendbot.com/developer](https://hifriendbot.com/developer/)

---

## License

ClawWP is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

Built by [HiFriendbot](https://hifriendbot.com).
