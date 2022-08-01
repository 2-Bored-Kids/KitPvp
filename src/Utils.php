<?php

namespace Adivius\KitPvp;


use pocketmine\player\Player;

class Utils {
	public static function clearAllInventories(Player $player) : void {
		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getCursorInventory()->clearAll();
		$player->getOffHandInventory()->clearAll();
	}
}