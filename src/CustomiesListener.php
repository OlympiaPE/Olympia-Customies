<?php

/** @noinspection PhpUnused */

namespace customiesdevs\customies;

use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\item\CustomiesItemFactory;
use customiesdevs\customies\player\BlockBreakRequest;
use customiesdevs\customies\util\BlockUtil;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\BiomeDefinitionListPacket;
use pocketmine\network\mcpe\protocol\ItemComponentPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\PlayerBlockAction;
use pocketmine\network\mcpe\protocol\types\PlayerBlockActionStopBreak;
use pocketmine\network\mcpe\protocol\types\PlayerBlockActionWithBlockInfo;
use pocketmine\player\Player;
use WeakMap;
use function array_merge;
use function count;

final class CustomiesListener implements Listener
{
    public const MAX_DISTANCE_BREAK = 16 ** 2;

	private ?ItemComponentPacket $cachedItemComponentPacket = null;
	/** @var ItemTypeEntry[] */
	private array $cachedItemTable = [];
	/** @var BlockPaletteEntry[] */
	private array $cachedBlockPalette = [];
	private Experiments $experiments;

    /** @phpstan-var WeakMap<NetworkSession, BlockBreakRequest> */
    private WeakMap $breaks;

	public function __construct()
    {
		$this->experiments = new Experiments([
			// "data_driven_items" is required for custom blocks to render in-game. With this disabled, they will be
			// shown as the UPDATE texture block.
			"data_driven_items" => true,
		], true);

        $this->breaks = new WeakMap();
	}

	public function onDataPacketSend(DataPacketSendEvent $event): void
    {
		foreach($event->getPackets() as $packet){
			if($packet instanceof BiomeDefinitionListPacket) {
				// ItemComponentPacket needs to be sent after the BiomeDefinitionListPacket.
				if($this->cachedItemComponentPacket === null) {
					// Wait for the data to be needed before it is actually cached. Allows for all blocks and items to be
					// registered before they are cached for the rest of the runtime.
					$this->cachedItemComponentPacket = ItemComponentPacket::create(CustomiesItemFactory::getInstance()->getItemComponentEntries());
				}
				foreach($event->getTargets() as $session){
					$session->sendDataPacket($this->cachedItemComponentPacket);
				}
			} elseif($packet instanceof StartGamePacket) {
				if(count($this->cachedItemTable) === 0) {
					// Wait for the data to be needed before it is actually cached. Allows for all blocks and items to be
					// registered before they are cached for the rest of the runtime.
					$this->cachedItemTable = CustomiesItemFactory::getInstance()->getItemTableEntries();
					$this->cachedBlockPalette = CustomiesBlockFactory::getInstance()->getBlockPaletteEntries();
				}
				$packet->levelSettings->experiments = $this->experiments;
				$packet->itemTable = array_merge($packet->itemTable, $this->cachedItemTable);
				$packet->blockPalette = $this->cachedBlockPalette;
			} elseif($packet instanceof ResourcePackStackPacket) {
				$packet->experiments = $this->experiments;
			}
		}
	}

    /** @priority MONITOR */
    public function onDataReceive(DataPacketReceiveEvent $event) : void
    {
        return;
        $player = ($session = $event->getOrigin())->getPlayer();
        if ($player === null || $player->isCreative())
            return;
        $packet = $event->getPacket();
        if($packet instanceof PlayerAuthInputPacket) {
            $cancel = false;
            $blockActions = $packet->getBlockActions();
            if ($blockActions !== null) {
                if (count($blockActions) > 100) {
                    $session->getLogger()->debug("PlayerAuthInputPacket contains " . count($blockActions) . " block actions, dropping");
                    return;
                }
                /**
                 * @var int $k
                 * @var PlayerBlockAction $blockAction
                 */
                $blockActions = array_filter($blockActions, fn(PlayerBlockAction $blockAction) => $blockAction->getActionType() === PlayerAction::START_BREAK ||
                    $blockAction->getActionType() === PlayerAction::CRACK_BREAK ||
                    $blockAction->getActionType() === PlayerAction::ABORT_BREAK ||
                    $blockAction instanceof PlayerBlockActionStopBreak);
                foreach ($blockActions as $blockAction) {
                    $action = $blockAction->getActionType();
                    if ($blockAction instanceof PlayerBlockActionWithBlockInfo) {
                        if ($action === PlayerAction::START_BREAK) {
                            $vector3 = new Vector3($blockAction->getBlockPosition()->getX(), $blockAction->getBlockPosition()->getY(), $blockAction->getBlockPosition()->getZ());
                            $block = $player->getWorld()->getBlock($vector3);
                            if ($block->getBreakInfo()->breaksInstantly()) continue;
                            $cancel = true;
                            $speed = BlockUtil::getDestroyRate($player, $block);
                            $this->breaks->offsetSet($session, new BlockBreakRequest($player->getWorld(), $vector3, $speed));
                            if (!$player->attackBlock($vector3, $blockAction->getFace())) {
                                $this->onFailedBlockAction($session, $player, $vector3, $blockAction->getFace());
                            } else {
                                $player->getWorld()->broadcastPacketToViewers(
                                    $vector3,
                                    LevelEventPacket::create(LevelEvent::BLOCK_START_BREAK, (int)floor($speed * 65535.0), $vector3)
                                );
                            }
                        } elseif ($action === PlayerAction::CRACK_BREAK) {
                            if ($this->breaks->offsetExists($session)) {
                                $cancel = true;
                                $vector3 = new Vector3($blockAction->getBlockPosition()->getX(), $blockAction->getBlockPosition()->getY(), $blockAction->getBlockPosition()->getZ());
                                $block = $player->getWorld()->getBlock($vector3);
                                $breakRequest = $this->breaks->offsetGet($session);
                                if ($vector3->distanceSquared($breakRequest->getOrigin()) > self::MAX_DISTANCE_BREAK) {
                                    unset($this->breaks[$session]);
                                    continue;
                                }
                                if ($breakRequest->addTick(BlockUtil::getDestroyRate($player, $block)) >= 1) {
                                    $player->breakBlock($vector3);
                                    unset($this->breaks[$session]);
                                }
                            }
                        } elseif ($blockAction->getActionType() === PlayerAction::ABORT_BREAK) {
                            $vector3 = new Vector3($blockAction->getBlockPosition()->getX(), $blockAction->getBlockPosition()->getY(), $blockAction->getBlockPosition()->getZ());
                            if ($this->breaks->offsetExists($session)) {
                                $player->stopBreakBlock($vector3);
                                unset($this->breaks[$session]);
                            }
                        }
                    } elseif ($blockAction instanceof PlayerBlockActionStopBreak) {
                        if ($this->breaks->offsetExists($session)) {
                            unset($this->breaks[$session]);
                        }
                    }
                }
            }
            if ($cancel)
                $event->cancel();
        }
    }

    private function onFailedBlockAction(NetworkSession $session, Player $player, Vector3 $blockPos, ?int $face): void
    {
        if($blockPos->distanceSquared($player->getLocation()) < 10000) {
            $blocks = $blockPos->sidesArray();
            if($face !== null){
                $sidePos = $blockPos->getSide($face);
                array_push($blocks, ...$sidePos->sidesArray());
            }else{
                $blocks[] = $blockPos;
            }
            foreach($player->getWorld()->createBlockUpdatePackets($blocks) as $packet) {
                $session->sendDataPacket($packet);
            }
        }
    }
}