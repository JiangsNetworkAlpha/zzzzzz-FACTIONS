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


class FactionListener implements Listener {
	
	public $plugin;
	
	public function __construct(FactionMain $pg) {
		$this->plugin = $pg;
	}
	
	public function factionChat(PlayerChatEvent $PCE) {
		if(!$this->plugin->getServer()->getPluginManager()->getPlugin("CustomChat") == false) {
			return true;
		}	
		if(!$this->plugin->getServer()->getPluginManager()->getPlugin("PureChat") == false) {
			return true;
		}	
				//This will be chat for players who are "Members" of a faction
		if($this->plugin->isInFaction($PCE->getPlayer()->getName()) && $this->plugin->isMember($PCE->getPlayer()->getName())) {
			$m = $PCE->getMessage();
			$p = $PCE->getPlayer()->getName();
			$lowerp = strtolower($p);
			$stmt = $this->plugin->db->query("SELECT * FROM master WHERE player='$p';");
			$result = $stmt->fetchArray(SQLITE3_ASSOC);
			$f = $result["faction"];
			$PCE->setFormat("[$f] $p: $m");
		}
			
			/*//DESC RECEIVER
			$p = strtolower($p);
			
			$this->plugin->getServer()->getLogger()->info($p);
			
			$stmt = $this->plugin->db->query("SELECT * FROM descRCV WHERE player='$p';");
			$result = $stmt->fetchArray(SQLITE3_ASSOC);
			if(!empty($result)) {
				if(time() - $result["timestamp"] > 30) {
					$PCE->getPlayer()->sendMessage("[FactionsPro] Timed out. Please use /f desc again.");
					$this->plugin->db->query("DELETE FROM descRCV WHERE player='$p';");
					$PCE->setCancelled(true);
					return true;
				} else {
					$desc = $PCE->getMessage();
					$faction = $this->plugin->getPlayerFaction($p);
					$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO desc (faction, description) VALUES (:faction, :description);");
					$stmt->bindValue(":faction", $faction);
					$stmt->bindValue(":description", $desc);
					$result = $stmt->execute();
					$PCE->setCancelled(true);
					$this->plugin->db->query("DELETE FROM descRCV WHERE player='$p';");
					$PCE->getPlayer()->sendMessage("[FactionsPro] Successfully updated faction message of the day!");
				}
			}
			return true;*/
			
		//This will be the chat for players that are "Officers"
		if($this->plugin->isInFaction($PCE->getPlayer()->getName()) && $this->plugin->isOfficer($PCE->getPlayer()->getName())) {
			$m = $PCE->getMessage();
			$p = $PCE->getPlayer()->getName();
			$lowerp = strtolower($p);
			$stmt = $this->plugin->db->query("SELECT * FROM master WHERE player='$p';");
			$result = $stmt->fetchArray(SQLITE3_ASSOC);
			$f = $result["faction"];
			$id = $this->plugin->prefs->get("OfficerIdentifier");
			$PCE->setFormat("[$id$f] $p: $m");
			return true;
		}
		//This will be the chat for players that are "Leaders"
		elseif($this->plugin->isInFaction($PCE->getPlayer()->getName()) && $this->plugin->isLeader($PCE->getPlayer()->getName())) {
			$m = $PCE->getMessage();
			$p = $PCE->getPlayer()->getName();
			$lowerp = strtolower($p);
			$stmt = $this->plugin->db->query("SELECT * FROM master WHERE player='$p';");
			$result = $stmt->fetchArray(SQLITE3_ASSOC);
			$f = $result["faction"];
			$id = $this->plugin->prefs->get("LeaderIdentifier");
			$PCE->setFormat("[$id$f] $p: $m");
			return true;
		}else {
			$m = $PCE->getMessage();
			$p = $PCE->getPlayer()->getName();
			$PCE->setFormat("$p: $m");
		}
	}
	public function factionPVP(EntityDamageEvent $factionDamage) {
		if($factionDamage instanceof EntityDamageByEntityEvent) {
			if(!($factionDamage->getEntity() instanceof Player) or !($factionDamage->getDamager() instanceof Player)) {
				return true;
			}
			if(($this->plugin->isInFaction($factionDamage->getEntity()->getPlayer()->getName()) == false) or ($this->plugin->isInFaction($factionDamage->getDamager()->getPlayer()->getName()) == false) ) {
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
	public function factionBlockBreakProtect(BlockBreakEvent $event) {	
		if($this->plugin->pointIsInPlot($event->getBlock()->getFloorX(), $event->getBlock()->getFloorZ())) {
			if( ($this->plugin->factionFromPoint($event->getBlock()->getFloorX(), $event->getBlock()->getFloorZ())) != $this->plugin->getPlayerFaction($event->getPlayer()->getName())) {
				$event->setCancelled(true);
				$faction = $this->plugin->factionFromPoint($event->getBlock()->getFloorX(), $event->getBlock()->getFloorZ());
				$event->getPlayer()->sendMessage("[FactionsPro] This area is claimed by $faction");
				return true;
			}
		}
	}
	
	public function factionBlockPlaceProtect(BlockPlaceEvent $event) {
		if($this->plugin->pointIsInPlot($event->getBlock()->getFloorX(), $event->getBlock()->getFloorZ())) {
		if( ($this->plugin->factionFromPoint($event->getBlock()->getFloorX(), $event->getBlock()->getFloorZ())) != $this->plugin->getPlayerFaction($event->getPlayer()->getName())) {
				$event->setCancelled(true);
				$faction = $this->plugin->factionFromPoint($event->getBlock()->getFloorX(), $event->getBlock()->getFloorZ());
				$event->getPlayer()->sendMessage("[FactionsPro] This area is claimed by $faction");
				return true;
			}
		}
	}
	
}
