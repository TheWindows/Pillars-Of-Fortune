<?php
namespace TheWindows\Pillars\Entity;

use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use TheWindows\Pillars\Forms\GameMenuForm;
use TheWindows\Pillars\Main;

class PillarsNPC extends Human {

    private $plugin;
    private $fixedPosition;

    public function __construct(Location $location, Skin $skin, ?CompoundTag $nbt = null) {
        parent::__construct($location, $skin, $nbt);
        $this->plugin = Main::getInstance();
        $this->fixedPosition = $location->asVector3();
        
        
        $this->setNameTag("§4Pillars Minigame\n§7Click To Join");
        $this->setNameTagAlwaysVisible(true);
        $this->setScale(1.5);
        $this->setNoClientPredictions(true);
        $this->setCanSaveWithChunk(false);
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        
        if ($nbt->getTag("Pos") instanceof ListTag) {
            $pos = $nbt->getListTag("Pos");
            $this->fixedPosition = new Vector3(
                $pos->get(0)->getValue(),
                $pos->get(1)->getValue(),
                $pos->get(2)->getValue()
            );
        }
    }

    public function attack(EntityDamageEvent $source): void {
        $source->cancel();
        if ($source instanceof EntityDamageByEntityEvent) {
            $damager = $source->getDamager();
            if ($damager instanceof Player) {
                $form = GameMenuForm::createForm($this->plugin, $damager);
                $form->sendToPlayer($damager);
            }
        }
    }

    public function onInteract(Player $player, Vector3 $clickPos): bool {
        $form = GameMenuForm::createForm($this->plugin, $player);
        $form->sendToPlayer($player);
        return true;
    }

    public function onUpdate(int $currentTick): bool {
        
        if ($this->fixedPosition !== null) {
            $this->setPosition($this->fixedPosition);
            $this->updateMovement();
        }
        return parent::onUpdate($currentTick);
    }

    public function move(float $dx, float $dy, float $dz): void {
        
    }

    public function knockBack(float $x, float $z, float $force = 0.4, ?float $verticalForce = null): void {
        
    }

    public function setMotion(Vector3 $motion): bool {
        
        return false;
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();
        if ($this->fixedPosition !== null) {
            $nbt->setTag("Pos", new ListTag([
                new FloatTag($this->fixedPosition->x),
                new FloatTag($this->fixedPosition->y),
                new FloatTag($this->fixedPosition->z)
            ]));
        }
        return $nbt;
    }
}