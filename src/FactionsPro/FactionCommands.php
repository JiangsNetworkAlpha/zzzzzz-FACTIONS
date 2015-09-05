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
use FactionsPro\Faction;
use FactionsPro\utils\FactionInvite;

class FactionCommands {
	
	public $plugin;
	
	public function __construct(FactionMain $pg) {
		$this->plugin = $pg;
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if($sender instanceof Player) {
			
			$ses = $this->plugin->getSession($sender->getPlayer()); //new
			
			$player = $sender->getPlayer()->getName();
			if(strtolower($command->getName('f'))) {
				if(empty($args)) {
					$sender->sendMessage($this->plugin->formatMessage("Please use /f help for a list of commands"));
					return true;
				}
				if(count($args == 2)) {
					
					/////////////////////////////// CREATE ///////////////////////////////
					
					if($args[0] == "create") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Usage: /f create <faction name>"));
							return true;
						}
						if(!(ctype_alnum($args[1]))) {
							$sender->sendMessage($this->plugin->formatMessage("You may only use letters and numbers!"));
							return true;
						}
						if($this->plugin->isNameBanned($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("This name is not allowed."));
							return true;
						}
						if($this->plugin->factionExists($args[1]) == true ) {
							$sender->sendMessage($this->plugin->formatMessage("Faction already exists"));
							return true;
						}
						if(strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
							$sender->sendMessage($this->plugin->formatMessage("This name is too long. Please try again!"));
							return true;
						}
						if($ses->inFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("You must leave this faction first"));
							return true;
						} else {
							$factionName = $args[1];
							$f = new Faction($this->plugin, $args[1], $sender->getPlayer()); //TODO: Split into two lines
							$ses->updateFaction();
							$sender->sendMessage($this->plugin->formatMessage("Faction successfully created!", true));
							return true;
						}
					}
					
					/////////////////////////////// INVITE ///////////////////////////////
					
					if($args[0] == "invite") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Usage: /f invite <player>"));
							return true;
						}
						if($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite") && !($ses->isLeader())) {
							$sender->sendMessage($this->plugin->formatMessage("You are not allowed to invite."));
							return true;
						}
						if($ses->getFaction()->isFull()) {
							$sender->sendMessage($this->plugin->formatMessage("Faction is full. Please kick players to make room."));
							return true;
						}
						$invited = $this->plugin->getServer()->getPlayer($args[1]);
						if(!$invited instanceof Player) {
							$sender->sendMessage($this->plugin->formatMessage("Player not online!"));
							return true;
						}
						if($this->plugin->getSession($invited)->inFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("Player is currently in a faction"));
							return true;
						}
						$invite = new FactionInvite($this->plugin->getSession($invited), $ses);
						$sender->sendMessage($this->plugin->formatMessage($invited->getName() . " has been invited!", true));
						$invited->sendMessage($this->plugin->formatMessage("You have been invited to " . $ses->getFaction()->getName() . ". Type '/f accept' or '/f deny' into chat to accept or deny!", true));
					}
					
					/////////////////////////////// LEADER ///////////////////////////////
					
					if($args[0] == "leader") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Usage: /f leader <player>"));
							return true;
						}
						if(!$ses->inFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
							return true;
						}
						if(!$ses->isLeader()) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
							return true;
						}
						if($ses->getFaction() != $this->plugin->getSession($this->plugin->getServer()->getPlayer($args[1]))->getFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("Player is not in your faction"));
							return true;
						}		
						if(!$this->plugin->getServer()->getPlayerExact($args[1]) instanceOf Player) {
							$sender->sendMessage($this->plugin->formatMessage("Player not online!"));
							return true;
						}
						$newLeader = $this->plugin->getSession($this->plugin->getServer()->getPlayer($args[1]));
						
						$ses->getFaction()->setRank($sender->getPlayer(), "Member");
						$ses->getFaction()->setRank($newLeader->getPlayer(), "Leader"); //TODO: make setRank availble through session rather than faction?
	
						$sender->sendMessage($this->plugin->formatMessage("You are no longer leader!", true));
						$newLeader->getPlayer()->sendMessage($this->plugin->formatMessage("You are now leader of " . $ses->getFaction()->getName() . "!",  true));
						$ses->updateTag();
						$newLeader->updateTag();
						}
					
					/////////////////////////////// PROMOTE ///////////////////////////////
					
					if($args[0] == "promote") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Usage: /f promote <player>"));
							return true;
						}
						if(!$ses->inFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
							return true;
						}
						if(!$ses->isLeader()) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
							return true;
						}
						if($ses->getFaction() != $this->plugin->getSession($this->plugin->getServer()->getPlayer($args[1]))->getFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("Player is not in this faction!"));
							return true;
						}
						if($this->plugin->getSession($this->plugin->getServer()->getPlayer($args[1]))->isOfficer()) {
							$sender->sendMessage($this->plugin->formatMessage("Player is already Officer"));
							return true;
						}
						$promoted = $this->plugin->getSession($this->plugin->getServer()->getPlayer($args[1]));
						$ses->getFaction()->setRank($promoted->getPlayer(), "Officer");
						$sender->sendMessage($this->plugin->formatMessage("" . $promoted->getPlayer()->getName() . " has been promoted to Officer!", true));
						$promoted->getPlayer()->sendMessage($this->plugin->formatMessage("You are now Officer!", true));
						$promoted->updateRank();
						$promoted->updateTag();
					}
					
					/////////////////////////////// DEMOTE ///////////////////////////////
					
					if($args[0] == "demote") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Usage: /f demote <player>"));
							return true;
						}
						if(!$ses->inFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
							return true;
						}
						if(!$ses->isLeader()) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
							return true;
						}
						$demoted = $this->plugin->getSession($this->plugin->getServer()->getPlayer($args[1]));
						if($ses->getFaction() != $demoted->getFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("Player is not in this faction!"));
							return true;
						}
						if(!$demoted->isOfficer()) {
							$sender->sendMessage($this->plugin->formatMessage("Player is already Member"));
							return true;
						}
						$demoted->getFaction()->setRank($demoted->getPlayer(), "Member");
						$sender->sendMessage($this->plugin->formatMessage("" . $demoted->getPlayer()->getName() . " has been demoted to Member.", true));
						$demoted->getPlayer()->sendMessage($this->plugin->formatMessage("You were demoted to Member.", true));
						$demoted->updateRank();
						$demoted->updateTag();
					}
					
					/////////////////////////////// KICK ///////////////////////////////
					//TODO: what if kicked is offline??
					if($args[0] == "kick") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Usage: /f kick <player>"));
							return true;
						}
						if(!$ses->inFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
							return true;
						}
						if(!$ses->isLeader()) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
							return true;
						}
						if(!$ses->getFaction()->hasPlayer_string($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Player is not in this faction!"));
							return true;
						}
						if(strtolower($ses->getPlayer()->getName()) == strtolower($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("You may not kick yourself. Use /f leave or /f del instead."));
							return true;
						}
						
						$ses->getFaction()->removePlayer_string($args[1]);
						
						$sender->sendMessage($this->plugin->formatMessage("You successfully kicked $args[1]!", true));
						$players[] = $this->plugin->getServer()->getOnlinePlayers();
						if($this->plugin->getServer()->getPlayer($args[1]) instanceof Player) {
							$factionName = $ses->getFaction()->getName();
							$this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("You have been kicked from \n $factionName.", true));
							$this->plugin->getSession($this->plugin->getServer()->getPlayer($args[1]))->updateFaction();							
							$this->plugin->getSession($this->plugin->getServer()->getPlayer($args[1]))->updateTag();
							return true;
						}
					}
					
					/////////////////////////////// INFO ///////////////////////////////
					/*
					if(strtolower($args[0]) == 'info') {
						if(isset($args[1])) {
							if( !(ctype_alnum($args[1])) | !($this->plugin->factionExists($args[1]))) {
								$sender->sendMessage($this->plugin->formatMessage("Faction does not exist"));
								return true;
							}
							$faction = strtolower($args[1]);
							$leader = $this->plugin->getLeader($faction);
							$numPlayers = $this->plugin->getNumberOfPlayers($faction);
							$sender->sendMessage(TextFormat::BOLD . "-------------------------");
							$sender->sendMessage("$faction");
							$sender->sendMessage(TextFormat::BOLD . "Leader: " . TextFormat::RESET . "$leader");
							$sender->sendMessage(TextFormat::BOLD . "# of Players: " . TextFormat::RESET . "$numPlayers");
							$sender->sendMessage(TextFormat::BOLD . "MOTD: " . TextFormat::RESET . "$message");
							$sender->sendMessage(TextFormat::BOLD . "-------------------------");
						} else {
							$faction = $this->plugin->getPlayerFaction(strtolower($sender->getName()));
							$result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
							$array = $result->fetchArray(SQLITE3_ASSOC);
							$message = $array["message"];
							$leader = $this->plugin->getLeader($faction);
							$numPlayers = $this->plugin->getNumberOfPlayers($faction);
							$sender->sendMessage(TextFormat::BOLD . "-------------------------");
							$sender->sendMessage("$faction");
							$sender->sendMessage(TextFormat::BOLD . "Leader: " . TextFormat::RESET . "$leader");
							$sender->sendMessage(TextFormat::BOLD . "# of Players: " . TextFormat::RESET . "$numPlayers");
							$sender->sendMessage(TextFormat::BOLD . "MOTD: " . TextFormat::RESET . "$message");
							$sender->sendMessage(TextFormat::BOLD . "-------------------------");
						}
					}
					if(strtolower($args[0]) == "help") {
						if(!isset($args[1]) || $args[1] == 1) {
							$sender->sendMessage(TextFormat::BLUE . "FactionsPro Help Page 1 of 3" . TextFormat::RED . "\n/f about\n/f accept\n/f claim\n/f create <name>\n/f del\n/f demote <player>\n/f deny");
							return true;
						}
						if($args[1] == 2) {
							$sender->sendMessage(TextFormat::BLUE . "FactionsPro Help Page 2 of 3" . TextFormat::RED . "\n/f home\n/f help <page>\n/f info\n/f info <faction>\n/f invite <player>\n/f kick <player>\n/f leader <player>\n/f leave");
							return true;
						} else {
							$sender->sendMessage(TextFormat::BLUE . "FactionsPro Help Page 3 of 3" . TextFormat::RED . "\n/f motd\n/f promote <player>\n/f sethome\n/f unclaim\n/f unsethome");
							return true;
						}
					}
				}
				if(count($args == 1)) {
					
					/////////////////////////////// CLAIM ///////////////////////////////
					
					if(strtolower($args[0]) == 'claim') {
						if($this->plugin->prefs->get("ClaimingEnabled") == "false") {
							$sender->sendMessage($this->plugin->formatMessage("Plots are not enabled on this server."));
							return true;
						}
						if($this->plugin->claimingIsDisabled($sender->getPlayer()->getLevel()))
						{
							$sender->sendMessage($this->plugin->formatMessage("You may not claim here"));
							return true;
						}
						if(!$this->plugin->getSession($sender->getPlayer())->inFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction."));
							return true;
						}
						if($this->plugin->prefs->get("OfficersCanClaim") == "false" && $this->plugin->isOfficer($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("You are not allowed to claim."));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this."));
							return true;
						}
						if($this->plugin->inOwnPlot($sender)) {
							$sender->sendMessage($this->plugin->formatMessage("Your faction has already claimed this area."));
							return true;
						}
						$x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
						$faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
						if($this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {
							return true;
						}
						$sender->sendMessage($this->plugin->formatMessage("Plot claimed.", true));
					}
					
					/////////////////////////////// UNCLAIM ///////////////////////////////
					
					if(strtolower($args[0]) == "unclaim") {
						if($this->plugin->prefs->get("ClaimingEnabled") == "false") {
							$sender->sendMessage($this->plugin->formatMessage("Plots are not enabled on this server."));
							return true;
						}
						if(!$this->plugin->isLeader($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
						$sender->sendMessage($this->plugin->formatMessage("Plot unclaimed.", true));
					}
					
					/////////////////////////////// MOTD ///////////////////////////////
					
					if(strtolower($args[0]) == "motd") {
						if(!$this->plugin->getSession($sender->getPlayer())->inFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
							return true;
						}
						$sender->sendMessage($this->plugin->formatMessage("Type your message in chat. It will not be visible to other players", true));
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
						$stmt->bindValue(":player", strtolower($sender->getName()));
						$stmt->bindValue(":timestamp", time());
						$result = $stmt->execute();
					}*/
					
					/////////////////////////////// ACCEPT ///////////////////////////////
					
					if(strtolower($args[0]) == "accept") {
						if(!$ses->hasInvite())
						{
							$sender->sendMessage($this->plugin->formatMessage("You have not been invited to any factions!"));
							return true;
						}
						if($ses->getInvite()->getTimeout() <= time())
						{
							$sender->sendMessage($this->plugin->formatMessage("Invite has timed out!"));
							$ses->deregisterInvite();
							return true;
						}
						$ses->joinFaction($ses->getInvite()->getFaction());
						$sender->sendMessage($this->plugin->formatMessage("You successfully joined " . $ses->getFaction()->getName() . "!", true));
						if($ses->getInvite()->getInvitedby() instanceof Player) 
						{
							$ses->getInvite()->getInvitedby()->sendMessage($this->plugin->formatMessage($sender->getPlayer()->getName() . " joined the faction!", true));
						}
						$ses->updateTag();
						$ses->deregisterInvite();
					}
					
					/////////////////////////////// DENY ///////////////////////////////
					
					if(strtolower($args[0]) == "deny") {
					if(!$ses->hasInvite())
						{
							$sender->sendMessage($this->plugin->formatMessage("You have not been invited to any factions!"));
							return true;
						}
						if($ses->getInvite()->getTimeout() <= time())
						{
							$sender->sendMessage($this->plugin->formatMessage("Invite has timed out!"));
							$ses->deregisterInvite();
							return true;
						}
						$sender->sendMessage($this->plugin->formatMessage("Invite declined."));
						if($ses->getInvite()->getInvitedby() instanceof Player) 
						{
							$ses->getInvite()->getInvitedby()->sendMessage($this->plugin->formatMessage($sender->getPlayer()->getName() . " declined your invite."));
						}
						$ses->deregisterInvite();
					}
					
					/////////////////////////////// DELETE ///////////////////////////////
					
					if(strtolower($args[0]) == "del") {
						if(!$ses->inFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("You are not in a faction!"));
						}
						if(!$ses->isLeader()) {
							$sender->sendMessage($this->plugin->formatMessage("You are not leader!"));
						}
						$ses->getFaction()->delete();
						$sender->sendMessage($this->plugin->formatMessage("Faction successfully disbanded!", true));
						$ses->updateTag();
					}
					
					/////////////////////////////// LEAVE ///////////////////////////////
					
					if(strtolower($args[0] == "leave")) {
						if(!$ses->isLeader()) {
							$faction = $ses->getFaction()->getName();
							$ses->leaveFaction();
							$sender->sendMessage($this->plugin->formatMessage("You successfully left $faction", true));
							$ses->updateTag();
						} else {
							$sender->sendMessage($this->plugin->formatMessage("You must delete or give\nleadership first!"));
						}
					}
					
					/////////////////////////////// SETHOME ///////////////////////////////
					/*
					if(strtolower($args[0] == "sethome")) {
						if(!$this->plugin->getSession($sender->getPlayer())->inFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to set home."));
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
					
					/////////////////////////////// UNSETHOME ///////////////////////////////
						
					if(strtolower($args[0] == "unsethome")) {
						if(!$this->plugin->getSession($sender->getPlayer())->inFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to unset home."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
						$sender->sendMessage($this->plugin->formatMessage("Home unset!", true));
					}
					
					/////////////////////////////// HOME ///////////////////////////////
						
					if(strtolower($args[0] == "home")) {
						if(!$this->plugin->getSession($sender->getPlayer())->inFaction()) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(!empty($array)) {
							$sender->getPlayer()->teleport(new Vector3($array['x'], $array['y'], $array['z']));
							$sender->sendMessage($this->plugin->formatMessage("Teleported home.", true));
							return true;
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Home is not set."));
							}
						}
					*/
					/////////////////////////////// ABOUT ///////////////////////////////
					
					if(strtolower($args[0] == 'about')) {
						$sender->sendMessage(TextFormat::BLUE . "FactionsPro v1.5b1 by " . TextFormat::BOLD . "Tethered_\n" . TextFormat::RESET . TextFormat::BLUE . "Twitter: " . TextFormat::ITALIC . "@Tethered_");
					}
				}
			}
		} else {
			$this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage("Please run command in game"));
		}
	}
}