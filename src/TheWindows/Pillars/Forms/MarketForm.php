<?php
namespace TheWindows\Pillars\Forms;

use pocketmine\form\Form;
use pocketmine\player\Player;

class MarketForm implements Form {
    
    public function __construct() {
    }
    
    public function jsonSerialize(): array {
        return [
            "type" => "form",
            "title" => "§d§lMarket",
            "content" => "§eComing Soon...",
            "buttons" => [
                ["text" => "§cClose"]
            ]
        ];
    }
    
    public function handleResponse(Player $player, $data): void {
        
    }
    
    public static function createForm(): MarketForm {
        return new MarketForm();
    }
}