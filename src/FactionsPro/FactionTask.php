<?php

namespace FactionsPro;

use pocketmine\scheduler\PluginTask;

class FactionTask extends PluginTask {
	
	public function onRun($currentTick) {
		$onlinePlayers = $this->getOwner()->getServer()->getOnlinePlayers();
		$this->getOwner()->plotChecker($onlinePlayers);
	}
}