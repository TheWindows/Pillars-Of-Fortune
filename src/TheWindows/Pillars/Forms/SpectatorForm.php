<?php
namespace TheWindows\Pillars\Forms;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use TheWindows\Pillars\Main;

class SpectatorForm {
    
    private static $lastOpen = [];
    
    public static function createForm(Main $plugin, Player $spectator, array $alivePlayers): SimpleForm {
        $form = new SimpleForm(function(Player $player, $data) use ($plugin, $alivePlayers) {
            if($data === null) return;
            
            $now = time();
            if (isset(self::$lastOpen[$player->getId()]) && ($now - self::$lastOpen[$player->getId()]) < 1) {
                return;
            }
            self::$lastOpen[$player->getId()] = $now;
            
            if(isset($alivePlayers[$data])) {
                $target = $alivePlayers[$data];
                if($target->isOnline() && !$target->isClosed()) {
                    $plugin->getGameManager()->teleportSpectatorToPlayer($player, $target);
                } else {
                    $player->sendMessage("§cThat player is no longer online or has left the game!");
                }
            }
        });
        
        $form->setTitle("§4§lSpectate Players");
        $form->setContent("§8Select a player to teleport to:\n§7" . count($alivePlayers) . " players alive");
        
        foreach($alivePlayers as $player) {
            if($player->isOnline()) {
                $health = round($player->getHealth());
                $maxHealth = $player->getMaxHealth();
                $form->addButton("§c" . $player->getName() . "\n§8Click to teleport");
            }
        }
        
        return $form;
    }
}
