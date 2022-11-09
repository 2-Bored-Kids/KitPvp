<?php

namespace Adivius\KitPvp;

use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\inventory\Inventory;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\block\VanillaBlocks;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class KitManager {
	private array $kits = [];

	public function setKits(array $kits) :void
	{
		$this->kits = $kits;
	}

	public function getKits() :array
	{
		return $this->kits;
	}

	private function buildItem(string $arr) : Item{
		$data = explode(":", $arr);
		$itemBuilder = ItemFactory::getInstance()->get((int)$data[0], (int)$data[1], (int)$data[2]);
		$enchantIDs = explode(",", $data[3]);
		$enchantLevel = explode(",", $data[4]);
		for ($i = 0; $i < count($enchantIDs); $i++) {
			if ((int)$enchantLevel[$i] < 1) {
				//TODO: Hack!
				continue;
			}
			try {
				$itemBuilder->addEnchantment(new EnchantmentInstance(EnchantmentIdMap::getInstance()->fromId((int) $enchantIDs[$i]), (int) $enchantLevel[$i]));
			} catch (\Exception) {
				//TODO: There has to be a better way
				return VanillaBlocks::AIR()->asItem();
			}
		}
		return $itemBuilder;
	}

	//TODO: gen items and store them on load
	public function giveKit(Player $player, string $nameKit) :void
	{
		Utils::clearAllInventories($player);
		$player->setHealth($player->getMaxHealth());
		$player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
		$player->getEffects()->clear();
		$player->sendMessage(TextFormat::GREEN . "You received the $nameKit".TextFormat::RESET.TextFormat::GREEN." kit!");

		//???? WTF
		//Format: id:meta:count:enchant1,enchant2:enchant1-level,enchant2-level

		foreach ($this->kits[$nameKit]['items'] as $item) {
			$player->getInventory()->addItem($this->buildItem($item));
		}

		foreach ($this->kits[$nameKit]['armor'] as $armor) {
			$player->getArmorInventory()->addItem($this->buildItem($armor));
		}
	}

	private function inventoryToArray(Inventory $inv) :array {
		$arr = [];

		foreach ($inv->getContents() as $content) {
			$enchantments = '';
			$levels = '';
			foreach ($content->getEnchantments() as $enchantment) {
				$enchantments .= EnchantmentIdMap::getInstance()->toId($enchantment->getType()).',';
				$levels .= $enchantment->getLevel().',';
			}

			$arr[] = implode(":", [$content->getId(), $content->getMeta(), $content->getCount(), substr($enchantments, -1), substr($levels, -1)]);
		}

		return $arr;
	}

	public function addKit(string $name, int $price, Player $player) :void
	{
		$kit = ["name" => $name, "price" => $price, "items" => $this->inventoryToArray($player->getInventory()), "armor" => $this->inventoryToArray($player->getArmorInventory())];
		$this->kits[$name] = $kit;
		$player->sendMessage(TextFormat::GREEN . "The kit $name" . TextFormat::RESET.TextFormat::GREEN ." has been created!");
	}

	public function removeKit(string $kitName, Player $player) :void
	{
		unset($this->kits[$kitName]);
		$player->sendMessage(TextFormat::GREEN . "The kit $kitName" . TextFormat::RESET.TextFormat::GREEN ." has been removed!");
	}
}