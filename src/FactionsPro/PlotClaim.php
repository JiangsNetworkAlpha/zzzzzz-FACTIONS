<?php

namespace FactionsPro;

use pocketmine\plugin\PluginBase;
use pocketmine\event\player;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\utils\TextFormat;


class PlotClaim implements Listener {
	
	private $plugin;
	
	public function __construct(Main $pg) {
		$this->plugin = $pg;
	}
	//A test function used to debug
	public function onPlayerMove(PlayerMoveEvent $event) {
		$this->plugin->getServer()->getLogger()->info(TextFormat::BLUE . "EVENT WORKED SUCCESSFULLY!");
	}
}