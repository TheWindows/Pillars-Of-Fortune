<?php
namespace TheWindows\Pillars\Forms;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use TheWindows\Pillars\Main;

class StatsForm {
    
    public static function createForm(Main $plugin, Player $player): SimpleForm {
        $stats = $plugin->getGameManager()->getPlayerStats($player);
        $wins = $stats['wins'] ?? 0;
        $coins = $stats['coins'] ?? 0;
        $kills = $stats['kills'] ?? 0;
        $deaths = $stats['deaths'] ?? 0;
        $gamesPlayed = $stats['games_played'] ?? 0;
        
        $winRate = $gamesPlayed > 0 ? round(($wins / $gamesPlayed) * 100, 1) : 0;
        
        $form = new SimpleForm(function(Player $player, $data) use ($plugin) {
            if($data === null) return;
        });
        
        $form->setTitle("§4§lPlayer Statistics");
        $form->setContent(
            "§8════════════════════\n" .
            "§4Player: §f" . $player->getName() . "\n" .
            "§8════════════════════\n\n" .
            "§6Wins: §e" . $wins . "\n" .
            "§6Coins: §e" . $coins . "\n" .
            "§6Kills: §e" . $kills . "\n" .
            "§6Deaths: §e" . $deaths . "\n" .
            "§6Games Played: §e" . $gamesPlayed . "\n" .
            "§6Win Rate: §e" . $winRate . "%\n\n" .
            "§8════════════════════"
        );
        $form->addButton("§cClose");
        
        return $form;
    }
}
