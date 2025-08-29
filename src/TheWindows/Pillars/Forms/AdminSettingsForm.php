<?php
namespace TheWindows\Pillars\Forms;

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use pocketmine\item\VanillaItems;
use pocketmine\world\WorldManager;
use pocketmine\utils\Filesystem;
use TheWindows\Pillars\Main;

class AdminSettingsForm {
    
    public static function createForm(Main $plugin, Player $player): SimpleForm {
        $form = new SimpleForm(function(Player $player, $data) use ($plugin) {
            if($data === null) return;
            
            switch($data) {
                case 0:
                    self::createGameForm($plugin, $player);
                    break;
                case 1:
                    $player->getInventory()->addItem(VanillaItems::BLAZE_ROD()->setCustomName("§4Set Spawn Wand"));
                    $player->sendMessage("§aYou received the spawn wand.");
                    break;
                case 2:
                    $player->getInventory()->addItem(VanillaItems::REDSTONE_DUST()->setCustomName("§4Remove Spawn Tool"));
                    $player->sendMessage("§aYou received the remove tool.");
                    break;
                case 3:
                    $plugin->getNPCManager()->createNPC($player);
                    break;
                case 4:
                    $plugin->getNPCManager()->removeAllNPCs();
                    $player->sendMessage("§aAll NPCs removed!");
                    break;
                case 5:
                    self::removeArenaForm($plugin, $player);
                    break;
            }
        });
        
        $form->setTitle("§4§lPillars Admin Menu");
        $form->addButton("§4Create Game\n§8Setup new game arena");
        $form->addButton("§4Get Spawn Wand\n§8Set spawn points");
        $form->addButton("§4Get Remove Tool\n§8Remove spawn points");
        $form->addButton("§4Create NPC\n§8Spawn game joining NPC");
        $form->addButton("§4Remove All NPCs\n§8Remove all game NPCs");
        $form->addButton("§4Remove Arena\n§8Delete a game arena");
        
        return $form;
    }
    
    private static function createGameForm(Main $plugin, Player $player): void {
        $form = new CustomForm(function(Player $player, $data) use ($plugin) {
            if($data === null) return;
            
            $worldName = trim($data[0]);
            $maxPlayers = (int)$data[1];
            $minPlayers = (int)$data[2];
            $countdownTime = (int)$data[3];
            $itemInterval = (int)$data[4];
            
            if(empty($worldName)) {
                $player->sendMessage("§cWorld name cannot be empty!");
                return;
            }
            
            if($plugin->getGameManager()->gameExists($worldName)) {
                $player->sendMessage("§cGame already exists in '$worldName'!");
                return;
            }
            
            $maxPlayers = max(2, min(24, $maxPlayers));
            $minPlayers = max(2, min($maxPlayers, $minPlayers));
            $countdownTime = max(5, min(60, $countdownTime));
            $itemInterval = max(10, min(300, $itemInterval));
            $gameTime = 1200; 
            
            $worldManager = $plugin->getServer()->getWorldManager();
            $worldsPath = $plugin->getServer()->getDataPath() . "worlds/";
            $mapsDataPath = $plugin->getDataFolder() . "Maps/";
            
            $sourceWorldPath = $worldsPath . $worldName;
            $targetTemplatePath = $mapsDataPath . $worldName;
            
            if(!is_dir($sourceWorldPath)) {
                $player->sendMessage("§cWorld '$worldName' not found in worlds folder!");
                return;
            }
            
            if($worldManager->isWorldLoaded($worldName)) {
                $world = $worldManager->getWorldByName($worldName);
                if($world !== null) {
                    foreach($world->getPlayers() as $p) {
                        $p->teleport($plugin->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
                    }
                    $worldManager->unloadWorld($world);
                }
            }
            
            if(!is_dir($targetTemplatePath)) {
                mkdir($targetTemplatePath, 0755, true);
            }
            
            try {
                $plugin->getMapManager()->recursiveCopy($sourceWorldPath, $targetTemplatePath);
                $player->sendMessage("§aCreated template from world '$worldName'!");
            } catch (\Exception $e) {
                $player->sendMessage("§cFailed to create template: " . $e->getMessage());
                return;
            }
            
            $worldManager->loadWorld($worldName);
            
            if($plugin->getGameManager()->createGame($worldName, $maxPlayers, $minPlayers, $gameTime, $countdownTime, $itemInterval)) {
                $player->sendMessage("§aGame created in '$worldName'!");
                $player->sendMessage("§6Max Players: §e" . $maxPlayers);
                $player->sendMessage("§6Min Players: §e" . $minPlayers);
                $player->sendMessage("§6Game Time: §e1200 seconds");
                $player->sendMessage("§6Countdown Time: §e" . $countdownTime . " seconds");
                
                $player->getInventory()->addItem(VanillaItems::BLAZE_ROD()->setCustomName("§4Set Spawn Wand"));
                $player->getInventory()->addItem(VanillaItems::REDSTONE_DUST()->setCustomName("§4Remove Spawn Tool"));
                
                if($worldManager->loadWorld($worldName)) {
                    $world = $worldManager->getWorldByName($worldName);
                    $player->teleport($world->getSpawnLocation());
                    $plugin->getMapManager()->autoSetupSpawnPoints($worldName);
                }
            } else {
                $player->sendMessage("§cFailed to create game in '$worldName'!");
            }
        });
        
        $form->setTitle("§4§lCreate New Game");
        $form->addInput("§4World Name:", "world_name");
        $form->addInput("§4Max Players (2-24):", "8", "8");
        $form->addInput("§4Min Players to Start (2-Max):", "2", "2");
        $form->addInput("§4Countdown Time seconds (5-60):", "30", "30");
        $form->addInput("§4Item Interval seconds (10-300):", "30", "30");
        
        $player->sendForm($form);
    }
    
    private static function removeArenaForm(Main $plugin, Player $player): void {
        $form = new CustomForm(function(Player $player, $data) use ($plugin) {
            if($data === null) return;
            
            $worldName = trim($data[0]);
            
            if(empty($worldName)) {
                $player->sendMessage("§cWorld name cannot be empty!");
                return;
            }
            
            if($plugin->getGameManager()->removeGame($worldName)) {
                // Delete the map folder from plugin data
                $mapsDataPath = $plugin->getDataFolder() . "Maps/" . $worldName;
                if(is_dir($mapsDataPath)) {
                    try {
                        Filesystem::recursiveUnlink($mapsDataPath);
                        $player->sendMessage("§aMap template for '$worldName' removed from Maps folder!");
                    } catch (\Exception $e) {
                        $player->sendMessage("§cFailed to remove map template: " . $e->getMessage());
                    }
                }
                
                $player->sendMessage("§aArena '$worldName' removed successfully!");
            } else {
                $player->sendMessage("§cArena '$worldName' not found!");
            }
        });
        
        $form->setTitle("§4§lRemove Arena");
        $form->addInput("§4World Name to Remove:", "world_name");
        
        $player->sendForm($form);
    }
}