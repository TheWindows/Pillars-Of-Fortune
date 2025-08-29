  [![](https://poggit.pmmp.io/shield.state/Pillars)](https://poggit.pmmp.io/p/Pillars)

# Pillars of Fortune

<img width="1366" height="687" alt="Screenshot 2025-08-28 125714" src="https://github.com/user-attachments/assets/000d3252-61b3-4ace-abc8-ea76b3735903" />


**Pillars of Fortune** is an exciting Minecraft Bedrock minigame plugin that delivers fast-paced, competitive gameplay to your server. Developed by **TheWindows**, this plugin is currently in **beta (v1.0.0)** and provides a robust framework for managing dynamic game worlds, interactive forms, scoreboards, and NPCs. Whether you're a player diving into thrilling battles or an admin crafting unique experiences, Pillars of Fortune is designed for you!

## Features

- **Dynamic Game Worlds**: Create and manage multiple game maps with flexible player counts and configurations.
- **Interactive UI**: Powered by FormAPI, players can navigate intuitive menus to join or manage games effortlessly.
- **Scoreboard Integration**: Utilize ScoreHud to display real-time stats like wins (`{pillars.wins}`) and coins (`{pillars.coins}`).
- **Boss Bar Support**: Leverage the apibossbar virion for engaging in-game notifications and status updates.
- **NPC Management**: Admins can create, list, and remove NPCs to enhance gameplay immersion.
- **Flexible Commands**: A comprehensive command system with partial command matching for ease of use.
- **Beta Development**: Actively maintained with a focus on stability and community-driven improvements.

## Requirements

To run Pillars of Fortune smoothly, install the following dependencies:

- **FormAPI**: Enables interactive form-based menus.
- **MultiWorld**: Supports multiple game worlds for diverse gameplay.
- **ScoreHud**: Displays dynamic player stats on scoreboards.
- **DEVirion**: Provides virion support for additional functionality.
- **apibossbar Virion**: Adds immersive boss bar features.

> **Note**: Ensure the lobby world is named `world` and set as the default in `server.properties`.

## Installation

1. Download the latest release from [GitHub Releases](https://github.com/TheWindows/Pillars-of-Fortune/releases).
2. Install the required dependencies listed above.
3. Place the plugin file in your server's `plugins` folder.
4. Configure the plugin via `config.yml` in the `plugin_data/Pillars` folder.
5. Restart your server to load the plugin.

> **Pro Tip**: Customize settings in `config.yml` to tailor the plugin to your server's needs.

## Commands

Pillars of Fortune provides a rich command system, accessible via `/pillars` or its alias `/p`. Below is a detailed list:

### Player Commands
| Command | Description | Permission |
|---------|-------------|------------|
| `/pillars join [map]` | Join a specific game or open the game menu if no map is specified. | `pillars.join` |
| `/pillars leave` | Leave the current game. | `pillars.join` |
| `/pillars list` | List all available games with player counts and status. | `pillars.join` |
| `/pillars info` | Display plugin details, including version, authors, and game stats. | `pillars.join` |

### Admin Commands
| Command | Description | Permission |
|---------|-------------|------------|
| `/pillars admin` | Open the admin settings menu for game management. | `pillars.admin` |
| `/pillars npc create` | Create a new NPC at your location. | `pillars.admin` |
| `/pillars npc list` | List all NPCs with their details (world, position, scale). | `pillars.admin` |
| `/pillars npc remove <id>` | Remove an NPC by its ID. | `pillars.admin` |
| `/pillars npc removeall` | Remove all NPCs from the server. | `pillars.admin` |
| `/pillars reset <map>` | Reset a specific game map to its original state. | `pillars.admin` |
| `/pillars reset all` | Reset all game maps to their original states. | `pillars.admin` |

> **Tip**: Use partial commands (e.g., `/p j` for `/pillars join`) for quicker access!

## Permissions

The plugin uses a simple permission system to control command access:

| Permission | Description | Default |
|------------|-------------|---------|
| `pillars.join` | Allows access to basic commands (`join`, `leave`, `list`, `info`). | `true` (all players) |
| `pillars.admin` | Grants access to admin commands (`admin`, `npc`, `reset`). | `op` (operators only) |

## Beta Notice

Pillars of Fortune is in **beta (v1.0.0)**. While we aim for stability, some features may be incomplete or unstable. Your feedback is crucial for improvement!

- **Report Bugs**: Submit issues at [GitHub Issues](https://github.com/TheWindows/Pillars-of-Fortune/issues).
- **Get Support**: Join our community on Discord (Username: **TheWindowsJava**).

## ScoreHud Integration

Enhance your server with dynamic scoreboards using these tags:

- `{pillars.wins}`: Displays the player's total wins.
- `{pillars.coins}`: Shows the player's coin balance.

Configure ScoreHud to include these tags for a personalized experience.

## Game World Setup

- Store game maps in the `resources/Maps` folder.
- Use `/pillars reset <map>` to restore maps to their original state.
- Ensure maps are properly configured in the plugin's settings.

## Contributing

We welcome contributions to improve Pillars of Fortune! Here's how to get involved:

1. **Fork the Repository**: Clone the project from [GitHub](https://github.com/TheWindows/Pillars-of-Fortune).
2. **Submit Pull Requests**: Add features, fix bugs, or enhance documentation.
3. **Report Issues**: Share bugs or suggestions via [GitHub Issues](https://github.com/TheWindows/Pillars-of-Fortune/issues).
4. **Join the Community**: Connect on Discord (Username: **TheWindowsJava**) to discuss ideas.

## Acknowledgments

A huge thank you to our community for supporting **Pillars of Fortune**! Your feedback fuels this project. Special thanks to **TheWindows** for creating this exciting plugin.

> **© 2025 TheWindows. All Rights Reserved.**

## Contact

For support or inquiries, reach out on:
- **Discord**: TheWindowsJava
- **GitHub**: [TheWindows](https://github.com/TheWindows)

Thank you for choosing **Pillars of Fortune**! 🚀
