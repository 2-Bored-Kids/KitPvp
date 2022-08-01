<?php

namespace Adivius\KitPvp;

use pocketmine\utils\Config;

class ConfigManager {
	private Main $plugin;

	public function __construct(Main $plugin){
		$this->plugin = $plugin;
	}

	public function readKitsfromConfig()
	{
		$cfg = new Config($this->plugin->getDataFolder() . "kits.json", Config::JSON);
		$this->plugin->kitManager->setKits($cfg->getAll());
	}

	public function updateConfig()
	{
		$config = new Config($this->plugin->getDataFolder() . "kits.json", Config::JSON);
		$config->setAll($this->plugin->kitManager->getKits());
		$config->save();
	}
}