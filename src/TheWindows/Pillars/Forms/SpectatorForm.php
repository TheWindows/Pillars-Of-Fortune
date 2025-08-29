<?php
namespace TheWindows\Pillars\Forms;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use TheWindows\Pillars\Main;

class SpectatorForm {
    
    public static function createForm(Main $plugin, Player $spectator, array $alivePlayers): SimpleForm {
        $form = new SimpleForm(function(Player $player, $data) use ($plugin, $alivePlayers) {
            if($data === null) return;
            
            if(isset($alivePlayers[$data])) {
                $target = $alivePlayers[$data];
                $plugin->getGameManager()->teleportSpectatorToPlayer($player, $target);
            }
        });
        
        $form->setTitle("§4§lSpectate Players");
        $form->setContent("§8Select a player to teleport to:");
        
        foreach($alivePlayers as $player) {
            if($player->isOnline()) {
                $form->addButton("§c" . $player->getName() . "\n§7Click to teleport");
            }
        }
        
        return $form;
    }
}