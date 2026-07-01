[![](https://poggit.pmmp.io/shield.state/Pillars)](https://poggit.pmmp.io/p/Pillars)

# Pillars of Fortune

<img width="1366" height="687" alt="Screenshot 2025-08-28 125714" src="https://github.com/user-attachments/assets/000d3252-61b3-4ace-abc8-ea76b3735903" />

**Pillars of Fortune** is an exciting Minecraft Bedrock minigame plugin for PocketMine-MP. Players fight to be the last one standing across dynamically created and reset game worlds. Developed by **TheWindows** and **Doma**, now on **v2.0.0** with major improvements to world management, spectating, scoreboards, and configuration.

## Features

- **Interactive UI**: Powered by FormAPI, players can join games, spectate, view stats, and access the market through intuitive menus.
- **Scoreboard Integration**: Built-in scoreboard system with fully customizable lines and placeholders for lobby, waiting, game, and spectator states.
- **Boss Bar Support**: Uses the apibossbar virion to show item drop countdowns during games.
- **NPC Management**: Admins can create, list, and remove NPCs that open the game menu when hit.
- **Spectator System**: Players can spectate active games from the lobby or after dying, with a compass to teleport to alive players.
- **Flexible Commands**: A full command system with short aliases for quick access.
- **JSON & MySQL Support**: Choose your database provider in `config.yml`. Falls back to JSON automatically if MySQL fails.

## Requirements

- PocketMine-MP API 5

> **Note**: The lobby world name defaults to `world`. Change it in `config.yml` if needed.

## Installation

1. Download the latest release from [GitHub Releases](https://github.com/TheWindows/Pillars-of-Fortune/releases).
2. Place the plugin `.phar` in your server's `plugins/` folder.
3. Start the server once to generate config files.
4. Use `/pillars admin` in-game to create a game and set spawn points.

## Commands

### Player Commands
| Command | Description | Permission |
|---------|-------------|------------|
| `/pillars` | Open the game menu | `pillars.join` |
| `/pillars join [map]` | Join a specific game or open the game menu | `pillars.join` |
| `/pillars leave` | Leave the current game | `pillars.join` |
| `/pillars list` | List all active games with player counts and status | `pillars.join` |
| `/pillars stats` | View your personal stats | `pillars.join` |
| `/pillars info` | Show plugin version, authors, and active game count | `pillars.join` |

### Admin Commands
| Command | Description | Permission |
|---------|-------------|------------|
| `/pillars admin` | Open the admin settings menu | `pillars.admin` |
| `/pillars npc create` | Spawn an NPC at your location | `pillars.admin` |
| `/pillars npc list` | List all NPCs with positions | `pillars.admin` |
| `/pillars npc remove <id>` | Remove an NPC by index | `pillars.admin` |
| `/pillars npc removeall` | Remove all NPCs | `pillars.admin` |
| `/pillars reset <map\|all>` | Reset a map or all maps to their original state | `pillars.admin` |

> Alias: `/p` — e.g. `/p j` for join, `/p l` for leave.

## Permissions

| Permission | Description | Default |
|------------|-------------|---------|
| `pillars.join` | Access to basic player commands | `true` |
| `pillars.admin` | Access to admin commands | `op` |

## Map Setup

1. Build your map and place it in the server `worlds/` folder.
2. Run `/pillars admin` → **Create Game**, enter the world name and settings.
3. Use the **Spawn Wand** (Blaze Rod) to place spawn points by right-clicking blocks.
4. The map is automatically saved as a template and restored after every game.

## Scoreboard Placeholders

Configure scoreboard lines in `config.yml` using these placeholders:

`{player_name}` `{player_coins}` `{player_wins}` `{player_kills}` `{player_deaths}` `{player_kdr}` `{online_players}` `{game_id}` `{game_players}` `{game_max_players}` `{game_min_players}` `{game_time_left}` `{date}` `{time}`

## Contributing

1. Fork the repository from [GitHub](https://github.com/TheWindows/Pillars-of-Fortune).
2. Submit pull requests for features, fixes, or documentation.
3. Report bugs via [GitHub Issues](https://github.com/TheWindows/Pillars-of-Fortune/issues).
4. Reach out on Discord: **TheWindowsJava** or **am2ma**.


## Contact

### TheWindows
- Discord: TheWindowsJava
- GitHub: [TheWindows](https://github.com/TheWindows)

### Doma
- Discord: am2ma
- GitHub: [Doma-0609](https://github.com/Doma-0609)
- Telegram: [xIsGod](https://t.me/xIsGod)

### Amirxd
- Discord: Soon (._.)
- GitHub: [amirxdcy4455](https://github.com/amirxdcy4455)
- Telegram: [amirxdcy](https://t.me/amirxdcy)

> **© 2025 TheWindows. All Rights Reserved.** 
