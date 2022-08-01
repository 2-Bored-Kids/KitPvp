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
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use SQLite3;

class Main extends PluginBase
{
    public SQLite3 $bountyDB;
	public ConfigManager $cfgManager;
	public KitManager $kitManager;

    public function onEnable(): void
    {
		$this->saveResource("kits.json");

		$this->kitManager = new KitManager($this);
		$this->cfgManager = new ConfigManager($this);

        $this->cfgManager->readKitsfromConfig();

        $this->getServer()->getPluginManager()->registerEvents(new KitPvpEventListener($this), $this);

        $this->bountyDB = new SQLite3($this->getDataFolder() . 'bounty.db');
		$this->bountyDB->exec('CREATE TABLE IF NOT EXISTS BOUNTY (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, bounty INTEGER NOT NULL);');
    }

    //BOUNTY:
    public function registerBounty(string $playername){
        $query = $this->bountyDB->prepare("INSERT INTO 'main'.'BOUNTY' ('name', 'bounty') VALUES ('playername', 0)");
		$query->bindValue('playername', $playername);
		$query->execute();
    }

    public function getBounty(string $playername)
    {
		$query = $this->bountyDB->prepare("SELECT bounty FROM BOUNTY WHERE name = 'playername'");
		$query->bindValue('playername', $playername);
        return $query->execute()->fetchArray()[0];
    }

    public function setBounty(string $playername, int $bounty){
        $query = $this->bountyDB->prepare("UPDATE BOUNTY SET bounty = $bounty WHERE name = 'playername'");
		$query->bindValue('playername', $playername);
		return $query->execute();
    }

    public function addBounty(string $playername, int $bounty){
        $query = $this->bountyDB->prepare("UPDATE BOUNTY SET bounty = bounty + $bounty WHERE name = 'playername'");
		$query->bindValue('playername', $playername);
		return $query->execute();
    }

	private function isPlayer(CommandSender $sender) :bool{
		if (!$sender instanceof Player) {
			$sender->sendMessage(TextFormat::RED.'Please, use this command in-game.');
			return false;
		}
		return true;
	}

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "kit":
				if (!isset($args[0]) && $sender->hasPermission('kit.others')){
					$this->kitSelectForm($this->getServer()->getPlayerByPrefix($args[0]), $this->kitManager->getKits());
					return true;
				}

                $spawnPos = $this->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation();
                if ($sender->getPosition()->distance($spawnPos) > 8) {
                    $sender->sendMessage(TextFormat::RED . "You are too far from spawn to do that");
                    return true;
                }

                $this->kitSelectForm($sender, $this->kitManager->getKits());
                break;
            case "addkit":
				if (!$this->isPlayer($sender)) return true;
                $this->kitAddForm($sender);
                break;
            case "removekit":
				if (!$this->isPlayer($sender)) return true;
                $this->kitRemoveForm($sender, $this->kitManager->getKits());
                break;
            case "spawn":
                if (!$this->isPlayer($sender)) return true;
                $sender->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn()->addVector(new Vector3(0.5, 0.5, 0.5)));
                $sender->sendMessage(TextFormat::GREEN . "You have been teleported to the spawn!");
                break;
            case "clear":
				if (!$this->isPlayer($sender)) return true;
                Utils::clearAllInventories($sender);
                $sender->sendMessage(TextFormat::GREEN . "Inventory cleared!");
                break;
            case 'updatefromconfig':
                try {
                    $this->cfgManager->readKitsfromConfig();
                    $sender->sendMessage(TextFormat::GREEN . "Kits have been reloaded");
                    break;
                } catch (\Exception $exception) {
                    $sender->sendMessage(TextFormat::RED . "Kits couldn`t be reloaded: " . $exception->getMessage());
                }
                break;
            case 'addbounty':
				if (!$this->isPlayer($sender)) return true;
                if (count($args) < 2 || !is_numeric($args[1])) {
					$sender->sendMessage(TextFormat::RED . $command->getUsage());
					return true;
				}
                BedrockEconomyAPI::legacy()->subtractFromPlayerBalance($sender->getName(), (int)$args[1], ClosureContext::create(
                    function (bool $wasUpdated) use ($sender, $args): void {
                        if ($wasUpdated) {
                            $sender->sendMessage(TextFormat::RED . "-$args[0]$");
							$this->addBounty($args[0], $args[1]);
                        } else {
                            $sender->sendMessage(TextFormat::RED . "You don`t have enough money!");
                        }
                    }
                )
                );
                $this->getServer()->broadcastMessage(TextFormat::GOLD.$args[0]."'s bounty was increased to $". $this->getBounty($args[0]));
                break;
            case 'bounty':
				if (!$this->isPlayer($sender)) return true;
                $sender->sendMessage(TextFormat::GOLD. "Your bounty is $".$this->getBounty($sender->getName()));
				break;
        }
        return true;
    }

    //FormManager:
    public function kitRemoveForm(Player $player, array $kits)
    {
        $form = new SimpleForm(function (Player $player, int $data = null) {
            if (is_null($data)) return true;
            $kitname = array_keys($this->kitManager->getKits())[$data];
            $this->confirmDeleteForm($player, $kitname);
        });
        $form->setTitle(TextFormat::GOLD . TextFormat::BOLD . "KitPvp");
        $form->setContent("Choose the kit you want to delete!");
        foreach ($kits as $key => $kit) {
            $form->addButton($key);
        }
        $player->sendForm($form);
    }

    public function confirmDeleteForm(Player $player, string $kitName){
        $form = new ModalForm(function (Player $player, bool $data) use ($kitName){
            if ($data){
                $this->kitManager->removeKit($kitName, $player);
				$this->cfgManager->updateConfig();
            }else{
                $player->sendMessage(TextFormat::GOLD."Cancelled");
            }
        });
        $form->setTitle(TextFormat::GOLD . TextFormat::BOLD . "KitPvp");
        $form->setContent("Are you sure you want to delete $kitName");
        $form->setButton1(TextFormat::RED."Delete");
        $form->setButton2("Cancel");
		$player->sendForm($form);
    }

    public function kitSelectForm(Player $player, array $kits)
    {
        $form = new SimpleForm(function (Player $player, int $data = null) {
            if (is_null($data)) return true;
            $price = (int)array_values($this->kitManager->getKits())[$data]["price"];
            $name = (string)array_keys($this->kitManager->getKits())[$data];
            BedrockEconomyAPI::legacy()->subtractFromPlayerBalance($player->getName(), $price, ClosureContext::create(
                function (bool $wasUpdated) use ($player, $price, $name): void {
                    if ($wasUpdated) {
                        $this->kitManager->giveKit($player, $name);
                        $player->sendMessage(TextFormat::RED . "-$price$");
                    } else {
                        $player->sendMessage(TextFormat::RED . "You don`t have enough money!");
                    }
                }
            )
            );
        });
        $form->setTitle(TextFormat::GOLD . TextFormat::BOLD . "KitPvp");
        $form->setContent("Choose the kit you want to play with:");
        foreach ($kits as $key => $kit) {
            $form->addButton($key . TextFormat::RESET ." $" . $kit['price']);
        }
        $player->sendForm($form);
    }

    public function kitAddForm(Player $player)
    {
        $form = new CustomForm(function (Player $player, array $data = null) {
            if (is_null($data)) return true;
            $this->kitManager->addKit($data[0], (int)$data[1], $player);
			$this->cfgManager->updateConfig();
        });
        $form->setTitle(TextFormat::GOLD . TextFormat::BOLD . "KitPvp");
        $form->addInput("Insert the name of the kit", "Name");
        $form->addSlider("Select a price for the kit", 25, 300, 25);
        $player->sendForm($form);
    }
}

