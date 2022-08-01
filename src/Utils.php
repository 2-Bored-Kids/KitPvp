<?php

namespace Adivius\KitPvp;

use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class Utils {
	public static function clearAllInventories(Player $player) : void {
		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getCursorInventory()->clearAll();
		$player->getOffHandInventory()->clearAll();
	}

	//TODO: rename?
	public static function isPlayer(CommandSender $sender) :bool{
		if (!$sender instanceof Player) {
			$sender->sendMessage(TextFormat::RED.'Please, use this command in-game.');
			return false;
		}
		return true;
	}
}