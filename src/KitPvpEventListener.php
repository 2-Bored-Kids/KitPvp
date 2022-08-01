<?php

namespace Adivius\KitPvp;

use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\entity\projectile\Egg;
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBedEnterEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\entity\Entity;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\Explosion;
use pocketmine\world\particle\BlockBreakParticle;

class KitPvpEventListener implements Listener
{
    public $plugin;
    private $radius = 8;

    public function __construct(Main $main)
    {
        $this->plugin = $main;
    }

    public function onDamage(EntityDamageEvent $event)
    {
        $player = $event->getEntity();
        if (!$player instanceof Player) return;
        if ($event->getCause() === EntityDamageEvent::CAUSE_FALL) {
            $event->cancel();
            return;
        }
        $spawnPos = $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation();
        if ($player->getPosition()->distance($spawnPos) < $this->radius) {
            $event->cancel();
            return;
        }

        $event->getEntity()->getWorld()->addParticle($player->getPosition()->asVector3(), new BlockBreakParticle(VanillaBlocks::REDSTONE()));
    }

    public function onHit(EntityDamageByEntityEvent $event)
    {
        $spawnPos = $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation();
        if ($event->getEntity()->getPosition()->distance($spawnPos) < $this->radius) {
            return;
        }
        if (!$event->getEntity() instanceof Player) return;
        if (!$event->getDamager() instanceof Player) return;
        if ($event->getEntity() == $event->getDamager()) return;
        $damager = $event->getDamager();
        $player = $event->getEntity();
        $damage = (int)$event->getFinalDamage();
        if ($damage <= 0) return;
        if ((int)$player->getHealth() <= $damage) {
            $addition = (int)$this->plugin->getBounty($player->getNameTag()) + 25;
            BedrockEconomyAPI::legacy()->addToPlayerBalance($damager->getNameTag(), $addition);
            $damager->sendMessage(TF::GREEN . "+". $addition);
            $this->plugin->addBounty($damager->getNameTag(), 50);
            $this->plugin->getServer()->broadcastMessage(TextFormat::GOLD.$damager->getNameTag()."'s bounty was increased to $". $this->plugin->getBounty($damager->getNameTag()));
        }else{
            BedrockEconomyAPI::legacy()->addToPlayerBalance($damager->getNameTag(), $damage);
            $damager->sendMessage(TF::GREEN . "+$damage");
        }

    }

    public function onProjectile(ProjectileHitEvent $event)
    {
        $entity = $event->getEntity();
        $owner = $entity->getOwningEntity();
        if ($entity instanceof EnderPearl)
            $owner->attack(new EntityDamageEvent($owner, EntityDamageEvent::CAUSE_PROJECTILE, 5));

        if ($entity instanceof Egg) {
            $explosion = new Explosion($entity->getPosition(), 1);
            $explosion->explodeB();
        }
    }


    public function onDeath(PlayerDeathEvent $event)
    {
        $player = $event->getPlayer();
        $event->setDrops([]);
        $event->setXpDropAmount(0);

        $pos = $player->getPosition();
        $light2 = AddActorPacket::create(Entity::nextRuntimeId(), 1, "minecraft:lightning_bolt", $player->getPosition()->asVector3(), null, $player->getLocation()->getYaw(), $player->getLocation()->getPitch(), 0.0, 0.0, [], [], []);
        $block = $player->getWorld()->getBlock($player->getPosition()->floor()->down());
        $particle = new BlockBreakParticle($block);
        $player->getWorld()->addParticle($pos, $particle, $player->getWorld()->getPlayers());
        $sound2 = PlaySoundPacket::create("ambient.weather.thunder", $pos->getX(), $pos->getY(), $pos->getZ(), 1, 1);
        Server::getInstance()->broadcastPackets($player->getWorld()->getPlayers(), [$light2, $sound2]);
        $this->plugin->setBounty($player->getName(), 0);
    }

    public function onBreak(BlockBreakEvent $event)
    {
        if ($event->getPlayer()->getGamemode() !== GameMode::CREATIVE()) $event->cancel();
    }

    public function onDrop(PlayerDropItemEvent $event)
    {
        if ($event->getPlayer()->getGamemode() !== GameMode::CREATIVE()) $event->cancel();
    }

    public function onCraft(CraftItemEvent $event)
    {
        if ($event->getPlayer()->getGamemode() !== GameMode::CREATIVE()) $event->cancel();
    }

    public function onBed(PlayerBedEnterEvent $event)
    {
        $event->cancel();
    }

    public function onShoot(ProjectileLaunchEvent $event)
    {
        $sender = $event->getEntity()->getOwningEntity();
        $spawnPos = $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation();
        if ($sender->getPosition()->distance($spawnPos) < $this->radius) {
            $event->cancel();
        }
    }

    public function onExplosion(ExplosionPrimeEvent $event){
        if($event->getEntity() instanceof PrimedTNT){
            $event->setBlockBreaking(false);
        }
    }

    public function onPlace(BlockPlaceEvent $event){



        $block = $event->getBlock();
        $player = $event->getPlayer();
        $pos = $block->getPosition();
        if ($block->getId() == VanillaBlocks::TNT()->getId()){
            $spawnPos = $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation();
            if ($event->getBlock()->getPosition()->distance($spawnPos) < $this->radius) {
                $event->cancel();
            }
            $player->sendMessage("Tnt");
            $tnt = new PrimedTNT(new Location($pos->x + 1, $pos->y + 1, $pos->z + 1, $pos->getWorld(), 0, 0));
            $tnt->spawnToAll();
            $event->cancel();
        }
        if ($event->getPlayer()->getGamemode() !== GameMode::CREATIVE()) $event->cancel();
    }

    public function onPlayerJoin(PlayerLoginEvent $event)
    {
        $player = $event->getPlayer()->getName();
        $result = $this->plugin->bountyDB->query("SELECT 1 FROM BOUNTY WHERE name = '$player'");
        if (!isset($result->fetchArray()[0])) {
            $this->plugin->registerBounty($event->getPlayer()->getName());
        }
    }
}