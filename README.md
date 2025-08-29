🌟 Pillars of Fortune
Pillars of Fortune is an exhilarating Minecraft Bedrock minigame plugin that brings fast-paced, competitive gameplay to your server. Developed by TheWindows, this plugin is currently in beta (v1.0.0) and offers a robust framework for creating and managing dynamic game worlds, complete with interactive forms, scoreboards, and NPC management. Whether you're a player seeking thrilling battles or an admin crafting unique experiences, Pillars of Fortune has you covered!

🚀 Features

Dynamic Game Worlds: Create and manage multiple game maps with ease, supporting various player counts and configurations.
Interactive UI: Powered by FormAPI, players can access intuitive menus to join or manage games seamlessly.
Scoreboard Integration: Leverage ScoreHud to display real-time player stats like wins ({pillars.wins}) and coins ({pillars.coins}).
Boss Bar Support: Utilize the apibossbar virion for engaging in-game notifications and status updates.
NPC Management: Admins can create, list, and remove NPCs to enhance gameplay immersion.
Flexible Commands: A comprehensive command system with partial command matching for a smooth user experience.
Beta Development: Actively maintained with a focus on stability and community feedback to polish the experience.


📋 Requirements
To ensure Pillars of Fortune runs smoothly on your server, install the following dependencies:

FormAPI: Enables interactive form-based menus for players and admins.
MultiWorld: Supports multiple game worlds for varied gameplay experiences.
ScoreHud: Displays dynamic player stats on scoreboards.
DEVirion: Provides virion support for additional functionality.
apibossbar Virion: Adds immersive boss bar features.


Note: Ensure the lobby world is named world and set as the default world in server.properties.


🛠 Installation

Download the latest release of Pillars of Fortune from GitHub Releases.
Install the required dependencies listed above.
Place the plugin file in your server's plugins folder.
Configure the plugin by editing config.yml in the plugin_data/Pillars folder.
Restart your server to load the plugin.


Pro Tip: Check the config.yml file for custom settings to tailor the plugin to your server's needs.


✨ Commands
Pillars of Fortune offers a rich set of commands for players and admins, accessible via /pillars or its alias /p. Below is a detailed breakdown:
Player Commands



Command
Description
Permission



/pillars join [map]
Join a specific game or open the game menu if no map is specified.
pillars.join


/pillars leave
Leave the current game.
pillars.join


/pillars list
List all available games, including player counts and status.
pillars.join


/pillars info
Display plugin information, including version, authors, and game stats.
pillars.join


Admin Commands



Command
Description
Permission



/pillars admin
Open the admin settings menu for game management.
pillars.admin


/pillars npc create
Create a new NPC at your location.
pillars.admin


/pillars npc list
List all NPCs with their details (world, position, scale).
pillars.admin


/pillars npc remove <id>
Remove an NPC by its ID.
pillars.admin


/pillars npc removeall
Remove all NPCs from the server.
pillars.admin


/pillars reset <map>
Reset a specific game map to its original state.
pillars.admin


/pillars reset all
Reset all game maps to their original states.
pillars.admin



Tip: Use partial commands (e.g., /p j for /pillars join) for faster access!


🔑 Permissions
Pillars of Fortune uses a simple permission system to control access to commands:



Permission
Description
Default



pillars.join
Allows players to join games and use basic commands (join, leave, list, info).
true (all players)


pillars.admin
Grants access to admin commands (admin, npc, reset).
op (operators only)



🧨 Beta Notice
Pillars of Fortune is currently in beta (v1.0.0). While we strive for stability, some features may be incomplete or unstable. We greatly appreciate your feedback to help us improve!

Report Bugs: Submit issues at GitHub Issues.
Get Support: Join our community on Discord (Username: TheWindowsJava) for assistance.


📈 ScoreHud Integration
Enhance your server with dynamic scoreboards using ScoreHud tags:

{pillars.wins}: Displays the player's total wins.
{pillars.coins}: Shows the player's coin balance.

Configure ScoreHud to include these tags for a personalized player experience.

🗺 Game World Setup

Game maps must be stored in the resources/Maps folder.
Use /pillars reset <map> to restore maps to their original state.
Ensure maps are properly configured in the plugin's settings to avoid issues.


🙌 Contributing
We welcome contributions to make Pillars of Fortune even better! Here's how you can help:

Fork the Repository: Clone the project from GitHub.
Submit Pull Requests: Add new features, fix bugs, or improve documentation.
Report Issues: Share bugs or suggestions via GitHub Issues.
Join the Community: Connect with us on Discord (Username: TheWindowsJava) to discuss ideas.


❤️ Acknowledgments
A huge thank you to our community for supporting Pillars of Fortune! Your feedback and enthusiasm drive this project forward. Special thanks to TheWindows for developing this exciting plugin.

© 2025 TheWindows. All Rights Reserved.


📬 Contact
For support or inquiries, reach out to us on:

Discord: TheWindowsJava
GitHub: TheWindows

Thank you for choosing Pillars of Fortune! Let’s create epic gaming moments together! 🚀
