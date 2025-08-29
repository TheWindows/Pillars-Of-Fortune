<?php
namespace TheWindows\Pillars\Forms;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use TheWindows\Pillars\Main;

class GameMenuForm {
    
    public static function createForm(Main $plugin, Player $player): SimpleForm {
        $form = new SimpleForm(function(Player $player, $data) use ($plugin) {
            if($data === null) return;
            
            $gameManager = $plugin->getGameManager();
            $availableGames = $gameManager->getAvailableGames();
            
            if(isset($availableGames[$data])) {
                $gameId = $availableGames[$data]['id'];
                $gameManager->addPlayerToGame($player, $gameId);
            }
        });
        
        $form->setTitle("§4§lPillars Minigame");
        $form->setContent("§8Select a game to join:");
        
        $gameManager = $plugin->getGameManager();
        $availableGames = $gameManager->getAvailableGames();
        
        if(empty($availableGames)) {
            $form->addButton("§cNo games available\n§8Create one first");
        } else {
            foreach($availableGames as $index => $game) {
                $players = $game['players'];
                $maxPlayers = $game['max_players'];
                $status = $game['status'];
                
                
                $color = "§c"; 
                if ($players >= $maxPlayers) {
                    $color = "§4"; 
                } elseif ($players >= $maxPlayers * 0.7) {
                    $color = "§6"; 
                }
                
                $form->addButton("{$color}{$game['world']}\n§8{$players}/{$maxPlayers} players §7- {$status}");
            }
        }
        
        return $form;
    }
}