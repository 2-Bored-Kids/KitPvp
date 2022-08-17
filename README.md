# KitPvp

A KitPvp plugin with an integrated bounty system, depends on BedrockEconomy for money handling.<br>
Mostly uses forms. <br>

KitPvp is a gamemode in which players purchase kits (sets of items) <br>
and use them to fight, with the winner usually earning some money. <br>
A bounty is the money one gets for killing a certain player, <br>
which in this case gets higher with every person the player has killed. <br>

---

## Features
>- Integrated bounty system (can be disabled)
>- Kits stored in a config file
>- Bounties stored in bounty.db
>- Tnt explodes when placed. (Only in the kitpvp world)

---

## Commands

| Command     | Description             | Permission         |
| ----------- |:----------------------: | -----------------: |
| /kit        | Open the kit menu       | kitpvp.kit         |
| /addkit     | Add a kit               | kitpvp.kitmanager  |
| /removekit  | Delete a kit            | kitpvp.kitmanager  |
| /reloadkits | Reload kits from config | kitpvp.kitmanager  |
| /addbounty  | Add a bounty to a user  | kitpvp.bounty      |
| /bounty     | See a players' bounty   | kitpvp.bounty      |
