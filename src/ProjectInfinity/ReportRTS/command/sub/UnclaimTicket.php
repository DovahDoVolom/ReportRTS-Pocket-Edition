<?php

namespace ProjectInfinity\ReportRTS\command\sub;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use ProjectInfinity\ReportRTS\ReportRTS;
use ProjectInfinity\ReportRTS\util\MessageHandler;
use ProjectInfinity\ReportRTS\util\PermissionHandler;
use ProjectInfinity\ReportRTS\util\ToolBox;

class UnclaimTicket {

    private $plugin;
    private $data;

    public function __construct(ReportRTS $plugin) {
        $this->plugin = $plugin;
        $this->data = $plugin->getDataProvider();
    }

    public function handleCommand(CommandSender $sender, $args) {

        ### Check if anything is wrong with the provided input before going further. ###
        if(!$sender->hasPermission(PermissionHandler::canClaimTicket)) {
            $sender->sendMessage(sprintf(MessageHandler::$permissionError, PermissionHandler::canClaimTicket));
            return true;
        }

        if(count($args) < 2) {
            $sender->sendMessage(sprintf(MessageHandler::$generalError, "You need to specify a ticket ID."));
            return true;
        }

        if(!ToolBox::isNumber($args[1])) {
            $sender->sendMessage(sprintf(MessageHandler::$generalError, "Ticket ID must be a number. Provided: ".$args[1]));
            return true;
        }

        $ticketId = intval($args[1]);

        if(!isset(ReportRTS::$tickets[$ticketId])) {
            # The ticket that the user is trying to claim is not in the array (not open).
            $sender->sendMessage(MessageHandler::$ticketNotClaimed);
            return true;
        }

        if($sender instanceof Player) {
            if(strtoupper($sender->getName()) != ReportRTS::$tickets[$ticketId]->getStaffName() and !$sender->hasPermission(PermissionHandler::bypassTicketClaim)) {
                $sender->sendMessage("You need ".PermissionHandler::bypassTicketClaim." to do that.");
                return true;
            }
        }
        ### We're done! Let's start processing stuff. ###

        $ticket = ReportRTS::$tickets[$args[1]];

        $timestamp = round(microtime(true));

        if($resultCode = $this->data->setTicketStatus($ticketId, $sender->getName(), 0, null, 0, $timestamp) and $resultCode != 1) {

            if($resultCode == -1) {
                # Username is invalid or does not exist.
                $sender->sendMessage(sprintf(MessageHandler::$userNotExists, $sender->getName()));
                return true;
            }
            if($resultCode == -2) {
                # Ticket status incompatibilities.
                $sender->sendMessage(MessageHandler::$ticketStatusError);
                return true;
            }
            if($resultCode == -3) {
                # Ticket does not exist.
                $sender->sendMessage(sprintf(MessageHandler::$ticketNotExists, $ticketId));
                return true;
            }
            $sender->sendMessage(sprintf(MessageHandler::$generalError, "Unable to unclaim ticket #".$ticketId));
            return true;
        }

        # Set ticket info in the ticket array too.
        $ticket->setStatus(0);
        $ticket->setStaffName(null);
        $ticket->setStaffId(0);
        $ticket->setStaffTimestamp(null);
        ReportRTS::$tickets[$args[1]] = $ticket;

        $player = $this->plugin->getServer()->getPlayer($ticket->getName());
        if($player != null) {
            $player->sendMessage(sprintf(MessageHandler::$ticketUnclaimUser, $sender->getName()));
            $player->sendMessage(sprintf(MessageHandler::$ticketClaimText, $ticket->getMessage()));
        }

        # Let staff know about this change.
        $this->plugin->messageStaff(sprintf(MessageHandler::$ticketUnclaim, $sender->getName(), $ticketId));

        return true;
    }
}