<?php

namespace customiesdevs\customies\util;

use pocketmine\block\Block;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use function pow;

class BlockUtil
{
    private static function getDestroySpeed(Player $player, Block $block, Item $item) : float
    {
        $destroySpeed = $item->getMiningEfficiency(($block->getBreakInfo()->getToolType() & $item->getBlockToolType()) !== 0);
        $speedBreak = $destroySpeed;
        $hasteLevel = 0;
        $effectManager = $player->getEffects();
        $haste = $effectManager->get(VanillaEffects::HASTE());
        $conduitPower = $effectManager->get(VanillaEffects::CONDUIT_POWER());
        $miningFatigue = $effectManager->get(VanillaEffects::MINING_FATIGUE());
        if ($haste)
            $hasteLevel = $haste->getEffectLevel();
        if ($conduitPower) {
            $conduitPowerLevel = $conduitPower->getEffectLevel();
            if ($hasteLevel < $conduitPowerLevel)
                $hasteLevel = $conduitPowerLevel;
        }
        if ($hasteLevel > 0)
            $speedBreak = $destroySpeed * (($hasteLevel * 0.2) + 1);
        if ($miningFatigue) {
            $slowMininLevel = $miningFatigue->getEffectLevel();
            $speedBreak = pow(0.300000011920929, $slowMininLevel) * $speedBreak;
        }
        if (!$player->isOnGround()) {
            if (!$player->getAllowFlight())
                $speedBreak *= 0.2;
        }
        if ($player->isUnderwater()) {
            if ($item->isNull())
                return $speedBreak * 0.2;
        }
        return $speedBreak;
    }

    public static function getDestroyRate(Player $player, Block $block) : float
    {
        $speedCalcul = self::getDestroyProgress($player, $block);
        $speedBreaker = $speedCalcul;
        $hasteLevel = 0;
        $effectManager = $player->getEffects();
        $haste = $effectManager->get(VanillaEffects::HASTE());
        $conduitPower = $effectManager->get(VanillaEffects::CONDUIT_POWER());
        $miningFatigue = $effectManager->get(VanillaEffects::MINING_FATIGUE());
        if ($haste)
            $hasteLevel = $haste->getEffectLevel();
        if ($conduitPower) {
            $conduitPowerLevel = $conduitPower->getEffectLevel();
            if ($hasteLevel < $conduitPowerLevel)
                $hasteLevel = $conduitPowerLevel;
        }
        if ($hasteLevel > 0)
            $speedBreaker = pow(1.200000047683716, (double) $hasteLevel) * $speedCalcul;
        if ($miningFatigue)
            $speedBreaker *= pow(0.699999988079071, $miningFatigue->getEffectLevel());
        return $speedBreaker;
    }

    private static function getDestroyProgress(Player $player, Block $block) : float
    {
        $destroySpeed = $block->getBreakInfo()->getHardness();
        $item = $player->getInventory()->getItemInHand();
        if ($destroySpeed > 0.0) {
            $tick = 1.0 / $destroySpeed;
            if ($block->getBreakInfo()->isToolCompatible(VanillaItems::AIR()))
                return (self::getDestroySpeed($player, $block, $item) * $tick) * 0.033333335;
            else if ($block->getBreakInfo()->isToolCompatible($item))
                return (self::getDestroySpeed($player, $block, $item) * $tick) * 0.033333335;
            else
                return ((self::getDestroySpeed($player, $block, $item) * $tick) * 0.0099999998);
        }
        return 1.0;
    }
}