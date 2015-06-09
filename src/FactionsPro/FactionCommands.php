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
use pocketmine\math\Vector3;

class FactionCommands {
	
	public $plugin;
	
	public function __construct(FactionMain $pg) {
		$this->plugin = $pg;
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if($sender instanceof Player) {
			$player = $sender->getPlayer()->getName();
			if(strtolower($command->getName('f'))) {
				if(empty($args)) {
					$sender->sendMessage($this->plugin->formatMessage("Please use /f help for a list of commands"));
				}
				if(count($args == 2)) {
					
					/////////////////////////////// CREATE ///////////////////////////////
					
					if($args[0] == "create") {
						if(!(ctype_alnum($args[1]))) {
							$sender->sendMessage($this->plugin->formatMessage("You may only use letters and numbers!"));
							return true;
						}
						if($this->plugin->isNameBanned($args[1])) {
							$player->sendMessage($this->plugin->formatMessage("This name is not allowed."))
						}
						if($this->plugin->factionExists($args[1]) == true ) {
							$sender->sendMessage($this->plugin->formatMessage("Faction already exists"));
							return true;
						}
						if(strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
							$sender->sendMessage($this->plugin->formatMessage("This name is too long. Please try again!"));
							return true;
						}
						if($this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("You must leave this faction first"));
							return true;
						} else {
							$factionName = $args[1];
							$player = strtolower($player);
							$rank = "Leader";
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
							$stmt->bindValue(":player", $player);
							$stmt->bindValue(":faction", $factionName);
							$stmt->bindValue(":rank", $rank);
							$result = $stmt->execute();
							$sender->sendMessage($this->plugin->formatMessage("Faction successfully created!"));
							return true;
						}
					}
					
					/////////////////////////////// INVITE ///////////////////////////////
					
					if($args[0] == "invite") {
						if( $this->plugin->isFactionFull($this->plugin->getPlayerFaction($player)) ) {
							$sender->sendMessage($this->plugin->formatMessage("Faction is full. Please kick players to make room."));
							return true;
						}
						$invited = $this->plugin->getServer()->getPlayerExact($args[1]);
						if($this->plugin->isInFaction($invited) == true) {
							$sender->sendMessage($this->plugin->formatMessage("Player is currently in a faction"));
							return true;
						}
						if($this->plugin->prefs->get("OnlyLeadersCanInvite") & !($this->plugin->isLeader($player))) {
							$sender->sendMessage($this->plugin->formatMessage("Only your faction leader may invite!"));
							return true;
						}
						if(!$invited instanceof Player) {
							$sender->sendMessage($this->plugin->formatMessage("Player not online!"));
							return true;
						}
						if($invited->isOnline() == true) {
							$factionName = $this->plugin->getPlayerFaction($player);
							$invitedName = $invited->getName();
							$rank = "Member";
								
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
							$stmt->bindValue(":player", strtolower($invitedName));
							$stmt->bindValue(":faction", $factionName);
							$stmt->bindValue(":invitedby", $sender->getName());
							$stmt->bindValue(":timestamp", time());
							$result = $stmt->execute();
	
							$sender->sendMessage($this->plugin->formatMessage("$invitedName!"));
							$invited->sendMessage($this->plugin->formatMessage("You have been invited to $factionName. Type '/f accept' or '/f deny' into chat to accept or deny!"));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Player not online!"));
						}
					}
					
					/////////////////////////////// LEADER ///////////////////////////////
					
					if($args[0] == "leader") {
						if($this->plugin->isInFaction($sender->getName()) == true) {
							if($this->plugin->isLeader($player) == true) {
								if($this->plugin->getPlayerFaction($player) == $this->plugin->getPlayerFaction($args[1])) {
									if($this->plugin->getServer()->getPlayerExact($args[1])->isOnline() == true) {
										$factionName = $this->plugin->getPlayerFaction($player);
										$factionName = $this->plugin->getPlayerFaction($player);
	
										$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
										$stmt->bindValue(":player", $player);
										$stmt->bindValue(":faction", $factionName);
										$stmt->bindValue(":rank", "Member");
										$result = $stmt->execute();
	
										$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
										$stmt->bindValue(":player", strtolower($args[1]));
										$stmt->bindValue(":faction", $factionName);
										$stmt->bindValue(":rank", "Leader");
										$result = $stmt->execute();
	
	
										$sender->sendMessage($this->plugin->formatMessage("You are no longer leader!"));
										$this->plugin->getServer()->getPlayerExact($args[1])->sendMessage($this->plugin->formatMessage("You are now leader \nof $factionName!"));
									} else {
										$sender->sendMessage($this->plugin->formatMessage("Player not online!"));
									}
								} else {
									$sender->sendMessage($this->plugin->formatMessage("Add player to faction first!"));
								}
							} else {
								$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
							}
						} else {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
						}
					}
					
					/////////////////////////////// PROMOTE ///////////////////////////////
					
					if($args[0] == "promote") {
						
						$factionName = $this->plugin->getPlayerFaction($player);
						
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Player is not in this faction!"));
							return true;
						}
						if($this->plugin->isOfficer($player) == true) {
							$sender->sendMessage($this->plugin->formatMessage("Player is already officer"));
							return true;
						}
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
						$stmt->bindValue(":player", strtolower($args[1]));
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", "Officer");
						$result = $stmt->execute();
					}
					
					/////////////////////////////// DEMOTE ///////////////////////////////
					
					if($args[0] == "demote") {
					
						$factionName = $this->plugin->getPlayerFaction($player);
					
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Player is not in this faction!"));
							return true;
						}
						if($this->plugin->isOfficer($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("Player is not Officer"));
							return true;
						}
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
						$stmt->bindValue(":player", strtolower($args[1]));
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", "Member");
						$result = $stmt->execute();
					}
					
					/////////////////////////////// KICK ///////////////////////////////
					
					if($args[0] == "kick") {
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Player is not in this faction!"));
							return true;
						}
						$kicked = $this->plugin->getServer()->getPlayerExact($args[1]);
						$factionName = $this->plugin->getPlayerFaction($player);
						$this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
						$sender->sendMessage($this->plugin->formatMessage("You successfully kicked $args[1]!"));
						$players[] = $this->plugin->getServer()->getOnlinePlayers();
						if(in_array($args[1], $players) == true) {
							$this->plugin->getServer()->getPlayerExact($args[1])->sendMessage($this->plugin->formatMessage("You have been kicked from \n $factionName!"));
							return true;
						}
					}
					
					/////////////////////////////// INFO ///////////////////////////////
					
					if(strtolower($args[0]) == 'info') {
						if(isset($args[1])) {
							if( !(ctype_alnum($args[1])) | !($this->plugin->factionExists($args[1]))) {
								$sender->sendMessage($this->plugin->formatMessage("Faction does not exist"));
								return true;
							}
							$faction = strtolower($args[1]);
							$leader = $this->plugin->getLeader($faction);
							$numPlayers = $this->plugin->getNumberOfPlayers($faction);
							$sender->sendMessage("-------------------------");
							$sender->sendMessage("$faction");
							$sender->sendMessage("Leader: $leader");
							$sender->sendMessage("# of Players: $numPlayers");
							$sender->sendMessage("MOTD: $message");
							$sender->sendMessage("-------------------------");
						} else {
							$faction = $this->plugin->getPlayerFaction(strtolower($sender->getName()));
							$result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
							$array = $result->fetchArray(SQLITE3_ASSOC);
							$message = $array["message"];
							$leader = $this->plugin->getLeader($faction);
							$numPlayers = $this->plugin->getNumberOfPlayers($faction);
							$sender->sendMessage("-------------------------");
							$sender->sendMessage("$faction");
							$sender->sendMessage("Leader: $leader");
							$sender->sendMessage("# of Players: $numPlayers");
							$sender->sendMessage("MOTD: $message");
							$sender->sendMessage("-------------------------");
						}
					}
				}
				if(count($args == 1)) {
					
					/////////////////////////////// PLOT (CLAIM) ///////////////////////////////
					
					if(strtolower($args[0]) == 'claim') {
						if(!$this->plugin->isInFaction($player)) {
							$player->sendMessage($this->plugin->formatMessage("You must be in a faction."));
						}
						if(!$this->plugin->isLeader($player)) {
							$player->sendMessage($this->plugin->formatMessage("You must be leader to use this."));
						}
						$x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
						$faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
						$this->plugin->drawPlot($player, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize"));
						$sender->sendMessage($this->plugin->formatMessage("Plot claimed.", true));
					}
					
					/////////////////////////////// MOTD ///////////////////////////////
					
					if(strtolower($args[0]) == "motd") {
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
							return true;
						}
						$sender->sendMessage($this->plugin->formatMessage("Type your message in chat. It will not be visible to other players"));
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
						$stmt->bindValue(":player", strtolower($sender->getName()));
						$stmt->bindValue(":timestamp", time());
						$result = $stmt->execute();
					}
					
					/////////////////////////////// ACCEPT ///////////////////////////////
					
					if(strtolower($args[0]) == "accept") {
						$player = $sender->getName();
						$lowercaseName = strtolower($player);
						$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("You have not been invited to any factions!"));
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $invitedTime) <= 60 ) { //This should be configurable
							$faction = $array["faction"];
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
							$stmt->bindValue(":player", strtolower($player));
							$stmt->bindValue(":faction", $faction);
							$stmt->bindValue(":rank", "Member");
							$result = $stmt->execute();
							$this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("You successfully joined $faction!"));
							$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player joined the faction!"));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Invite has timed out!"));
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$player';");
						}
					}
					
					/////////////////////////////// DENY ///////////////////////////////
					
					if(strtolower($args[0]) == "deny") {
						$player = $sender->getName();
						$lowercaseName = strtolower($player);
						$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("You have not been invited to any factions!"));
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $invitedTime) <= 60 ) { //This should be configurable
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("Invite declined!"));
							$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player declined the invite!"));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Invite has timed out!"));
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
						}
					}
					
					/////////////////////////////// DELETE ///////////////////////////////
					
					if(strtolower($args[0]) == "del") {
						if($this->plugin->isInFaction($player) == true) {
							if($this->plugin->isLeader($player)) {
								$faction = $this->plugin->getPlayerFaction($player);
								$this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
								$sender->sendMessage($this->plugin->formatMessage("Faction successfully disbanded!"));
							}	 else {
								$sender->sendMessage($this->plugin->formatMessage("You are not leader!"));
							}
						} else {
							$sender->sendMessage($this->plugin->formatMessage("You are not in a faction!"));
						}
					}
					
					/////////////////////////////// LEAVE ///////////////////////////////
					
					if(strtolower($args[0] == "leave")) {
						if($this->plugin->isLeader($player) == false) {
							$remove = $sender->getPlayer()->getNameTag();
							$faction = $this->plugin->getPlayerFaction($player);
							$name = $sender->getName();
							$this->plugin->db->query("DELETE FROM master WHERE player='$name';");
							$sender->sendMessage($this->plugin->formatMessage("You successfully left $faction"));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("You must delete or give\nleadership first!"));
						}
					}
					
					if(strtolower($args[0] == "sethome")) {
						if(!$this->plugin->isInFaction($player)) {
							$player->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
						}
						if(!$this->plugin->isLeader($player)) {
							$player->sendMessage($this->plugin->formatMessage("You must be leader to set home."));
							return true;
						}
						$factionName = $this->plugin->getPlayerFaction($sender->getName());
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z) VALUES (:faction, :x, :y, :z);");
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":x", $sender->getX());
						$stmt->bindValue(":y", $sender->getY());
						$stmt->bindValue(":z", $sender->getZ());
						$result = $stmt->execute();
						$sender->sendMessage($this->plugin->formatMessage("Home updated!", true));
					}
						
					if(strtolower($args[0] == "unsethome")) {
						if(!$this->plugin->isInFaction($player)) {
							$player->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
						}
						if(!$this->plugin->isLeader($player)) {
							$player->sendMessage($this->plugin->formatMessage("You must be leader to unset home."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
						$sender->sendMessage("[FactionsPro] Home unset!");
						}
					
					if(strtolower($args[0] == "home")) {
						if(!$this->plugin->isInFaction($player)) {
							$player->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(!empty($array)) {
							$sender->getPlayer()->teleport(new Vector3($array['x'], $array['y'], $array['z']));
							$sender->sendMessage("[FactionsPro] Teleported home.");
							return true;
						} else {
							$sender->sendMessage("[FactionsPro] Home is not set.");
							}
						}
					
					/////////////////////////////// ABOUT ///////////////////////////////
					
					if(strtolower($args[0] == 'about')) {
						$player->sendMessage(TextFormat::BLUE . "FactionsPro v1.3.0 by " . TextFormat::BOLD . "Tethered_\n" . TextFormat::RESET . TextFormat::BLUE . "Twitter: " . TextFormat::ITALIC . "@Tethered_");
					}
					
					if(strtolower($args[0]) == "help") {
						$sender->sendMessage(TextFormat::BLUE . "FactionsPro Commands" . TextFormat::RED . "\n/f create <name>\n/f del\n/f help\n/f invite <player>\n/f kick <player>\n/f leave\n/f leader <player>\n/f leave\n/f motd\n/f info");
					}
				} else {
					$sender->sendMessage($this->plugin->formatMessage("Please use /f help for a list of commands"));
				}
			}
		} else {
			$this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage("Please run command in game"));
		}
	}
}