<?php

namespace Adivius\KitPvp;

use SQLite3;

class BountyManager {
	private SQLite3 $bountyDB;

	public function __construct(Main $plugin){
		$this->bountyDB = new SQLite3($plugin->getDataFolder() . 'bounty.db');
		$this->bountyDB->exec('CREATE TABLE IF NOT EXISTS BOUNTY (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, bounty INTEGER NOT NULL);');
	}

	public function registerBounty(string $playername){
		$query = $this->bountyDB->query("SELECT bounty FROM BOUNTY WHERE name = 'playername'");
		$query->bindValue('playername', $playername);

		if (isset($query->execute()->fetchArray()[0])) {
			return;
		}

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
}