<?php
namespace TheWindows\Pillars\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use TheWindows\Pillars\Main;
use TheWindows\Pillars\Forms\GameMenuForm;
use TheWindows\Pillars\Forms\AdminSettingsForm;

class MainCommand extends Command {
    
    private $plugin;
    private $subcommands = [
        'join' => 'Join a game',
        'leave' => 'Leave current game',
        'list' => 'List available games',
        'info' => 'Plugin information',
        'admin' => 'Admin commands',
        'npc' => 'Manage NPCs (admin)',
        'reset' => 'Reset a game map (admin)' 
    ];
    
    public function __construct(Main $plugin) {
        parent::__construct("pillars", "Main Pillars command", "/pillars [join|leave|list|info|admin|npc|reset]");
        $this->setPermission("pillars.join");
        $this->setAliases(["p"]);
        $this->plugin = $plugin;
    }
    
    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if(!$this->testPermission($sender)) {
            return false;
        }
        
        if(!$sender instanceof Player) {
            $sender->sendMessage("This command can only be used in-game.");
            return false;
        }
        
        if(empty($args)) {
            $this->showCommandList($sender);
            return true;
        }
        
        $subcommand = strtolower($args[0]);
        
        switch($subcommand) {
            case "join":
            case "j":
                if(isset($args[1])) {
                    $mapName = $args[1];
                    $gameManager = $this->plugin->getGameManager();
                    
                    $playerState = $gameManager->checkPlayerState($sender);
                    if($playerState !== null) {
                        $sender->sendMessage("§cYou are already in a game! Use /pillars leave first.");
                        return false;
                    }
                    
                    if($gameManager->gameExists($mapName)) {
                        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($mapName);
                        if($world !== null) {
                            $currentPlayers = count($world->getPlayers());
                            $maxPlayers = $gameManager->getMapMaxPlayers($mapName);
                            
                            if($currentPlayers >= $maxPlayers) {
                                $sender->sendMessage("§cGame '$mapName' is full! (§6{$currentPlayers}/{$maxPlayers}§c)");
                                return false;
                            }
                        }
                        
                        $gameManager->addPlayerToGame($sender, $mapName);
                        $sender->sendMessage("§aJoining game: §6$mapName");
                        return true;
                    } else {
                        $availableGames = $gameManager->getAvailableGames();
                        $gameNames = array_column($availableGames, 'world');
                        
                        $sender->sendMessage("§cMap '$mapName' not found!");
                        if(!empty($gameNames)) {
                            $sender->sendMessage("§aAvailable maps: §6" . implode("§7, §6", $gameNames));
                        }
                        return false;
                    }
                } else {
                    $form = GameMenuForm::createForm($this->plugin, $sender);
                    $sender->sendForm($form);
                    return true;
                }
                break;
                
            case "leave":
            case "quit":
            case "l":
                $gameManager = $this->plugin->getGameManager();
                $playerState = $gameManager->checkPlayerState($sender);
                
                if($playerState === null) {
                    $sender->sendMessage("§cYou are not in any game!");
                    return false;
                }
                
                $gameManager->removePlayerFromGame($sender);
                $sender->sendMessage("§aYou have left the game!");
                return true;
                
            case "list":
            case "games":
            case "ls":
                $gameManager = $this->plugin->getGameManager();
                $availableGames = $gameManager->getAvailableGames();
                
                if(empty($availableGames)) {
                    $sender->sendMessage("§cNo games available!");
                    return true;
                }
                
                $sender->sendMessage("§6§lPillars Games List:");
                $sender->sendMessage("§7====================");
                
                foreach($availableGames as $game) {
                    $players = $game['players'];
                    $maxPlayers = $game['max_players'];
                    $status = $game['status'];
                    
                    $sender->sendMessage("§e" . $game['world'] . " §7- §a" . $players . "§7/§c" . $maxPlayers . " §7- " . $status);
                }
                
                $sender->sendMessage("§7====================");
                $sender->sendMessage("§aUse §e/pillars join <map> §ato join a specific game!");
                return true;
                
            case "info":
            case "i":
                $gameManager = $this->plugin->getGameManager();
                $availableGames = $gameManager->getAvailableGames();
                $totalGames = count($availableGames);
                $totalPlayers = 0;
                
                foreach($availableGames as $game) {
                    $totalPlayers += $game['players'];
                }
                
                $sender->sendMessage("§6§lPillars Plugin Information:");
                $sender->sendMessage("§7====================");
                $sender->sendMessage("§eVersion: §a" . $this->plugin->getDescription()->getVersion());
                $sender->sendMessage("§eAuthor: §a" . implode(", ", $this->plugin->getDescription()->getAuthors()));
                $sender->sendMessage("§eTotal Games: §a" . $totalGames);
                $sender->sendMessage("§eTotal Players: §a" . $totalPlayers);
                $sender->sendMessage("§7====================");
                $sender->sendMessage("§aAvailable Commands:");
                $sender->sendMessage("§e/pillars join §7- Join a game");
                $sender->sendMessage("§e/pillars leave §7- Leave current game");
                $sender->sendMessage("§e/pillars list §7- List available games");
                $sender->sendMessage("§e/pillars info §7- Plugin information");
                $sender->sendMessage("§e/pillars admin §7- Admin commands");
                $sender->sendMessage("§e/pillars npc §7- Manage NPCs (admin)");
                $sender->sendMessage("§e/pillars reset §7- Reset a game map (admin)"); 
                $sender->sendMessage("§7====================");
                return true;
                
            case "admin":
            case "a":
                if(!$sender->hasPermission("pillars.admin")) {
                    $sender->sendMessage("§cYou don't have permission to use this command!");
                    return false;
                }
                
                $form = AdminSettingsForm::createForm($this->plugin, $sender);
                $sender->sendForm($form);
                return true;
                
            case "npc":
                if(!$sender->hasPermission("pillars.admin")) {
                    $sender->sendMessage("§cYou don't have permission to use this command!");
                    return false;
                }
                
                $npcManager = $this->plugin->getNPCManager();
                
                if(empty($args[1])) {
                    $sender->sendMessage("§6§lNPC Management:");
                    $sender->sendMessage("§7====================");
                    $sender->sendMessage("§e/pillars npc create §7- Create an NPC");
                    $sender->sendMessage("§e/pillars npc list §7- List all NPCs");
                    $sender->sendMessage("§e/pillars npc remove <id> §7- Remove an NPC");
                    $sender->sendMessage("§e/pillars npc removeall §7- Remove all NPCs");
                    $sender->sendMessage("§7====================");
                    return true;
                }
                
                $npcSubcommand = strtolower($args[1]);
                
                switch($npcSubcommand) {
                    case "create":
                    case "c":
                        $npcManager->createNPC($sender);
                        break;
                        
                    case "list":
                    case "ls":
                    case "l":
                        $npcs = $npcManager->getNPCs();
                        if(empty($npcs)) {
                            $sender->sendMessage("§cNo NPCs found.");
                        } else {
                            $sender->sendMessage("§aNPC List:");
                            foreach($npcs as $index => $data) {
                                $sender->sendMessage("§7#$index: World: {$data['world']}, Position: {$data['x']}, {$data['y']}, {$data['z']}, Scale: {$data['scale']}");
                            }
                        }
                        break;
                        
                    case "remove":
                    case "r":
                        if(isset($args[2]) && is_numeric($args[2])) {
                            $index = (int)$args[2];
                            if($npcManager->removeNPC($index)) {
                                $sender->sendMessage("§aNPC #$index removed successfully!");
                            } else {
                                $sender->sendMessage("§cInvalid NPC index.");
                            }
                        } else {
                            $sender->sendMessage("§cUsage: /pillars npc remove <index>");
                        }
                        break;
                        
                    case "removeall":
                    case "ra":
                        $npcManager->removeAllNPCs();
                        $sender->sendMessage("§aAll NPCs have been removed!");
                        break;
                        
                    default:
                        $sender->sendMessage("§cUnknown NPC subcommand. Use /pillars npc for help.");
                        break;
                }
                return true;
                
            case "reset": 
                if(!$sender->hasPermission("pillars.admin")) {
                    $sender->sendMessage("§cYou don't have permission to use this command!");
                    return false;
                }
                
                if(empty($args[1])) {
                    $sender->sendMessage("§6§lMap Reset:");
                    $sender->sendMessage("§7====================");
                    $sender->sendMessage("§e/pillars reset <mapname> §7- Reset a specific map");
                    $sender->sendMessage("§e/pillars reset all §7- Reset all maps");
                    
                    
                    $gameManager = $this->plugin->getGameManager();
                    $availableGames = $gameManager->getAvailableGames();
                    $gameNames = array_column($availableGames, 'world');
                    
                    if(!empty($gameNames)) {
                        $sender->sendMessage("§aAvailable maps: §6" . implode("§7, §6", $gameNames));
                    }
                    $sender->sendMessage("§7====================");
                    return true;
                }
                
                $mapName = $args[1];
                
                if($mapName === "all") {
                    
                    $gameManager = $this->plugin->getGameManager();
                    $availableGames = $gameManager->getAvailableGames();
                    $resetCount = 0;
                    
                    foreach($availableGames as $game) {
                        $worldName = $game['world'];
                        if($this->plugin->getMapManager()->resetWorld($worldName)) {
                            $resetCount++;
                            $sender->sendMessage("§aReset map: §6$worldName");
                        } else {
                            $sender->sendMessage("§cFailed to reset map: §6$worldName");
                        }
                    }
                    
                    $sender->sendMessage("§aSuccessfully reset §6$resetCount§a maps!");
                    return true;
                } else {
                    
                    if($this->plugin->getMapManager()->resetWorld($mapName)) {
                        $sender->sendMessage("§aSuccessfully reset map: §6$mapName");
                    } else {
                        $sender->sendMessage("§cFailed to reset map: §6$mapName");
                        $sender->sendMessage("§cMake sure the map exists in your resources/Maps folder.");
                    }
                    return true;
                }
                break;
                
            default:
                
                $matched = [];
                foreach(array_keys($this->subcommands) as $cmd) {
                    if(stripos($cmd, $subcommand) === 0) {
                        $matched[] = $cmd;
                    }
                }
                
                if(count($matched) === 1) {
                    
                    $args[0] = $matched[0];
                    return $this->execute($sender, $commandLabel, $args);
                } else {
                    $this->showCommandList($sender);
                    return false;
                }
        }
    }
    
    private function showCommandList(CommandSender $sender): void {
        $sender->sendMessage("§6§lPillars Commands:");
        $sender->sendMessage("§7====================");
        
        foreach($this->subcommands as $cmd => $desc) {
            $permission = ($cmd === 'admin' || $cmd === 'npc' || $cmd === 'reset') ? 'pillars.admin' : 'pillars.join';
            
            if($sender->hasPermission($permission)) {
                $sender->sendMessage("§e/pillars $cmd §7- $desc");
            }
        }
        
        $sender->sendMessage("§7====================");
        $sender->sendMessage("§aTip: Use partial commands like §e/p j §afor §e/pillars join");
    }
    
    public function getAvailableSubcommands(Player $player): array {
        $available = [];
        
        foreach($this->subcommands as $cmd => $desc) {
            $permission = ($cmd === 'admin' || $cmd === 'npc' || $cmd === 'reset') ? 'pillars.admin' : 'pillars.join';
            
            if($player->hasPermission($permission)) {
                $available[] = $cmd;
            }
        }
        
        return $available;
    }
}