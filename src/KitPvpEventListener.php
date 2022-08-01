<?php

namespace Adivius\KitPvp;

use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\entity\projectile\Egg;
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
    public Main $plugin;
    private int $maxDistanceFromSpawn;

    public function __construct(Main $main)
    {
        $this->plugin = $main;
        $this->maxDistanceFromSpawn = $this->plugin->config->get('spawn-radius');
    }

    private function isInKitWorld(Entity $entity) : bool {
        return $entity->getWorld()->getFolderName() == $this->plugin->config->get('kitpvpworld');
    }

    public function onDamage(EntityDamageEvent $event)
    {
        $player = $event->getEntity();
        if (!$player instanceof Player) return;
        if ($event->getCause() === EntityDamageEvent::CAUSE_FALL && !$event instanceof EntityDamageByEntityEvent) {
            $event->cancel();
            return;
        }
        $spawnPos = $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation();
        if ($player->getPosition()->distance($spawnPos) < $this->maxDistanceFromSpawn) {
            $event->cancel();
            return;
        }

        $event->getEntity()->getWorld()->addParticle($player->getPosition()->asVector3(), new BlockBreakParticle(VanillaBlocks::REDSTONE()));
    }

    public function onHit(EntityDamageByEntityEvent $event)
    {
        if (!$this->isInKitWorld($event->getEntity())) {return;}

        $spawnPos = $event->getEntity()->getWorld()->getSpawnLocation();
        if ($event->getEntity()->getPosition()->distanceSquared($spawnPos) < $this->maxDistanceFromSpawn ** 2
        || $event->getDamager()->getPosition()->distanceSquared($spawnPos) < $this->maxDistanceFromSpawn ** 2
        ) {
            return;
        }

		$damager = $event->getDamager();
		$player = $event->getEntity();

        if (!$player instanceof Player) return;
        if (!$damager instanceof Player) return;
        if ($player === $damager) return;

        $damage = (int)$event->getFinalDamage();
        if ($damage <= 0) return;
        if ((int)$player->getHealth() <= $damage) {
            $addition = (int)$this->plugin->bountyManager->getBounty($player->getNameTag()) + 25;
            BedrockEconomyAPI::legacy()->addToPlayerBalance($damager->getNameTag(), $addition);
            $damager->sendMessage(TF::GREEN . "+". $addition);
            $this->plugin->bountyManager->addBounty($damager->getNameTag(), 50);
            $this->plugin->getServer()->broadcastMessage(TextFormat::GOLD.$damager->getNameTag()."'s bounty was increased to $". $this->plugin->bountyManager->getBounty($damager->getNameTag()));
        }else{
            BedrockEconomyAPI::legacy()->addToPlayerBalance($damager->getNameTag(), $damage);
            $damager->sendMessage(TF::GREEN . "+$damage");
        }

    }

    public function onProjectile(ProjectileHitEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity instanceof Egg) {
            $explosion = new Explosion($entity->getPosition(), 1);
            $explosion->explodeB();
        }
    }

    public function onDeath(PlayerDeathEvent $event)
    {
        if (!$this->isInKitWorld($event->getPlayer())) {return;}
        $player = $event->getPlayer();
        $event->setDrops([]);
        $event->setXpDropAmount(0);

        $pos = $player->getPosition();
        $lightning = AddActorPacket::create(Entity::nextRuntimeId(), Entity::nextRuntimeId(), "minecraft:lightning_bolt", $player->getPosition()->asVector3(), null, 0, 0, 0.0, 0.0, [], [], []);
        $particle = new BlockBreakParticle($player->getWorld()->getBlock($player->getPosition()->floor()->down()));
        $player->getWorld()->addParticle($pos, $particle, $player->getWorld()->getPlayers());
        $sound = PlaySoundPacket::create("ambient.weather.thunder", $pos->getX(), $pos->getY(), $pos->getZ(), 1, 1);
        Server::getInstance()->broadcastPackets($player->getWorld()->getPlayers(), [$lightning, $sound]);
        $this->plugin->bountyManager->setBounty($player->getName(), 0);
        $event->setDeathMessage('');
    }

    public function onBreak(BlockBreakEvent $event)
    {
        if (!$this->isInKitWorld($event->getPlayer())) {return;}
        if ($event->getPlayer()->getGamemode() !== GameMode::CREATIVE()) $event->cancel();
    }

    public function onDrop(PlayerDropItemEvent $event)
    {
        if (!$this->isInKitWorld($event->getPlayer())) {return;}
        if ($event->getPlayer()->getGamemode() !== GameMode::CREATIVE()) $event->cancel();
    }

    public function onCraft(CraftItemEvent $event)
    {
        if (!$this->isInKitWorld($event->getPlayer())) {return;}
        if ($event->getPlayer()->getGamemode() !== GameMode::CREATIVE()) $event->cancel();
    }

    public function onBed(PlayerBedEnterEvent $event)
    {
        if (!$this->isInKitWorld($event->getPlayer())) {return;}
        $event->cancel();
    }

    public function onShoot(ProjectileLaunchEvent $event)
    {
        if (!$this->isInKitWorld($event->getEntity())) {return;}
        $sender = $event->getEntity()->getOwningEntity();
        $spawnPos = $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation();
        if ($sender->getPosition()->distance($spawnPos) < $this->maxDistanceFromSpawn) {
            $event->cancel();
        }
    }

    public function onExplosion(ExplosionPrimeEvent $event){
        if (!$this->isInKitWorld($event->getEntity())) {return;}
        if($event->getEntity() instanceof PrimedTNT){
            $event->setBlockBreaking(false);
        }
    }

    public function onPlace(BlockPlaceEvent $event){
        if (!$this->isInKitWorld($event->getPlayer())) {return;}
        $block = $event->getBlock();
        $player = $event->getPlayer();
        $pos = $block->getPosition();
        if ($block->getId() == BlockLegacyIds::TNT){
            $spawnPos = $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation();
            if ($event->getBlock()->getPosition()->distance($spawnPos) < $this->maxDistanceFromSpawn) {
				$event->cancel();
                return;
            }
            $tnt = new PrimedTNT(new Location($pos->x + 0.5, $pos->y + 0.5, $pos->z + 0.5, $pos->getWorld(), 0, 0));
            $tnt->spawnToAll();
			$stack = $player->getInventory()->getItemInHand();
            $player->getInventory()->setItemInHand($stack->setCount($stack->getCount() - 1));
            $event->cancel();
        }
        if ($event->getPlayer()->getGamemode() !== GameMode::CREATIVE()) $event->cancel();
    }

    public function onPlayerJoin(PlayerLoginEvent $event)
    {
		$this->plugin->bountyManager->registerBounty($event->getPlayer()->getName());
    }
}