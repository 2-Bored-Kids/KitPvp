<?php

declare(strict_types=1);

namespace Adivius\KitPvp;

use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use cooldogedev\BedrockEconomy\libs\cooldogedev\libSQL\context\ClosureContext;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\ModalForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\ItemFactory;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use SQLite3;

class Main extends PluginBase
{
    private $kitsConfig;
    private $kits = [];
    public $bountyDB;

    public function onEnable(): void
    {
        $this->reloadKitsfromConfig();
        $this->getLogger()->info(TextFormat::GREEN."KitPvp started correctly");
        $this->getServer()->getPluginManager()->registerEvents(new KitPvpEventListener($this), $this);
        $this->bountyDB = new SQLite3($this->getDataFolder() . 'bounty.db');
        $this->bountyDB->exec('CREATE TABLE IF NOT EXISTS BOUNTY (
	"id"	INTEGER,
	"name"	TEXT,
	"bounty"	INTEGER NOT NULL,
	PRIMARY KEY("id" AUTOINCREMENT)
);');
    }

    //ConfigManager:
    public function reloadKitsfromConfig()
    {
        $this->saveResource("kits.json");
        $this->kitsConfig = new Config($this->getDataFolder() . "kits.json", Config::JSON);
        $this->kits = $this->kitsConfig->getAll();
    }

    private function updateKitsConfig()
    {
        $this->kitsNames = [];
        foreach ($this->kits as $item) {
            $this->kitsNames[] = $item['name'];
        }
        $this->kitsConfig->setAll($this->kits);
        $this->kitsConfig->save();
        $this->saveResource("kits.json");
    }

    //BOUNTY:
    public function registerBounty(string $playername){
        $this->bountyDB->exec("INSERT INTO 'main'.'BOUNTY' ('name', 'bounty') VALUES ('$playername', 0)");
    }
    public function getBounty(string $playername)
    {
        $result = $this->bountyDB->query("SELECT bounty FROM BOUNTY WHERE name = '$playername'")->fetchArray()[0];
        return $result;
    }
    public function setBounty(string $playername, int $bounty){
        $this->bountyDB->query("UPDATE BOUNTY SET bounty = $bounty WHERE name = '$playername'");
    }
    public function addBounty(string $playername, int $bounty){
        $this->bountyDB->query("UPDATE BOUNTY SET bounty = bounty + $bounty WHERE name = '$playername'");
    }


    //Commands:
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "kit":
                if (!$sender instanceof Player) {
                    if (!isset($args[0])) return true;
                    $this->kitSelectForm($this->getServer()->getPlayerExact($args[0]), $this->kits);
                    return true;
                }
                $spawnPos = $this->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation();
                if ($sender->getPosition()->distance($spawnPos) > 8) {
                    $sender->sendMessage(TextFormat::RED . "Not allowed");
                    return true;
                }
                $this->kitSelectForm($sender, $this->kits);
                break;
            case "addkit":
                if (!$sender instanceof Player) return true;
                $this->kitAddForm($sender);
                break;
            case "removekit":
                if (!$sender instanceof Player) return true;
                $this->kitRemoveForm($sender, $this->kits);
                break;

            case "spawn":
                if (!$sender instanceof Player) return true;
                $sender->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn()->addVector(new Vector3(0.5, 0.5, 0.5)));
                $sender->sendMessage(TextFormat::GREEN . "You have been teleported to the spawn!");
                break;
            case "clear":
                if (!$sender instanceof Player) return true;
                $sender->getInventory()->clearAll();
                $sender->getArmorInventory()->clearAll();
                $sender->sendMessage(TextFormat::GREEN . "Inventory cleared!");
                break;
            case "damage":
                if (!$sender instanceof Player) return true;
                if (!isset($args[0]) || !is_numeric($args[0])) {
                    $sender->sendMessage(TextFormat::RED . $command->getUsage());
                    return true;
                }
                $sender->attack(new EntityDamageEvent($sender, EntityDamageEvent::CAUSE_SUICIDE, (float)$args[0]));
                $sender->sendMessage(TextFormat::GREEN . "You took $args[0] damage");
                break;
            case "updatefromconfig":
                try {
                    $this->saveResource("kits.json");
                    $this->kitsConfig = new Config($this->getDataFolder() . "kits.json", Config::JSON);
                    $this->kits = $this->kitsConfig->getAll();
                    $this->updateKitsConfig();
                    $sender->sendMessage(TextFormat::GREEN . "Config was updated!");
                    break;
                } catch (\Exception $exception) {
                    $sender->sendMessage(TextFormat::RED . "Config cant be updated: " . $exception->getMessage());
                }
                break;
            case "addbounty":
                if (!$sender instanceof Player) return true;
                if (!isset($args[0]) || !isset($args[1]) || !is_numeric($args[1])) return true;
                $value = $args[1];
                BedrockEconomyAPI::legacy()->subtractFromPlayerBalance($sender->getName(), $value, ClosureContext::create(
                    function (bool $wasUpdated) use ($sender, $value): void {
                        if ($wasUpdated) {
                            $sender->sendMessage(TextFormat::RED . "-$value");
                        } else {
                            $sender->sendMessage(TextFormat::RED . "You have not enough money!");
                        }
                    }
                )
                );
                $playername = $args[0];
                $percent = 0.25 * (int)$args[1];
                $bounty = (int)$args[1] + (int)$percent;
                $this->addBounty($playername, $bounty);
                $this->getServer()->broadcastMessage(TextFormat::GOLD.$playername."'s bounty was increased to $". $this->getBounty($playername));
                break;
            case "bounty":
                if (!$sender instanceof Player) return true;
                $playername = $sender->getName();
                $sender->sendMessage(TextFormat::GOLD. "Your bounty is $".$this->getBounty($playername));
        }
        return true;
    }
    //money
    public function MoneyRemove(Player $player, int $value)
    {
        BedrockEconomyAPI::legacy()->subtractFromPlayerBalance($player->getName(), $value, ClosureContext::create(
            function (bool $wasUpdated) use ($player, $value): void {
                if ($wasUpdated) {
                    $player->sendMessage(TextFormat::RED . "-$value");
                } else {
                    $player->sendMessage(TextFormat::RED . "You have not enough money!");
                }
            }
        )
        );
    }


    //KitManager:
    public function getKit(Player $player, string $nameKit)
    {
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->setHealth($player->getMaxHealth());
        $player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
        $player->getEffects()->clear();
        $player->sendMessage(TextFormat::GREEN . "You received the Kit $nameKit!");
        foreach ($this->kits[$nameKit]["items"] as $item) {
            $data = explode(":", $item);
            $itemBuilder = ItemFactory::getInstance()->get((int)$data[0], (int)$data[1], (int)$data[2]);
            $enchantIDs = explode(",", $data[3]);
            $enchantLevel = explode(",", $data[4]);
            for ($i = 0; $i < sizeof($enchantIDs); $i++) {
                if ((int)$enchantLevel[$i] < 1 || (int)$enchantIDs[$i] < 1) break;
                $itemBuilder->addEnchantment(new EnchantmentInstance(EnchantmentIdMap::getInstance()->fromId((int)$enchantIDs[$i]), (int)$enchantLevel[$i]));
            }
            $player->getInventory()->addItem($itemBuilder);
        }
        foreach ($this->kits[$nameKit]["armor"] as $armor) {
            $result = explode(":", $armor);
            $armorItem = ItemFactory::getInstance()->get((int)$result[0], (int)$result[1], (int)$result[2]);
            $armorEnchantIDs = explode(",", $result[3]);
            $armorEnchantLevel = explode(",", $result[4]);
            for ($j = 0; $j < sizeof($armorEnchantIDs); $j++) {
                if ((int)$armorEnchantLevel[$j] < 1) break;
                $armorItem->addEnchantment(new EnchantmentInstance(EnchantmentIdMap::getInstance()->fromId((int)$armorEnchantIDs[$j]), (int)$armorEnchantLevel[$j]));
            }
            $player->getArmorInventory()->addItem($armorItem);
        }
    }

    private function addKit(string $name, int $price, Player $player)
    {
        //ITEMS:
        $items = array();
        foreach ($player->getInventory()->getContents() as $content) {
            $en = [];
            $level = [];
            foreach ($content->getEnchantments() as $enchantment) {
                $en[] = EnchantmentIdMap::getInstance()->toId($enchantment->getType());
                $level[] = $enchantment->getLevel();
            }
            $en = implode(",", $en);
            $level = implode(",", $level);
            $id = $content->getId();
            $meta = $content->getMeta();
            $count = $content->getCount();
            $items[] = implode(":", [$id, $meta, $count, $en, $level]);
        }
        //ARMOR:
        $armor = array();
        foreach ($player->getArmorInventory()->getContents() as $content) {
            $en = [];
            $level = [];
            foreach ($content->getEnchantments() as $enchantment) {
                $en[] = EnchantmentIdMap::getInstance()->toId($enchantment->getType());
                $level[] = $enchantment->getLevel();
            }
            $en = implode(",", $en);
            $level = implode(",", $level);
            $id = $content->getId();
            $meta = $content->getMeta();
            $count = $content->getCount();
            $armor[] = implode(":", [$id, $meta, $count, $en, $level]);
        }


        $kit = ["name" => $name, "price" => $price, "items" => $items, "armor" => $armor];
        $this->kits[$name] = $kit;
        $this->updateKitsConfig();
        $player->sendMessage(TextFormat::GREEN . "The Kit $name" . TextFormat::GREEN ." was created!");
    }

    private function removeKit(string $kitName, Player $player)
    {
        unset($this->kits[$kitName]);
        $this->updateKitsConfig();
        $player->sendMessage(TextFormat::GREEN . "The Kit $kitName" . TextFormat::GREEN ." was removed!");
    }

    //FormManager:

    public function kitRemoveForm(Player $player, array $kits)
    {
        $form = new SimpleForm(function (Player $player, int $data = null) {
            if (is_null($data)) return true;
            $kitname = array_values($this->kits)[$data]["name"];
            $this->confirmDeleteForm($player, $kitname);
        });
        $form->setTitle(TextFormat::GOLD . TextFormat::BOLD . "KitPvp");
        $form->setContent("Choose the kit you want to delete!");
        foreach ($kits as $kit) {
            $form->addButton($kit['name']);
        }
        $form->sendToPlayer($player);
        return $form;
    }

    public function confirmDeleteForm(Player $player, string $kitName){
        $form = new ModalForm(function (Player $player, bool $data) use ($kitName){
            if ($data){
                $this->removeKit($kitName, $player);
            }else{
                $player->sendMessage(TextFormat::GOLD."Deleting was canceled");
            }
        });
        $form->setTitle(TextFormat::GOLD . TextFormat::BOLD . "KitPvp");
        $form->setContent("Are you sure to delete $kitName");
        $form->setButton1(TextFormat::RED."Delete");
        $form->setButton2("Cancel");
        $form->sendToPlayer($player);
        return $form;
    }

    public function kitSelectForm(Player $player, array $kits)
    {
        $form = new SimpleForm(function (Player $player, int $data = null) {
            if (is_null($data)) return true;
            $price = (int)array_values($this->kits)[$data]["price"];
            $name = (string)array_values($this->kits)[$data]["name"];
            BedrockEconomyAPI::legacy()->subtractFromPlayerBalance($player->getName(), $price, ClosureContext::create(
                function (bool $wasUpdated) use ($player, $price, $name): void {
                    if ($wasUpdated) {
                        $this->getKit($player, $name);
                        $player->sendMessage(TextFormat::RED . "-$price");
                    } else {
                        $player->sendMessage(TextFormat::RED . "You have not enough money!");
                    }
                }
            )
            );

        });
        $form->setTitle(TextFormat::GOLD . TextFormat::BOLD . "KitPvp");
        $form->setContent("Choose the kit you want to play with:");
        foreach ($kits as $kit) {
            $form->addButton($kit['name'] . TextFormat::RESET ." $" . $kit['price']);
        }
        $player->sendForm($form);
        return $form;
    }

    public function kitAddForm(Player $player)
    {
        $form = new CustomForm(function (Player $player, array $data = null) {
            if (is_null($data)) return true;
            $this->addKit($data[0], (int)$data[1], $player);
        });
        $form->setTitle(TextFormat::GOLD . TextFormat::BOLD . "KitPvp");
        $form->addInput("Insert the name of the kit", "Name");
        $form->addSlider("Select a price for the kit", 25, 300, 25);
        $form->sendToPlayer($player);
        return $form;
    }
}

