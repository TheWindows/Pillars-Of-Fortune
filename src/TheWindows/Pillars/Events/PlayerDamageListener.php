<?php
namespace TheWindows\Pillars\Events;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use TheWindows\Pillars\Main;

class PlayerDamageListener implements Listener {
    
    private $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function onEntityDamage(EntityDamageEvent $event): void {
    $entity = $event->getEntity();
    
    if($entity instanceof Player) {
        $gameManager = $this->plugin->getGameManager();
        
        
        if($gameManager->isPlayerInvulnerable($entity)) {
            $event->cancel();
            return;
        }
        
        $playerState = $gameManager->checkPlayerState($entity);
        
        
        if($playerState === 'spectating') {
            $event->cancel();
            return;
        }
        
        
        if($playerState === 'waiting') {
            $event->cancel();
            return;
        }
        
        
        if($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            if($damager instanceof Player) {
                
                if($gameManager->isPlayerInvulnerable($damager)) {
                    $event->cancel();
                    return;
                }
                
                $damagerState = $gameManager->checkPlayerState($damager);
                if($damagerState === 'waiting' || $damagerState === 'spectating') {
                    $event->cancel();
                    return;
                }
            }
        }
        
        
        if($playerState === 'playing') {
            $newHealth = $entity->getHealth() - $event->getFinalDamage();
            if($newHealth <= 0.1) {
                $event->cancel();
                $entity->setHealth($entity->getMaxHealth());
                
                
                $gameId = $gameManager->getPlayerGame($entity);
                if($gameId !== null) {
                    $gameManager->handlePlayerDeath($entity, $gameId);
                }
            }
        }
    }
}
}
