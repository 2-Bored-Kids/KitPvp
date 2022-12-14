<?php

declare(strict_types=1);

namespace Adivius\KitPvp;

use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use cooldogedev\BedrockEconomy\libs\cooldogedev\libSQL\context\ClosureContext;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Main extends PluginBase
{
	public ConfigManager $cfgManager;
	public KitManager $kitManager;
	public FormManager $formManager;
	public BountyManager $bountyManager;
    public Config $config;

    public function onEnable(): void
    {
		$this->saveResource("kits.json");
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();

		$this->kitManager = new KitManager();
		$this->cfgManager = new ConfigManager($this);
		$this->formManager = new FormManager($this);
		$this->bountyManager = new BountyManager($this);

        $this->cfgManager->readKitsfromConfig();

        $this->getServer()->getPluginManager()->registerEvents(new KitPvpEventListener($this), $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "kit":
				if (!isset($args[0]) && $sender->hasPermission('adivius.kitpvp.others')){
					$this->formManager->kitSelectForm($this->getServer()->getPlayerByPrefix($args[0]), $this->kitManager->getKits());
					return true;
				}

				if (!Utils::isPlayer($sender)) return true;

                $spawnPos = $this->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation();
                if ($sender->getPosition()->distance($spawnPos) > $this->config->get('spawn-radius')) {
                    $sender->sendMessage(TextFormat::RED . "You are too far from spawn to do that");
                    return true;
                }

                $this->formManager->kitSelectForm($sender, $this->kitManager->getKits());
                break;
            case "addkit":
				if (!Utils::isPlayer($sender)) return true;
                $this->formManager->kitAddForm($sender);
                break;
            case "removekit":
				if (!Utils::isPlayer($sender)) return true;
                $this->formManager->kitRemoveForm($sender, $this->kitManager->getKits());
                break;
            case 'reloadkits':
                try {
                    $this->cfgManager->readKitsfromConfig();
                    $sender->sendMessage(TextFormat::GREEN . "Kits have been reloaded");
                    break;
                } catch (\Exception $exception) {
                    $sender->sendMessage(TextFormat::RED . "Kits could`t be reloaded: " . $exception->getMessage());
                }
                break;
            case 'addbounty':
				if (!Utils::isPlayer($sender)) return true;
                if (count($args) < 2 || !is_numeric($args[1])) {
					$sender->sendMessage(TextFormat::RED . $command->getUsage());
					return true;
				}
                BedrockEconomyAPI::legacy()->subtractFromPlayerBalance($sender->getName(), (int)$args[1], ClosureContext::create(
                    function (bool $wasUpdated) use ($sender, $args): void {
                        if ($wasUpdated) {
                            $sender->sendMessage(TextFormat::RED . "-$args[0]$");
							$this->bountyManager->addBounty($args[0], $args[1]);
                        } else {
                            $sender->sendMessage(TextFormat::RED . "You don`t have enough money!");
                        }
                    }
                )
                );
                $this->getServer()->broadcastMessage(TextFormat::GOLD.$args[0]."'s bounty was increased to $". $this->bountyManager->getBounty($args[0]));
                break;
            case 'bounty':
				if (!Utils::isPlayer($sender)) return true;
                $sender->sendMessage(TextFormat::GOLD. "Your bounty is $".$this->bountyManager->getBounty($sender->getName()));
				break;
        }
        return true;
	}
}
