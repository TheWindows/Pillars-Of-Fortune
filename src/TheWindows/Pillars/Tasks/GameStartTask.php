<?php
namespace TheWindows\Pillars\Tasks;

use pocketmine\scheduler\Task;
use pocketmine\player\Player;
use TheWindows\Pillars\Main;

class GameStartTask extends Task {
    
    private $plugin;
    private $players;
    
    public function __construct(Main $plugin, array $players) {
        $this->plugin = $plugin;
        $this->players = $players;
    }
    
    public function onRun(): void {
        $spawnPoints = $this->plugin->getSpawnManager()->getSpawnPoints();
        
        foreach($this->players as $index => $player) {
            if(isset($spawnPoints[$index])) {
                $player->teleport($spawnPoints[$index]);
            }
        }
    }
}