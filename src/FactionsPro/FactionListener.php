<?php

namespace FactionsPro;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use FactionsPro\utils\Session;


class FactionListener implements Listener {
	
	public $plugin;
	
	public function __construct(FactionMain $pg) {
		$this->plugin = $pg;
	}
	
	public function onJoin(PlayerLoginEvent $event)
	{
		$this->plugin->addSession($event->getPlayer());
	}
	
	public function onQuit(PlayerQuitEvent $event)
	{
		$this->plugin->removeSession($event->getPlayer());
	}
	
	public function factionChat(PlayerChatEvent $PCE) {
		$faction = $this->plugin->getSession($PCE->getPlayer())->getFaction();
		if($faction == null) {
			$PCE->setFormat($PCE->getPlayer()->getName() . ": " . $PCE->getMessage());
		} else {
			$PCE->setFormat("[" . $faction->getName() . "] " . $PCE->getPlayer()->getName() . ": " . $PCE->getMessage());
		}
		return true;
		
		$player = strtolower($PCE->getPlayer()->getName());
	}
	
	public function factionPVP(EntityDamageEvent $factionDamage) {
		if($factionDamage instanceof EntityDamageByEntityEvent) {
			if(!($factionDamage->getEntity() instanceof Player) or !($factionDamage->getDamager() instanceof Player)) {
				return true;
			}
			if((!$this->plugin->getSession($factionDamage->getEntity()->getPlayer())->inFaction()) or (!$this->plugin->getSession($factionDamage->getDamager()->getPlayer()))) {
				return true;
			}
			if(($factionDamage->getEntity() instanceof Player) and ($factionDamage->getDamager() instanceof Player)) {
				$player1 = $factionDamage->getEntity()->getPlayer()->getName();
				$player2 = $factionDamage->getDamager()->getPlayer()->getName();
				if($this->plugin->sameFaction($player1, $player2) == true) {
					$factionDamage->setCancelled(true);
				}
			}
		}
	}
}
