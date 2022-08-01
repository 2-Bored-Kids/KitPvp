<?php

namespace Adivius\KitPvp;

use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use cooldogedev\BedrockEconomy\libs\cooldogedev\libSQL\context\ClosureContext;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\ModalForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class FormManager {
	private Main $plugin;

	public function __construct(Main $plugin){
		$this->plugin = $plugin;
	}

	public function kitRemoveForm(Player $player, array $kits)
	{
		$form = new SimpleForm(function (Player $player, int $data = null) {
			if (is_null($data)) return true;
			$kitname = array_keys($this->plugin->kitManager->getKits())[$data];
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
				$this->plugin->kitManager->removeKit($kitName, $player);
				$this->plugin->cfgManager->updateConfig();
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
			$price = (int)array_values($this->plugin->kitManager->getKits())[$data]["price"];
			$name = (string)array_keys($this->plugin->kitManager->getKits())[$data];
			BedrockEconomyAPI::legacy()->subtractFromPlayerBalance($player->getName(), $price, ClosureContext::create(
				function (bool $wasUpdated) use ($player, $price, $name): void {
					if ($wasUpdated) {
						$this->plugin->kitManager->giveKit($player, $name);
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
			$this->plugin->kitManager->addKit($data[0], (int)$data[1], $player);
			$this->plugin->cfgManager->updateConfig();
		});
		$form->setTitle(TextFormat::GOLD . TextFormat::BOLD . "KitPvp");
		$form->addInput("Insert the name of the kit", "Name");
		$form->addSlider("Select a price for the kit", 25, 300, 25);
		$player->sendForm($form);
	}
}